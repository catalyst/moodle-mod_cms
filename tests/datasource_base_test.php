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

use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\model\cms_types;

/**
 * Tests the datasource base class.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datasource_base_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test get_datasources function.
     *
     * @covers \mod_cms\local\datasource\base::get_datasources
     * @covers \mod_cms\local\datasource\base::register_datasources
     * @covers \mod_cms\local\datasource\base::add_datasource_class
     */
    public function test_get_datasources() {
        $cmstype = new cms_types();
        $cmstype->set('name', 'somename');
        $cmstype->set('datasources', array_keys(dsbase::get_datasource_labels(false)));
        $cmstype->save();

        // Get a list of names of datasource classes.
        $datasources = dsbase::get_datasources($cmstype);
        $names = [];
        foreach ($datasources as $ds) {
            $names[] = get_class($ds);
        }

        // Assert that the builtin classes are in this list.
        foreach (dsbase::BUILTIN_DATASOURCES as $ds) {
            $this->assertTrue(in_array('mod_cms\\local\\datasource\\' . $ds, $names));
        }
    }

    /**
     * Test the guards for add_datasource_class
     *
     * @dataProvider add_datasource_class_provider
     * @covers \mod_cms\local\datasource\base::add_datasource_class
     * @param string $classname
     * @param string $errormessage
     */
    public function test_add_datasource_class_errors(string $classname, string $errormessage) {
        // Make sure the datasources are there.
        dsbase::register_datasources();

        $this->expectException('\moodle_exception');
        $this->expectExceptionMessage($errormessage);

        // This should fail.
        dsbase::add_datasource_class($classname);
    }

    /**
     * Provider for test_add_datasource_class_errors().
     *
     * @return array[]
     */
    public function add_datasource_class_provider() {
        return [
            ['not_a_namespace\\not_a_class', get_string('error:class_missing', 'mod_cms', 'not_a_namespace\\not_a_class')],
            ['mod_cms\\local\\lib', get_string('error:must_be_base', 'mod_cms', 'mod_cms\\local\\lib')],
            ['mod_cms\\local\\datasource\\site', get_string('error:name_not_unique', 'mod_cms', 'site')],
        ];
    }
}
