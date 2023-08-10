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
use mod_cms\local\model\cms;

defined('MOODLE_INTERNAL') || die();

require_once( __DIR__ . '/fixtures/test_import1_trait.php');

/**
 * Tests cms instances.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_instance_test  extends \advanced_testcase {
    use test_import1_trait;

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Tests adding a new instance, including datasource data.
     *
     * @covers \mod_cms\local\lib::add_instance
     * @throws \coding_exception
     */
    public function test_add_instance() {
        $cmstype = $this->import();

        // Create a course.
        $course = $this->create_course();

        // Add a module to the course.
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        // Check that the instance and datasource info exists.
        $cmsid = $moduleinfo->instance;

        $count = cms::count_records(['id' => $cmsid]);
        $this->assertEquals(1, $count);

        $cms = new cms($cmsid);
        $this->assertEquals('Field A', $cms->get('name'));
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
        $cmstype = $this->import();

        // Create a course.
        $course = $this->create_course();

        // Add a module to the course.
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        // Check that the instance and datasource info exists.
        $cmsid = $moduleinfo->instance;

        // Modify some fields and update.
        $cmstype->set('title_mustache', '{{fields.bfield}}');
        $cmstype->save();
        $moduleinfo->customfield_bfield = 'Field B';
        update_module($moduleinfo);

        $cms = new cms($cmsid);
        $this->assertEquals('Field B', $cms->get('name'));
        $this->assertEquals($cmstype->get('id'), $cms->get('typeid'));

        $ds = new dsfields($cms);
        $fieldsdata = $ds->get_data();
        $this->assertEquals('Field A', $fieldsdata->afield);
        $this->assertEquals('Field B', $fieldsdata->bfield);
    }

    /**
     * Tests that the CMS instance is deleted when the module is deleted.
     *
     * @covers \mod_cms\local\lib::delete_instance
     */
    public function test_delete_instance() {
        $cmstype = $this->import();

        // Create a course.
        $course = $this->create_course();

        // Add a module to the course.
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);
        $cmsid = $moduleinfo->instance;

        $count = cms::count_records(['id' => $cmsid]);
        $this->assertEquals(1, $count);

        course_delete_module($moduleinfo->coursemodule);

        $count = cms::count_records(['id' => $cmsid]);
        $this->assertEquals(0, $count);
    }
}
