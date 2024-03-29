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

namespace mod_cms;

use mod_cms\local\datasource\roles as dsroles;
use mod_cms\local\model\cms;
use mod_cms\local\model\cms_types;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_import2_trait.php');


/**
 * Unit test for roles datasource.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datasource_roles_test extends \advanced_testcase {
    use test_import2_trait;

    /** Test data for import/export. */
    const IMPORT_DATAFILE = __DIR__ . '/fixtures/roles_data.json';

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Tests short name.
     *
     * @covers \mod_cms\local\datasource\roles::get_shortname
     */
    public function test_name() {
        $this->assertEquals('roles', dsroles::get_shortname());
    }

    /**
     * Tests get_key functions when the hash is not set.
     *
     * @covers \mod_cms\local\datasource\roles::get_config_cache_key
     * @covers \mod_cms\local\datasource\roles::get_instance_cache_key
     */
    public function test_no_hash() {
        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->set('idnumber', 'test-name');
        $cmstype->set('datasources', ['roles']);
        $cmstype->save();

        $cms = $cmstype->get_sample_cms();
        $cms->set('intro', '');
        $cms->set('course', 0);
        $cms->set('name', '');
        $cms->save();

        $ds = new dsroles($cms);
        // A cache key should be generated if one does not already exist.
        $this->assertNotEmpty($ds->get_full_cache_key());
        $this->assertDebuggingCalledCount(2);
    }

    /**
     * Tests import and export.
     *
     * @covers \mod_cms\local\datasource\roles::set_from_import
     * @covers \mod_cms\local\datasource\roles::get_for_export
     */
    public function test_import() {
        $importdata = json_decode(file_get_contents(self::IMPORT_DATAFILE));
        $manager = new manage_content_types();
        $cmstype = $manager->create((object) [
            'name' => 'name',
            'idnumber' => 'test-name',
            'datasources' => 'roles',
        ]);
        $cms = $cmstype->get_sample_cms();
        $ds = new dsroles($cms);

        $oldkey = $ds->get_config_cache_key();
        $ds->set_from_import($importdata);
        $exportdata = $ds->get_for_export();
        $this->assertEquals($importdata, $exportdata);
        // The config cache key fragment should be updated.
        $newkey = $ds->get_config_cache_key();
        $this->assertNotEquals($oldkey, $newkey);
    }

    /**
     * Tests get_data().
     *
     * @covers \mod_cms\local\datasource\roles::get_cached_data
     * @covers \mod_cms\local\datasource\roles::get_full_cache_key
     */
    public function test_get_data() {
        $cmstype = $this->import();

        // Create the course and module.
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);
        $cms = new cms($moduleinfo->instance);
        $dsroles = new dsroles($cms);
        $context = \context_course::instance($course->id);
        $manager = new manage_content_types();
        $config = $manager->get_config($cmstype->get('id'));
        $idasstr = (string) $cms->get('id');

        // Test that the cache key exists.
        $oldkey = $dsroles->get_full_cache_key();
        $this->assertNotEmpty($oldkey);
        $instkey = $dsroles->get_instance_cache_key();
        // Test that instance key starts with the ID.
        $this->assertEquals($idasstr, substr($instkey, 0, strlen($idasstr)));

        // Create users.
        $user1 = $this->add_user('Mary', 'Sue');
        $user2 = $this->add_user('Gary', 'Due');

        // Enrol in course.
        $this->enrol_user($user1, $course);
        $this->enrol_user($user2, $course);

        // Add roles to those users.
        $this->add_role($user1, 'teacher', $context);
        $this->add_role($user1, 'student', $context);
        $this->add_role($user2, 'student', $context);

        // Test that key has changed.
        $newkey = $dsroles->get_full_cache_key();
        $this->assertNotEquals($oldkey, $newkey);
        $instkey = $dsroles->get_instance_cache_key();
        // Test that instance key starts with the ID.
        $this->assertEquals($idasstr, substr($instkey, 0, strlen($idasstr)));

        // Test data for 'all'.
        $data = $this->get_cached_data($dsroles);
        $expected = $this->get_expected('all', ['marysue' => $user1, 'garydue' => $user2]);
        $this->assertEquals($expected, $data);

        // Test data for 'firstonly'.
        $config->roles_duplicates = 'firstonly';
        $manager->update($cmstype->get('id'), $config);
        $data = $this->get_cached_data($dsroles);
        $expected = $this->get_expected('firstonly', ['marysue' => $user1, 'garydue' => $user2]);
        $this->assertEquals($expected, $data);

        // Test data for 'nest'.
        $config->roles_duplicates = 'nest';
        $manager->update($cmstype->get('id'), $config);
        $data = $this->get_cached_data($dsroles);
        $expected = $this->get_expected('nest', ['marysue' => $user1, 'garydue' => $user2]);
        $this->assertEquals($expected, $data);
    }

    /**
     * Get data from the cache while testing cache integrity.
     *
     * @param dsroles $ds
     * @return \stdClass
     */
    protected function get_cached_data(dsroles $ds): \stdClass {
        $data = $ds->get_cached_data();
        $cache = \cache::make('mod_cms', 'cms_content_roles');
        $key = $ds->get_full_cache_key();
        $this->assertEquals($data, $cache->get($key));
        $this->assertEquals($data, $ds->get_data());

        return $data;
    }

    /**
     * Adds a user to the system.
     *
     * @param string $firstname
     * @param string $lastname
     * @return \stdClass
     */
    protected function add_user(string $firstname, string $lastname): \stdClass {
        $usernew = new \stdClass();
        $usernew->username = strtolower($firstname.$lastname);
        $usernew->firstname = $firstname;
        $usernew->lastname = $lastname;
        $usernew->email = $usernew->username . '@x';
        $usernew->password = hash_internal_user_password('!1Qqaaaa');
        $usernew->auth = 'manual';
        $usernew->confirmed = 1;
        $usernew->deleted = 0;
        $usernew->id = user_create_user($usernew, false, false);
        return $usernew;
    }

    /**
     * Enrols a user in a course.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     */
    protected function enrol_user(\stdClass $user, \stdClass $course) {
        global $DB;

        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $enrolplugin = enrol_get_plugin($instance->enrol);
        $enrolplugin->enrol_user($instance, $user->id);
    }

    /**
     * Assigns a role to a user.
     *
     * @param \stdClass $user
     * @param string $role
     * @param \context $context
     */
    protected function add_role(\stdClass $user, string $role, \context $context) {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => $role]);

        role_assign($roleid, $user->id, $context->id);
    }

    /**
     * Gets the expected data that shoudl be returned by get_data().
     *
     * @param string $duplicates
     * @param array $users
     * @return object
     */
    protected function get_expected(string $duplicates, array $users) {
        $expected = (object) [
            'roles' => [
                (object) [
                    'shortname' => 'teacher',
                    'name' => 'teacher',
                    'users' => [
                        (object) [
                            'id' => $users['marysue']->id,
                            'fullname' => 'Mary Sue',
                            'roles' => ['teacher', 'student'],
                        ],
                    ],
                ],
                (object) [
                    'shortname' => 'student',
                    'name' => 'student',
                    'users' => [
                        (object) [
                            'id' => $users['marysue']->id,
                            'fullname' => 'Mary Sue',
                            'roles' => ['teacher', 'student'],
                        ],
                        (object) [
                            'id' => $users['garydue']->id,
                            'fullname' => 'Gary Due',
                            'roles' => ['student'],
                        ],
                    ],
                ],
            ]
        ];
        if ($duplicates !== 'nest') {
            foreach ($expected->roles as $role) {
                foreach ($role->users as $user) {
                    unset($user->roles);
                }
            }
        }
        if ($duplicates !== 'all') {
            unset($expected->roles[1]->users[0]);
            $expected->roles[1]->users = array_values($expected->roles[1]->users);
        }
        return $expected;
    }

    /**
     * Test duplication (also tests backup and restore).
     *
     * @covers \mod_cms\local\datasource\roles::instance_backup_define_structure
     * @covers \mod_cms\local\datasource\roles::restore_define_structure
     */
    public function test_duplicate() {
        $cmstype = $this->import();
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $cm = get_coursemodule_from_id('', $moduleinfo->coursemodule, 0, false, MUST_EXIST);

        $cms = new cms($cm->instance);
        $ds = new dsroles($cms);
        $newcm = duplicate_module($course, $cm);
        $newcms = new cms($newcm->instance);
        $newds = new dsroles($newcms);

        $this->assertEquals($ds->get_data(), $newds->get_data());

        // Assert that the CMS type is not duplicated.
        $this->assertEquals($cms->get('typeid'), $newcms->get('typeid'));
    }
}
