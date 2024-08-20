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

/**
 * Define search area.
 *
 * @package    mod_cms
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.com>
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_cms\search;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cms/lib.php');

/**
 * Define search area.
 *
 * @package    mod_cms
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.com>
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmsfield extends \core_search\base_mod {

    /**
     * @var array Internal quick static cache.
     */
    protected $cmsdata = [];

    /**
     * Returns recordset containing required data for indexing cmsfield records.
     *
     * @param int $modifiedfrom timestamp
     * @param \context|null $context Optional context to restrict scope of returned results
     * @return moodle_recordset|null Recordset (or null if no results)
     */
    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;

        list ($contextjoin, $contextparams) = $this->get_context_restriction_sql(
                $context, 'cms', 'mc');
        if ($contextjoin === null) {
            return null;
        }

        $searcharea = explode(',', get_config('mod_cms', 'search_area'));
        if (empty($searcharea)) {
            return null;
        }
        list($insql, $inparams) = $DB->get_in_or_equal($searcharea);

        $sql = "SELECT mcd.*, mc.id AS cmsid, mc.course AS courseid
                  FROM {customfield_data} mcd
                  JOIN {cms} mc ON mc.id = mcd.instanceid
          $contextjoin
                 WHERE mcd.timemodified >= ? AND mcd.fieldid " . $insql . " ORDER BY mcd.timemodified ASC";
        return $DB->get_recordset_sql($sql, array_merge($contextparams, [$modifiedfrom], $inparams));
    }

    /**
     * Returns the document associated with this data id.
     *
     * @param stdClass $record
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = []) {
        try {
            $cm = $this->get_cm('cms', $record->cmsid, $record->courseid);
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

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($record->value, false));
        $doc->set('content', content_to_text($record->value, $record->valueformat));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->courseid);
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
            $cminfo = $this->get_cm('cms', $data->instanceid, $data->courseid);
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }

        // Recheck uservisible although it should have already been checked in core_search.
        if ($cminfo->uservisible === false) {
            return \core_search\manager::ACCESS_DENIED;
        }

        $context = \context_module::instance($cminfo->id);

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
        return $this->get_context_url($doc);
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
            $sql = "SELECT mcd.*, mc.id AS cmsid, mc.course AS courseid
                      FROM {customfield_data} mcd
                      JOIN {cms} mc ON mc.id = mcd.instanceid
                     WHERE mcd.id = :id";
            $this->cmsdata[$id] = $DB->get_record_sql($sql, ['id' => $id]);
            if (!$this->cmsdata[$id]) {
                throw new \dml_missing_record_exception('cms_data');
            }
        }
        return $this->cmsdata[$id];
    }
}
