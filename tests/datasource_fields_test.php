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

use core_customfield\{category_controller, field_controller};
use mod_cms\customfield\cmsfield_handler;
use mod_cms\local\datasource\fields as dsfields;
use mod_cms\local\model\cms_types;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_import1_trait.php');

/**
 * Unit test for custom field datasource.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datasource_fields_test extends \advanced_testcase {
    use test_import1_trait;

    /** Test data for import/export. */
    const IMPORT_DATAFILE = __DIR__ . '/fixtures/fields_data.json';
    /** Test data for unsupported field. */
    const UNSUPPORTED_FIELD_DATAFILE = __DIR__ . '/fixtures/fields_data_unsupported.json';

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
     * @covers \mod_cms\local\datasource\fields::get_shortname
     */
    public function test_name() {
        $this->assertEquals('fields', dsfields::get_shortname());
    }

    /**
     * Tests import and export.
     *
     * @dataProvider import_dataprovider
     * @covers \mod_cms\local\datasource\fields::set_from_import
     * @covers \mod_cms\local\datasource\fields::get_for_export
     * @param string $importfile
     */
    public function test_import(string $importfile) {
        $importdata = json_decode(file_get_contents($importfile));
        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->save();
        $cms = $cmstype->get_sample_cms();

        $ds = new dsfields($cms);
        $ds->set_from_import($importdata);

        $this->check_database($importdata, $cmstype->get('id'));

        $exportdata = $ds->get_for_export();
        $this->assertEquals($importdata, $exportdata);
    }

    /**
     * Provider for test_import().
     *
     * @return \string[][]
     */
    public function import_dataprovider(): array {
        return [
            [ self::IMPORT_DATAFILE ],
        ];
    }

    /**
     * Tests importing data with an unsupported field type.
     *
     * @covers \mod_cms\local\datasource\fields::set_from_import
     */
    public function test_unsupported_field() {
        $importdata = json_decode(file_get_contents(self::UNSUPPORTED_FIELD_DATAFILE));

        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->save();
        $cms = $cmstype->get_sample_cms();

        $ds = new dsfields($cms);
        $ds->set_from_import($importdata);

        $this->check_database($importdata, $cmstype->get('id'));
    }

    /**
     * Check the database of the import data.
     *
     * @param object $importdata
     * @param int $itemid
     */
    public function check_database(object $importdata, int $itemid) {
        global $DB;

        foreach ($importdata->categories as $categorydata) {
            $categoryrecord = $DB->get_record(
                'customfield_category',
                ['name' => $categorydata->name, 'itemid' => $itemid]
            );
            $this->assertNotFalse($categoryrecord);
            foreach ($categorydata->fields as $fielddata) {
                $fieldrecord = $DB->get_record(
                    'customfield_field',
                    ['shortname' => $fielddata->shortname, 'categoryid' => $categoryrecord->id]
                );
                $this->assertNotFalse($fieldrecord);
                foreach ($fielddata as $index => $value) {
                    $this->assertEquals($value, $fieldrecord->$index);
                }
            }
        }
    }

    /**
     * Tests to see that changing the custom field configuration will alter the hash of the cms.
     *
     * @covers \mod_cms\local\model\cms::get_content_hash
     * @covers \mod_cms\local\model\cms_types::get_content_hash
     * @covers \mod_cms\local\datasource\fields::update_config_hash
     * @covers \mod_cms\customfield\cmsfield_handler::clear_configuration_cache
     */
    public function test_hash() {
        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->save();
        $oldhash = $cmstype->get_sample_cms()->get_content_hash();

        $importdata = json_decode(file_get_contents(self::IMPORT_DATAFILE));
        $ds = new dsfields($cmstype->get_sample_cms());
        $ds->set_from_import($importdata);
        $cmstype->read();
        $newhash = $cmstype->get_sample_cms()->get_content_hash();
        $this->assertNotEquals($oldhash, $newhash);
        $oldhash = $newhash;

        $cfhandler = cmsfield_handler::create($cmstype->get('id'));
        $catid = $cfhandler->create_category('x');
        $cc = category_controller::create($catid);

        $cmstype->read();
        $newhash = $cmstype->get_sample_cms()->get_content_hash();
        $this->assertNotEquals($oldhash, $newhash);
        $oldhash = $newhash;

        $cfhandler->rename_category($cc, 'y');

        $cmstype->read();
        $newhash = $cmstype->get_sample_cms()->get_content_hash();
        $this->assertNotEquals($oldhash, $newhash);
        $oldhash = $newhash;

        $fieldobj = (object) [
            'name' => 'Extra Field',
            'shortname' => 'extra_field',
            'type' => 'text',
            'description' => 'Extra Field',
        ];
        $field = field_controller::create(0, $fieldobj, $cc);
        $cfhandler->save_field_configuration($field, $fieldobj);

        $cmstype->read();
        $newhash = $cmstype->get_sample_cms()->get_content_hash();
        $this->assertNotEquals($oldhash, $newhash);
    }

    /**
     * Tests that field defs are removed when a cms type is deleted.
     *
     * @covers \mod_cms\local\datasource\fields::config_on_delete
     */
    public function test_config_delete() {
        global $DB;

        $cmstype = $this->import();
        // Test that stuff gets deleted even if not included in datasource list.
        $cmstype->set('datasources', []);
        $cmstype->save();

        $ids = $DB->get_records('customfield_category', ['component' => 'mod_cms', 'area' => 'cmsfield']);
        $this->assertEquals(1, count($ids));
        $catid = array_shift($ids)->id;

        $fields = $DB->get_records('customfield_field', ['categoryid' => $catid]);
        $this->assertNotEquals(0, count($fields));

        $manager = new manage_content_types();
        $manager->delete($cmstype->get('id'));

        $fields = $DB->get_records('customfield_field', ['categoryid' => $catid]);
        $this->assertEquals(0, count($fields));

        $ids = $DB->get_records('customfield_category', ['component' => 'mod_cms', 'area' => 'cmsfield']);
        $this->assertEquals(0, count($ids));
    }

    /**
     * Tests that field data is removed when a cms type is deleted.
     *
     * @covers \mod_cms\local\datasource\fields::instance_on_delete
     */
    public function test_instance_delete() {
        global $DB;

        $cmstype = $this->import();

        // Create a course and add a module to it.
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $fields = $DB->get_records('customfield_data', ['instanceid' => $moduleinfo->instance]);
        $this->assertNotEquals(0, count($fields));

        // Test that stuff gets deleted even if not included in datasource list.
        $cmstype->set('datasources', []);
        $cmstype->save();

        course_delete_module($moduleinfo->coursemodule);

        $fields = $DB->get_records('customfield_data', ['instanceid' => $moduleinfo->instance]);
        $this->assertEquals(0, count($fields));
    }
}
