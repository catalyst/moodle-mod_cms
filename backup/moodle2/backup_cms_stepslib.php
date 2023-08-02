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

/**
 * Backup the activity.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_cms_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines structure of activity backup
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        global $DB;

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        $root = new backup_nested_element('cms', ['id'], [
            'course',
            'name',
            'intro',
            'introformat',
            'typeid',
            'customdata',
            'usermodified',
            'timecreated',
            'timemodified',
        ]);

        $root->annotate_ids('course', 'course');
        $root->annotate_ids('user', 'usermodified');
        $root->annotate_files('mod_cms', 'intro', null);

        $cmsrecord = $DB->get_record('cms', ['id' => $this->task->get_activityid()]);
        $cms = new cms(0, clone $cmsrecord);
        // We don't want cache hashes to be backed up. Other custom data should be backed up by the various datasource classes.
        $cmsrecord->customdata = '';
        $root->set_source_array([$cmsrecord]);

        $cmstype = new backup_nested_element('cms_types', ['id'], [
            'name',
            'idnumber',
            'description',
            'descriptionformat',
            'title_mustache',
            'mustache',
            'datasources',
            'customdata',
            'usermodified',
            'timecreated',
            'timemodified',
        ]);

        $cmstyperecord = $this->retrieve_cms_type($cmsrecord);
        $cmstype->set_source_array([$cmstyperecord]);

        $cmstype->annotate_ids('user', 'usermodified');
        $cmstype->annotate_files('mod_cms', 'description', null);

        $root->add_child($cmstype);

        $configdatasources = new backup_nested_element('config_datasources');
        $cmstype->add_child($configdatasources);

        $instancedatasources = new backup_nested_element('instance_datasources');
        $root->add_child($instancedatasources);

        foreach (dsbase::get_datasources($cms) as $ds) {
            $ds->config_backup_define_structure($configdatasources);
            $ds->instance_backup_define_structure($instancedatasources);
        }

        return $this->prepare_activity_structure($root);
    }

    /**
     * Retrieves a record from cms type table associated with the current activity
     *
     * @param stdClass $cmsrecord
     * @return stdClass|null
     */
    protected function retrieve_cms_type($cmsrecord) {
        global $DB;
        if (!$cmsrecord->typeid) {
            return null;
        }

        $record = $DB->get_record('cms_types', ['id' => $cmsrecord->typeid]);
        $record->customdata = '';

        return $record;
    }
}
