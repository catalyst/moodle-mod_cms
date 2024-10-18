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

use stdClass;
use mod_cms\local\model\cms;
use mod_cms\local\renderer;

/**
 * Search area for mod_cms activities.
 *
 * @package    mod_cms
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity extends \core_search\base_activity {

    /**
     * Returns the document associated with this activity.
     *
     * Overwriting base_activity method as page contents field is required,
     * description field is not.
     *
     * @param stdClass $record
     * @param array    $options
     * @return bool|\core_search\document
     */
    public function get_document($record, $options = []) {

        try {
            $cm = $this->get_cm($this->get_module_name(), $record->id, $record->course);
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
        $doc->set('title', content_to_text($record->name, false));

        $cms = new cms($cm->instance);
        $renderer = new renderer($cms);
        $doc->set('content', content_to_text($renderer->get_html(), FORMAT_HTML));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->course);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        return $doc;
    }

    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return false;
    }

    /**
     * Return the context info required to index files for
     * this search area.
     *
     * @return array
     */
    public function get_search_fileareas() {
        return ['content'];
    }

    /**
     * Add the cms file attachments.
     *
     * @param \core_search\document $document The current document
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
