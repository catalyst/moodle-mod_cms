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

/**
 * Database upgrade script
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_cms\local\model\cms_types;
use mod_cms\local\model\cms;

/**
 * Function to upgrade mod_cms database
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_cms_upgrade($oldversion) {
    global $DB, $SITE;

    $dbman = $DB->get_manager();

    if ($oldversion < 2023060601) {
        // Define field mustache.
        $table = new xmldb_table('cms_types');
        $field = new xmldb_field('mustache', XMLDB_TYPE_TEXT, null, null, false, null, null, 'descriptionformat');

        // Conditionally launch add field mustache.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2023060601, 'cms');
    }

    if ($oldversion < 2023063001) {
        // Define field datasources.
        $table = new xmldb_table('cms_types');
        $field = new xmldb_field('datasources', XMLDB_TYPE_TEXT, null, null, false, null, null, 'descriptionformat');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2023063001, 'cms');
    }

    if ($oldversion < 2023070500) {
        // Define field customdata.
        $table = new xmldb_table('cms');
        $field = new xmldb_field('customdata', XMLDB_TYPE_TEXT, null, null, false, null, null, 'typeid');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {cms} SET customdata='{}'");
        }

        // Define field customdata.
        $table = new xmldb_table('cms_types');
        $field = new xmldb_field('customdata', XMLDB_TYPE_TEXT, null, null, false, null, null, 'mustache');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {cms_types} SET customdata='{}'");
        }

        upgrade_mod_savepoint(true, 2023070500, 'cms');
    }

    if ($oldversion < 2023072400) {
        // Remove tables if they exist.
        $table = new xmldb_table('cms_userlists');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('cms_userlist_columns');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_mod_savepoint(true, 2023072400, 'cms');
    }

    if ($oldversion < 2023072500) {
        // Define field datasources.
        $table = new xmldb_table('cms_types');
        $field = new xmldb_field('isvisible', XMLDB_TYPE_INTEGER, 1, null, true, null, 0, 'customdata');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2023072500, 'cms');
    }

    if ($oldversion < 2023072600) {
        $records = $DB->get_records_select('cms_types', 'datasources is null', [], '', 'id, datasources');
        foreach ($records as $record) {
            $record->datasources = '';
            $DB->update_record('cms_types', $record, true);
        }

        $table = new xmldb_table('cms_types');
        $field = new xmldb_field('isvisible', XMLDB_TYPE_INTEGER, 1, null, true, null, 1, 'customdata');

        $dbman->change_field_default($table, $field);

        upgrade_mod_savepoint(true, 2023072600, 'cms');
    }

    if ($oldversion < 2023080100) {
        // Update CMS records to include the course ID.
        $records = $DB->get_records('cms');
        foreach ($records as $record) {
            $cm = get_coursemodule_from_instance('cms', $record->id, 0, false, MUST_EXIST);
            $record->course = $cm->course;
            $DB->update_record('cms', $record);
        }

        upgrade_mod_savepoint(true, 2023080100, 'cms');
    }

    if ($oldversion < 2023080400) {
        $table = new xmldb_table('cms');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, 4, null, false, false, 0, 'intro');

        $dbman->change_field_default($table, $field);

        upgrade_mod_savepoint(true, 2023080400, 'cms');
    }

    if ($oldversion < 2023080900) {
        $table = new xmldb_table('cms_types');
        $field = new xmldb_field('title_mustache', XMLDB_TYPE_TEXT, null, null, false, null, null, 'datasources');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2023080900, 'cms');
    }

    if ($oldversion < 2023081401) {
        // Make sure title_mustache is set to something by default.
        $DB->execute("UPDATE {cms_types} SET title_mustache = name WHERE title_mustache='' OR title_mustache IS NULL");

        upgrade_mod_savepoint(true, 2023081401, 'cms');
    }

    if ($oldversion < 2023081600) {
        $table = new xmldb_table('cms_types');
        $field = new xmldb_field('idnumber', XMLDB_TYPE_CHAR, 255, null, false, null, '', 'name');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Give each CMS type a suitably unique ID.
        $records = cms_types::get_records();
        foreach ($records as $cmstype) {
            $cmstype->set('idnumber', uniqid($SITE->shortname, true));
            $cmstype->save();
        }

        upgrade_mod_savepoint(true, 2023081600, 'cms');
    }

    if ($oldversion < 2023082800) {
        // Transfer 'userlistmaxinstanceid' customdata from cms to cms_types.
        $records = cms::get_records();
        foreach ($records as $record) {
            $max = $record->get_custom_data('userlistmaxinstanceid');
            if ($max === null) {
                continue;
            }
            $cmstype = $record->get_type();
            $oldmax = $cmstype->get_custom_data('userlistmaxinstanceid') ?? 0;
            if ($max > $oldmax) {
                $cmstype->set_custom_data('userlistmaxinstanceid', $max);
                $cmstype->save();
            }
            $record->set_custom_data('userlistmaxinstanceid', null);
            $record->save();
        }

        upgrade_mod_savepoint(true, 2023082800, 'cms');
    }

    if ($oldversion < 2023110100) {
        // Changing nullability of field idnumber on table cms_types to not null.
        $table = new xmldb_table('cms_types');
        $field = new xmldb_field('idnumber', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'name');

        // Launch change of nullability for field idnumber.
        $dbman->change_field_notnull($table, $field);

        // Cms savepoint reached.
        upgrade_mod_savepoint(true, 2023110100, 'cms');
    }

    return true;
}
