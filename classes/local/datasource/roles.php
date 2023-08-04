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

namespace mod_cms\local\datasource;

use mod_cms\local\model\cms;

/**
 * List roles
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roles extends base {
    /** Prefix used for form elements. */
    const FORM_PREFIX = 'roles_';

    /**
     * Get the display name.
     *
     * @return string
     */
    public static function get_displayname(): string {
        return get_string('roles:displayname', 'mod_cms');
    }

    /**
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        $data = new \stdClass();

        $showuser = 'all'; // List user under each first role.

        $rolestolist = $this->cms->get_type()->get_custom_data('roles_config');
        if (!is_null($rolestolist)) {
            $showuser = $rolestolist->duplicates;
            $rolestolist = $rolestolist->list;
        }

        $rolesdata = [];

        // Prepares the source material depending on whether this is a sample or real.
        if ($this->cms->issample) {
            $users = [
                123 => (object) ['fullname' => 'Billy Goat'],
                451 => (object) ['fullname' => 'Jack Horner'],
                693 => (object) ['fullname' => 'Puss in Boots'],
            ];

            $usersroles = [
                123 => [
                    (object) ['shortname' => 'student'],
                    (object) ['shortname' => 'teacher'],
                ],
                451 => [
                    (object) ['shortname' => 'manager'],
                    (object) ['shortname' => 'student'],
                ],
                693 => [
                    (object) ['shortname' => 'student'],
                ],
            ];

            $rolesincontext = [
                (object) ['shortname' => 'student', 'name' => '', 'coursealias' => ''],
                (object) ['shortname' => 'teacher', 'name' => '', 'coursealias' => ''],
                (object) ['shortname' => 'manager', 'name' => '', 'coursealias' => ''],
                (object) ['shortname' => 'editingteacher', 'name' => '', 'coursealias' => ''],
            ];
        } else {
            $context = \context_course::instance($this->cms->get('course'));

            $rolesincontext = get_all_roles($context);

            // Gets the roles assigned in the context, indexed by user ID, then assign ID.
            $usersroles = get_users_roles($context, [], false);

            // Get a list of users from the database.
            $userids = array_keys($usersroles);
            $users = user_get_users_by_id($userids);
            foreach ($users as $user) {
                $user->fullname = fullname($user);
            }
        }

        // Create a roles data array, in the order specified in the config. If none, then all roles are included.
        // We use the shortname for indexing to help with filling out the data,
        // even though it will be stripped away before returning.
        if (empty($rolestolist)) {
            foreach ($rolesincontext as $record) {
                $shortname = $record->shortname;
                $rolesdata[$shortname] = new \stdClass();
                $rolesdata[$shortname]->shortname = $shortname;
                $rolesdata[$shortname]->name = $record->coursealias ?: $record->name ?: $shortname;
                $rolesdata[$shortname]->users = [];
            }
        } else {
            // Create the rolesdata array in the order specified.
            foreach ($rolestolist as $shortname) {
                $rolesdata[$shortname] = new \stdClass();
                $rolesdata[$shortname]->shortname = $shortname;
                $rolesdata[$shortname]->name = ''; // Placeholder to ensure consistent ordering.
                $rolesdata[$shortname]->users = [];
            }
            // Add the course alias name.
            foreach ($rolesincontext as $record) {
                if (isset($rolesdata[$record->shortname])) {
                    $rolesdata[$record->shortname]->name = $record->coursealias ?: $record->name ?: $record->shortname;
                }
            }
        }

        // Convert user indexed array to role indexed array.
        foreach ($usersroles as $userid => $rolerecords) {
            $userobj = new \stdClass();
            $userobj->id = $userid;
            // Add user fields.
            $userobj->fullname = $users[$userid]->fullname;
            // TODO: In future, more fields may be needed.

            if ($showuser === 'nest') {
                $userobj->roles = [];
            }

            $alreadyadded = false;

            // We use rolesdata to ensure correct ordering.
            foreach ($rolesdata as $shortname => $roledata) {
                foreach ($rolerecords as $record) {
                    if ($record->shortname !== $shortname) {
                        continue;
                    }
                    if ($showuser === 'nest') {
                        $userobj->roles[] = $record->shortname;
                    }
                    if (!$alreadyadded || ($showuser === 'all')) {
                        $rolesdata[$record->shortname]->users[] = $userobj;
                    }
                    $alreadyadded = true;
                    break;
                }
            }
        }

        $data->roles = array_values($rolesdata);

        return $data;
    }

    /**
     * Add fields to the CMS type config form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        // Add a heading.
        $mform->addElement('header', 'heading', get_string('roles:config:header', 'cms'));

        // A list of roles to list.
        $mform->addElement('textarea', self::FORM_PREFIX . 'list', get_string('roles:config:list', 'cms'));
        $mform->addElement(
            'select',
            self::FORM_PREFIX . 'duplicates',
            get_string('roles:config:duplicates', 'cms'),
            [
                'all' => get_string('roles:config:duplicates:all', 'cms'),
                'firstonly' => get_string('roles:config:duplicates:firstonly', 'cms'),
                'nest' => get_string('roles:config:duplicates:nest', 'cms'),
            ]
        );
    }

    /**
     * Add extra data needed to add to the config form.
     *
     * @param mixed $data
     */
    public function config_form_default_data($data) {
        $cmstype = $this->cms->get_type();
        $config = $cmstype->get_custom_data('roles_config');
        if (!is_null($config)) {
            $data->roles_list = implode("\n", $config->list);
            $data->roles_duplicates = $config->duplicates;
        }
    }

    /**
     * Validate fields added by datasource.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function config_form_validation(array $data, array $files): array {
        $errors = [];
        $existingroles = array_map(
            function($record) {
                return $record->shortname;
            },
            get_all_roles()
        );

        foreach ($this->split_lines($data['roles_list'] ?? '') as $rolename) {
            if (!in_array($rolename, $existingroles)) {
                $errors[] = get_string('roles:error:role_does_not_exist', 'cms', $rolename);
            }
        }
        return $errors;
    }

    /**
     * Called after updating cms type to perform any extra saving required by datasource.
     *
     * @param mixed $data
     */
    public function config_on_update($data) {
        $cmstype = $this->cms->get_type();
        $config = [
            'list' => $this->split_lines($data->roles_list ?? ''),
            'duplicates' => $data->roles_duplicates ?? '',
        ];
        $cmstype->set_custom_data('roles_config', $config);
        $cmstype->save();
    }

    /**
     * Get configuration data for exporting.
     *
     * @return \stdClass
     */
    public function get_for_export(): \stdClass {
        $data = new \stdClass();
        $cmstype = $this->cms->get_type();
        $config = $cmstype->get_custom_data('roles_config');
        if (!is_null($config)) {
            $data->list = $config->list;
            $data->showduplicates = $config->duplicates;
        }
        return $data;
    }

    /**
     * Import configuration from an object.
     *
     * @param \stdClass $data
     */
    public function set_from_import(\stdClass $data) {
        $cmstype = $this->cms->get_type();
        $config = [
            'list' => $data->list ?? [],
            'duplicates' => $data->showduplicates ?? '',
        ];
        $cmstype->set_custom_data('roles_config', $config);
        $cmstype->save();
    }

    /**
     * Returns a hash of the content, representing the data stored for the datasource.
     *
     * @return string
     */
    public function get_content_hash(): string {
        // Hash is stored in the DB with the cms, so gets returned by cms::get_content_hash().
        return '';
    }

    /**
     * Split a multi-line string into individual lines, trimming each line, and removing empties.
     *
     * @param string $lines
     * @return array
     */
    protected function split_lines(string $lines): array {
        // Trim whitespace and split into an array.
        $lines = explode(PHP_EOL, trim($lines));

        // Trim each item.
        $lines = array_map(
            function($v) {
                return trim($v);
            },
            $lines
        );

        // Remove empty items.
        return array_filter(
            $lines,
            function($v) {
                return $v !== '';
            }
        );
    }

    /**
     * Called whenever a role assignment has changed in order to update the datasource cache.
     *
     * @param \core\event\base $event
     */
    public static function on_role_changed(\core\event\base $event) {
        $cmslist = cms::get_records(['course' => $event->get_data()['courseid']]);
        foreach ($cmslist as $cms) {
            $cacherev = $cms->get_custom_data('roles_course_role_cache_rev') ?? 0;
            $cms->set_custom_data('roles_course_role_cache_rev', ++$cacherev);
            $cms->save();
        }
    }
}
