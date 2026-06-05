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

use mod_cms\local\datasource\course as dscourse;
use mod_cms\local\model\cms;
use mod_cms\local\model\cms_types;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_import2_trait.php');

/**
 * Unit tests for the course datasource.
 *
 * @package   mod_cms
 * @author    Brendan Heywood <brendan@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_cms\local\datasource\course
 */
final class datasource_course_test extends \advanced_testcase {
    use test_import2_trait;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Tests short name.
     *
     * @covers \mod_cms\local\datasource\course::get_shortname
     */
    public function test_name(): void {
        $this->assertEquals('course', dscourse::get_shortname());
    }

    /**
     * Tests get_data() returns sample placeholder values for a sample CMS.
     *
     * @covers \mod_cms\local\datasource\course::get_data
     */
    public function test_get_data_sample(): void {
        $cmstype = new cms_types();
        $cmstype->set('name', 'Test type');
        $cmstype->set('idnumber', 'test-course-ds');
        $cmstype->set('datasources', ['course']);
        $cmstype->save();

        $cms = $cmstype->get_sample_cms();
        $ds = new dscourse($cms);
        $data = $ds->get_data();

        $this->assertTrue(property_exists($data, 'fullname'));
        $this->assertTrue(property_exists($data, 'shortname'));
        $this->assertTrue(property_exists($data, 'courseurl'));
        $this->assertTrue(property_exists($data, 'summary'));
        $this->assertTrue(property_exists($data, 'idnumber'));
        $this->assertTrue(property_exists($data, 'courseimage'));
        // Sample data should not be empty.
        $this->assertNotEmpty($data->fullname);
        $this->assertNotEmpty($data->shortname);
    }

    /**
     * Tests get_data() returns real course data for a real CMS instance.
     *
     * @covers \mod_cms\local\datasource\course::get_data
     */
    public function test_get_data_real(): void {
        $cmstype = new cms_types();
        $cmstype->set('name', 'Test type');
        $cmstype->set('idnumber', 'test-course-ds');
        $cmstype->set('datasources', ['course']);
        $cmstype->save();

        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);
        $cms = new cms($moduleinfo->instance);

        $ds = new dscourse($cms);
        $data = $ds->get_data();

        $this->assertEquals(format_string($course->fullname), $data->fullname);
        $this->assertEquals(format_string($course->shortname), $data->shortname);
        $this->assertStringContainsString((string) $course->id, $data->courseurl);
        $this->assertTrue(property_exists($data, 'summary'));
        $this->assertTrue(property_exists($data, 'idnumber'));
        $this->assertTrue(property_exists($data, 'courseimage'));
        $this->assertTrue(property_exists($data, 'fields'));
        // No custom fields defined in test, fields should be an empty object.
        $this->assertInstanceOf(\stdClass::class, $data->fields);
        // No image uploaded in test, so courseimage should be empty string.
        $this->assertSame('', $data->courseimage);
    }

    /**
     * Tests that the instance cache key reflects the course's timemodified,
     * so it automatically invalidates when the course changes.
     *
     * @covers \mod_cms\local\datasource\course::get_instance_cache_key
     * @covers \mod_cms\local\datasource\course::get_full_cache_key
     */
    public function test_cache(): void {
        global $DB;

        $cmstype = new cms_types();
        $cmstype->set('name', 'Test type');
        $cmstype->set('idnumber', 'test-course-ds');
        $cmstype->set('datasources', ['course']);
        $cmstype->save();

        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);
        $cms = new cms($moduleinfo->instance);

        $ds = new dscourse($cms);

        $key1 = $ds->get_instance_cache_key();
        $this->assertEquals((string) $course->timemodified, $key1);

        // Simulate a course update by bumping timemodified.
        $DB->set_field('course', 'timemodified', $course->timemodified + 1, ['id' => $course->id]);

        $key2 = $ds->get_instance_cache_key();
        $this->assertNotEquals($key1, $key2);

        // Cached data should be retrievable.
        $this->assertEquals($ds->get_cached_data(), $ds->get_data());
    }
}
