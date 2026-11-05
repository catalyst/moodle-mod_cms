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

use mod_cms\external\update_instance_fields;
use mod_cms\local\datasource\fields as dsfields;
use mod_cms\local\model\cms;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_import1_trait.php');

/**
 * Tests for the update_instance_fields external function.
 *
 * @package   mod_cms
 * @author    Brendan Heywood <brendan@catalyst-au.net>
 * @copyright 2026, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_update_instance_fields_test extends \advanced_testcase {
    use test_import1_trait;

    /**
     * Tests that a field is updated, others are left alone, and the cache key changes.
     *
     * @covers \mod_cms\external\update_instance_fields::execute
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $cmstype = $this->import();
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);
        $oldkey = (new dsfields(new cms($moduleinfo->instance)))->get_full_cache_key();

        $result = update_instance_fields::execute($moduleinfo->coursemodule, [
            ['shortname' => 'afield', 'value' => 'Phil Smith,Bob Jones'],
        ]);
        $this->assertEquals(['updated' => ['afield']], $result);

        $data = $DB->get_records('customfield_data', ['instanceid' => $moduleinfo->instance]);
        $values = array_map(fn($d) => $d->charvalue, $data);
        $this->assertContains('Phil Smith,Bob Jones', $values);
        $this->assertNotEquals($oldkey, (new dsfields(new cms($moduleinfo->instance)))->get_full_cache_key());
    }

    /**
     * Tests that a textarea field, which is saved through an editor element, is updated.
     *
     * @covers \mod_cms\external\update_instance_fields::execute
     */
    public function test_execute_textarea(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_cms');
        $cmstype = $generator->create_cms_type(['datasources' => 'fields', 'title_mustache' => 'Notes']);
        $category = $generator->create_datasource_fields_category($cmstype);
        $generator->create_datasource_fields_field([
            'categoryid' => $category->get('id'),
            'shortname' => 'notes',
            'type' => 'textarea',
        ]);
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        update_instance_fields::execute($moduleinfo->coursemodule, [
            ['shortname' => 'notes', 'value' => '<ul><li>1:Phil</li><li>2:Bob</li></ul>'],
        ]);

        $data = $DB->get_records('customfield_data', ['instanceid' => $moduleinfo->instance]);
        $values = array_map(fn($d) => $d->value, $data);
        $this->assertContains('<ul><li>1:Phil</li><li>2:Bob</li></ul>', $values);
    }

    /**
     * Tests that users without the capability are refused.
     *
     * @covers \mod_cms\external\update_instance_fields::execute
     */
    public function test_execute_no_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cmstype = $this->import();
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        update_instance_fields::execute($moduleinfo->coursemodule, [['shortname' => 'afield', 'value' => 'x']]);
    }
}
