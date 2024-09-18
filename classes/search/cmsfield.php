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
     * @var array Internal quick static cache.
     */
    protected $defaultvalues = null;

    /**
     * Returns recordset containing required data for indexing cmsfield records.
     *
     * @param int $modifiedfrom timestamp
     * @param \context|null $context Optional context to restrict scope of returned results
     * @return \moodle_recordset|null Recordset (or null if no results)
     */
    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;

        list ($contextjoin, $contextparams) = $this->get_context_restriction_sql(
                $context, 'cms', 'mc');
        if ($contextjoin === null) {
            return null;
        }

        // Search area is from customfield_data, but if the record is missing from activity, use default value.
        $sqlgroupconcat = $DB->sql_group_concat("mcd.value", ', ', 'mcf.sortorder');
        $sql = "SELECT ccms.id, ccms.course AS courseid, ccms.typeid, cmcf.name AS fieldname, cmcf.type,
                       cdata.dataid dataid, cdata.value AS value, cdata.valueformat AS valueformat,
                       cdata.timecreated AS timecreated, cdata.timemodified AS timemodified
                  FROM {cms} ccms
                  JOIN (
                       SELECT mc.id, MAX(mcf.id) AS fieldid,
                              MAX(mcd.id) dataid, {$sqlgroupconcat} AS value, MAX(mcd.valueformat) AS valueformat,
                              MAX(mcd.timecreated) AS timecreated, MAX(mcd.timemodified) AS timemodified
                         FROM {cms} mc
                         JOIN {customfield_data} mcd ON mc.id = mcd.instanceid
                         JOIN {customfield_field} mcf ON mcf.id = mcd.fieldid
                         JOIN {customfield_category} mcc ON mcf.categoryid = mcc.id
                 $contextjoin
                        WHERE mcd.timemodified >= ? AND mcc.component = 'mod_cms' AND mcc.area = 'cmsfield'
                          AND mcf.type IN ('textarea', 'text', 'file')
                     GROUP BY mc.id
                       ) cdata ON ccms.id = cdata.id
                  JOIN {customfield_field} cmcf ON cmcf.id = cdata.fieldid
                 UNION
                SELECT mc.id, mc.course AS courseid, mc.typeid, null AS fieldname, null AS type,
                       null AS dataid, null AS value, null AS valueformat,
                       mc.timecreated timecreated, mc.timemodified timemodified
                 FROM {cms} mc
         $contextjoin
            LEFT JOIN {customfield_data} mcd ON mc.id = mcd.instanceid
                WHERE mcd.id IS NULL AND mc.timecreated >= ?
             ORDER BY timemodified ASC";
        return $DB->get_recordset_sql($sql, array_merge($contextparams, [$modifiedfrom], $contextparams, [$modifiedfrom]));
    }

    /**
     * Returns the document associated with this data id.
     *
     * @param stdClass $record
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = []) {
        global $DB;
        try {
            $cm = $this->get_cm('cms', $record->id, $record->courseid);
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

        $defaultvalues = $this->get_default_values();

        // Check if it's default value or not.
        if (empty($record->dataid)) {
            $title = $defaultvalues[$record->typeid]->fieldname ?? '';
            $value = $defaultvalues[$record->typeid]->value ?? '';
            if (isset($defaultvalues[$record->typeid]->valueformat)) {
                $valueformat = $defaultvalues[$record->typeid]->valueformat;
            } else {
                if ($record->type == 'textarea') {
                    $valueformat = FORMAT_HTML;
                } else {
                    $valueformat = FORMAT_PLAIN;
                }
            }
        } else {
            $title = $record->fieldname;
            $value = $record->value;
            $valueformat = $record->valueformat;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($title, false));
        $doc->set('content', content_to_text($value, $valueformat));
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
     * Get default value for cms custom field.
     *
     * @return array
     */
    protected function get_default_values() {
        global $DB;
        if (is_null($this->defaultvalues)) {
            $defaultvalues = [];
            $sql = "SELECT mcf.id fieldid, mct.id typeid, mcf.configdata, mcf.name fieldname
                      FROM {cms_types} mct
                      JOIN {customfield_category} mcc ON mcc.itemid = mct.id
                      JOIN {customfield_field} mcf ON mcf.categoryid = mcc.id
                     WHERE mcc.component = 'mod_cms' AND mcc.area = 'cmsfield' AND mcf.type IN ('textarea', 'text')
                  ORDER BY mct.id, mcf.sortorder";
            $cmstypes = $DB->get_records_sql($sql);
            foreach ($cmstypes as $cmstype) {
                if (empty($defaultvalues[$cmstype->typeid])) {
                    $data = new \stdClass();
                    $configdata = json_decode($cmstype->configdata);
                    $data->value = $configdata->defaultvalue ?? 'Default value';
                    $data->valueformat = $configdata->defaultvalueformat ?? 0;
                    $data->fieldname = $cmstype->fieldname;
                } else {
                    $data = $defaultvalues[$cmstype->typeid];
                    $configdata = json_decode($cmstype->configdata);
                    $data->value .= ', ' . $configdata->defaultvalue;
                }
                $defaultvalues[$cmstype->typeid] = $data;
            }
            $this->defaultvalues = $defaultvalues;
        }
        return $this->defaultvalues;
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
     * Add the forum post attachments.
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
