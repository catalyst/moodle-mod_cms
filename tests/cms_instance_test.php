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

use mod_cms\local\datasource\fields as dsfields;
use mod_cms\local\model\{cms, cms_types};

/**
 * Tests cms instances.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_instance_test  extends \advanced_testcase {
    /** Name of YAML file to define CMS type used in testing. */
    public const IMPORTDATFILE = __DIR__ . '/fixtures/test_import_1.yml';

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Import a file defining a cms type.
     *
     * @param string $filename
     * @return cms_types
     */
    public function import(string $filename): cms_types {
        $importdata = file_get_contents($filename);
        $cmstype = new cms_types();
        $cmstype->import($importdata);
        return $cmstype;
    }

    /**
     * Create a course for testing.
     *
     * @return object
     */
    public function create_course() {
        $data = (object) [
            'fullname' => 'Fullname',
            'shortname' => 'Shortname',
            'category' => 1,
        ];
        return create_course($data);
    }

    /**
     * Creates a module for a course.
     *
     * @param int $typeid
     * @param int $courseid
     * @return object|\stdClass
     */
    public function create_module(int $typeid, int $courseid) {
        $moduleinfo = (object) [
            'modulename' => 'cms',
            'course' => $courseid,
            'section' => 0,
            'visible' => true,
            'typeid' => $typeid,
            'name' => 'Some module',
            'customfield_afield' => 'Field A',
        ];
        return create_module($moduleinfo);
    }

    /**
     * Tests adding a new instance, including datasource data.
     *
     * @covers \mod_cms\local\lib::add_instance
     * @throws \coding_exception
     */
    public function test_add_instance() {
        $cmstype = $this->import(self::IMPORTDATFILE);

        // Create a course.
        $course = $this->create_course();

        // Add a module to the course.
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        // Check that the instance and datasource info exists.
        $instanceid = $moduleinfo->instance;

        $cms = new cms($instanceid);
        $this->assertEquals('Some module', $cms->get('name'));
        $this->assertEquals($cmstype->get('id'), $cms->get('typeid'));

        $ds = new dsfields($cms);
        $fieldsdata = $ds->get_data();
        $this->assertEquals('Field A', $fieldsdata->afield);
        $this->assertEquals('def', $fieldsdata->bfield);
    }

    /**
     * Tests updating an instance, including dataource data.
     *
     * @covers \mod_cms\local\lib::add_instance
     * @throws \coding_exception
     */
    public function test_update_instance() {
        $cmstype = $this->import(self::IMPORTDATFILE);

        // Create a course.
        $course = $this->create_course();

        // Add a module to the course.
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        // Check that the instance and datasource info exists.
        $instanceid = $moduleinfo->instance;

        // Modify some fields and update.
        $moduleinfo->name = 'New name';
        $moduleinfo->customfield_bfield = 'Field B';
        update_module($moduleinfo);

        $cms = new cms($instanceid);
        $this->assertEquals('New name', $cms->get('name'));
        $this->assertEquals($cmstype->get('id'), $cms->get('typeid'));

        $ds = new dsfields($cms);
        $fieldsdata = $ds->get_data();
        $this->assertEquals('Field A', $fieldsdata->afield);
        $this->assertEquals('Field B', $fieldsdata->bfield);
    }
}
