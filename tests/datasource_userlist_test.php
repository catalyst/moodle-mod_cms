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

use mod_cms\local\datasource\userlist as dsuserlist;
use mod_cms\local\model\{cms_types, cms_userlist, cms_userlist_columns};

/**
 * Unit tests for userlist datasource.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datasource_userlist_test extends \advanced_testcase {
    use test_import1_trait;

    /** Test data for import/export. */
    const IMPORT_JSONFILE = __DIR__ . '/fixtures/userlist_data.json';

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
     * @covers \mod_cms\local\datasource\userlist::get_shortname
     */
    public function test_name() {
        $this->assertEquals('list', dsuserlist::get_shortname());
    }

    /**
     * Tests import and export.
     *
     * @covers \mod_cms\local\datasource\userlist::set_from_import
     * @covers \mod_cms\local\datasource\userlist::get_for_export
     */
    public function test_import() {
        global $DB;

        $importdata = json_decode(file_get_contents(self::IMPORT_JSONFILE));
        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->save();
        $cms = $cmstype->get_sample_cms();

        $ds = new dsuserlist($cms);
        $ds->set_from_import($importdata);

        // Check the database directly.
        $this->check_database($importdata, $cmstype->get('id'));

        // Check that exporting produces the same content as was imported.
        $exportdata = $ds->get_for_export();
        $this->assertEquals($importdata, $exportdata);
    }

    /**
     * Check the database of the import data.
     *
     * @param object $importdata
     * @param int $itemid
     */
    public function check_database(object $importdata, int $itemid) {
        global $DB;

        $categoryrecord = $DB->get_records(
            'customfield_category',
            ['itemid' => $itemid]
        );
        $this->assertEquals(1, count($categoryrecord));
        $categoryrecord = array_shift($categoryrecord);

        foreach ($importdata->columns as $column) {
            $fieldrecord = $DB->get_record(
                'customfield_field',
                ['shortname' => $column->shortname, 'categoryid' => $categoryrecord->id]
            );
            $this->assertNotFalse($fieldrecord);
            foreach ($column as $index => $value) {
                $this->assertEquals($value, $fieldrecord->$index);
            }
        }
    }

    /**
     * Tests that column defs are removed when a cms type is deleted.
     *
     * @covers \mod_cms\local\datasource\userlist::config_on_delete
     */
    public function test_config_delete() {
        global $DB;

        $cmstype = $this->import();
        // Test that stuff gets deleted even if not included in datasource list.
        $cmstype->set('datasources', []);
        $cmstype->save();

        $ids = $DB->get_records('customfield_category', ['component' => 'mod_cms', 'area' => 'cmsuserlist']);
        $this->assertEquals(1, count($ids));
        $catid = array_shift($ids)->id;

        $fields = $DB->get_records('customfield_field', ['categoryid' => $catid]);
        $this->assertNotEquals(0, count($fields));

        $manager = new manage_content_types();
        $manager->delete($cmstype->get('id'));

        $fields = $DB->get_records('customfield_field', ['categoryid' => $catid]);
        $this->assertEquals(0, count($fields));

        $ids = $DB->get_records('customfield_category', ['component' => 'mod_cms', 'area' => 'cmsuserlist']);
        $this->assertEquals(0, count($ids));
    }

    /**
     * Tests that field data is removed when a cms type is deleted.
     *
     * @covers \mod_cms\local\datasource\userlist::instance_on_delete
     */
    public function test_instance_delete() {
        global $DB;

        $cmstype = $this->import();

        // Create a course and add a module to it.
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $list = $DB->get_records(cms_userlist::TABLE, ['cmsid' => $moduleinfo->instance]);
        $this->assertNotEquals(0, count($list));

        // Test that stuff gets deleted even if not included in datasource list.
        $cmstype->set('datasources', []);
        $cmstype->save();

        course_delete_module($moduleinfo->coursemodule);

        $list = $DB->get_records(cms_userlist::TABLE, ['cmsid' => $moduleinfo->instance]);
        $this->assertEquals(0, count($list));
    }
}
