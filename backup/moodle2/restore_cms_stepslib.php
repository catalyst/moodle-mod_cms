<?php
// This file is part of Moodle - https://moodle.org/
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

use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\model\cms;
use mod_cms\local\model\cms_types;

/**
 * Restore CMS activity.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_cms_activity_structure_step extends restore_activity_structure_step {
    /** @var array Datasource restore processors that need to have after_execute() or after_restore() called. */
    protected $afterparty = [];

    /**
     * Add a datasource restore processor object to the afterparty.
     *
     * @param object $obj
     */
    public function add_to_after_party($obj) {
        $this->afterparty[] = $obj;
    }

    /**
     * Define the structure for the restore
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('cms', '/activity/cms');
        $paths[] = new restore_path_element('cms_types', '/activity/cms/cms_types');

        foreach (dsbase::get_datasources(null, false) as $ds) {
            $paths = $ds->restore_define_structure($paths, $this);
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the cms data.
     *
     * @param array $data
     */
    protected function process_cms(array $data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->typeid = 0;

        // Insert the record.
        $newitemid = $DB->insert_record('cms', $data);

        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore the cms type.
     *
     * @param array $data
     */
    protected function process_cms_types(array $data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $typeid = $this->find_existing_cms_type($data);

        if ($typeid === false) {
            $typeid = $DB->insert_record('cms_types', $data);
        }

        $this->set_mapping('cms_types', $oldid, $typeid, true);

        $DB->update_record('cms', (object) ['id' => $this->get_new_parentid('cms'), 'typeid' => $typeid]);
    }

    /**
     * Looks for an existing record that matches the CMS type.
     *
     * @param stdClass $data
     * @return int/false The db ID of the CMS type or false if none was found.
     */
    protected function find_existing_cms_type(\stdClass $data) {
        global $DB;

        if ($typeid = $this->get_mappingid('cms_types', $data->id)) {
            return $typeid;
        }

        if ($this->task->is_samesite()) {
            if ($DB->record_exists('cms_types', ['id' => $data->id])) {
                return $data->id;
            }
        }

        // Try to find a CMS type is that is a match for content. Returns false if none found.
        $sqltitlemustache = $DB->sql_compare_text('title_mustache');
        $sqltitlemustacheparam = $DB->sql_compare_text(':title_mustache');
        $sqlmustache = $DB->sql_compare_text('mustache');
        $sqlmustacheparam = $DB->sql_compare_text(':mustache');

        $sql = "SELECT id
                  FROM {cms_types}
                 WHERE name = :name
                   AND idnumber = :idnumber
                   AND datasources = :datasources
                   AND $sqltitlemustache = $sqltitlemustacheparam
                   AND $sqlmustache = $sqlmustacheparam";
        $params = [
            'name' => $data->name,
            'idnumber' => $data->idnumber,
            'datasources' => $data->datasources,
            'title_mustache' => $data->title_mustache,
            'mustache' => $data->mustache,
        ];
        $record = $DB->get_record_sql($sql, $params);

        return $record->id ?? false;
    }

    /**
     * Perform any after execute tasks.
     */
    protected function after_execute() {
        parent::after_execute();

        $this->add_related_files('mod_cms', 'description', null);
        $this->add_related_files('mod_cms', 'intro', null);

        foreach ($this->afterparty as $processor) {
            if (method_exists($processor, 'after_execute')) {
                $processor->after_execute();
            }
        }
    }

    /**
     * Perform any after restore tasks.
     */
    protected function after_restore() {
        parent::after_restore();
        foreach ($this->afterparty as $processor) {
            if (method_exists($processor, 'after_restore')) {
                $processor->after_restore();
            }
        }
    }
}
