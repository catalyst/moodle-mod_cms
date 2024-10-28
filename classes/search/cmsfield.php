<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_cms\search;

defined('MOODLE_INTERNAL') || die();

use mod_cms\local\model\cms;
use mod_cms\local\renderer;

require_once($CFG->dirroot . '/mod/cms/lib.php');

/**
 * Define search area.
 *
 * @package    mod_cms
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.com>
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmsfield extends \core_search\base_activity {

    /**
     * @var array Internal quick static cache.
     */
    protected $cmsdata = [];

    /**
     * @var array Internal quick static cache.
     */
    protected $defaultvalues = null;

    /**
     * Returns the document associated with this data id.
     *
     * @param stdClass $record
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = []) {
        try {
            $cm = $this->get_cm('cms', $record->id, $record->course);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        $cms = new cms($cm->instance);
        $renderer = new renderer($cms);
        $value = $renderer->get_html();
        $title = $cms->get('name');
        $valueformat = FORMAT_HTML;

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($title, false));
        $doc->set('content', content_to_text($value, $valueformat));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->course);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->timecreated)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Whether the user can access the document or not.
     *
     * @param int $id data id
     * @return bool
     */
    public function check_access($id) {
        try {
            $data = $this->get_data($id);
            $cminfo = $this->get_cm('cms', $data->id, $data->courseid);
            $context = \context_module::instance($cminfo->id);
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }

        // Recheck uservisible although it should have already been checked in core_search.
        if ($cminfo->uservisible === false) {
            return \core_search\manager::ACCESS_DENIED;
        }

        if (!has_capability('mod/cms:view', $context)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Link to the cms.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        $cm = get_coursemodule_from_id('cms', $contextmodule->instanceid, $doc->get('courseid'), true);
        return new \moodle_url('/course/view.php', ['id' => $doc->get('courseid'), 'section' => $cm->sectionnum]);
    }

    /**
     * Link to the cms.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        return new \moodle_url('/mod/cms/view.php', ['id' => $contextmodule->instanceid]);
    }

    /**
     * Returns the specified data from its internal cache.
     *
     * @throws \dml_missing_record_exception
     * @param int $id
     * @return stdClass
     */
    protected function get_data($id) {
        global $DB;
        if (empty($this->cmsdata[$id])) {
            $sql = "SELECT mc.id, mc.course AS courseid
                      FROM {cms} mc
                     WHERE mc.id = :id";
            $this->cmsdata[$id] = $DB->get_record_sql($sql, ['id' => $id], MUST_EXIST);
        }
        return $this->cmsdata[$id];
    }

    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return true;
    }

    /**
     * Return the context info required to index files for
     * this search area.
     *
     * @return array
     */
    public function get_search_fileareas() {
        return ['value'];
    }

    /**
     * Add the cms file attachments.
     *
     * @param document $document The current document
     * @return null
     */
    public function attach_files($document) {
        global $DB;

        $fileareas = $this->get_search_fileareas();
        // File is in "customfield_file" for component, "value" for filearea, and for customfield data id for itemid.
        $contextid = \context_system::instance()->id;
        $component = 'customfield_file';
        $cmsid = $document->get('itemid');

        // Search customfield data from cms record.
        $sql = "SELECT mcd.id
                  FROM {cms} mc
                  JOIN {customfield_data} mcd ON mc.id = mcd.instanceid
                  JOIN {customfield_field} mcf ON mcf.id = mcd.fieldid
                  JOIN {customfield_category} mcc ON mcf.categoryid = mcc.id
                 WHERE mc.id = ? AND mcc.component = 'mod_cms' AND mcc.area = 'cmsfield' AND mcf.type = 'file'";
        $param = [$cmsid];
        $filedata = $DB->get_records_sql($sql, $param);

        foreach ($fileareas as $filearea) {
            foreach ($filedata as $data) {
                $fs = get_file_storage();
                $files = $fs->get_area_files($contextid, $component, $filearea, $data->id, '', false);

                foreach ($files as $file) {
                    $document->add_stored_file($file);
                }
            }
        }
    }
}
