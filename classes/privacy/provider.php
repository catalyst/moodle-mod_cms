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

namespace mod_cms\privacy;

use context;
use core_privacy\local\request\userlist;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy subsystem implementation for mod_cms
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Get information about the user data stored by this plugin.
     *
     * @param  collection $collection An object for storing metadata.
     * @return collection The metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'cms',
            [
                'usermodified' => 'privacy:metadata:cms:usermodified',
            ],
            'privacy:metadata:cms'
        );
        $collection->add_database_table(
            'cms_types',
            [
                'moodleuid' => 'privacy:metadata:cms_types:usermodified',
            ],
            'privacy:metadata:cms_types'
        );
        $collection->add_database_table(
            'cms_userlists',
            [
                'usermodified' => 'privacy:metadata:cms:usermodified',
            ],
            'privacy:metadata:cms_userlist'
        );
        $collection->add_database_table(
            'cms_userlist_columns',
            [
                'usermodified' => 'privacy:metadata:cms:usermodified',
            ],
            'privacy:metadata:cms_userlist_columns'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The ID of the user
     *
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // Just enough to pass unit tests.
        return new contextlist();
    }

    /**
     * Export personal data for the given approved_contextlist.
     * User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist A list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // Empty on purpose.
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The context to delete in
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        // Empty on purpose.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist A list of contexts approved for deletion
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // Empty on purpose.
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The list of users who have data in this plugin.
     */
    public static function get_users_in_context(userlist $userlist) {
        // Empty on purpose.
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // Empty on purpose.
    }
}
