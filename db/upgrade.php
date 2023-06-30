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
 * Datbase upgrade script
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade mod_cms database
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_cms_upgrade($oldversion) {
    global $DB;

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

    return true;
}
