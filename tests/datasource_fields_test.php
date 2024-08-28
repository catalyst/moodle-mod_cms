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

use context_module;
use core_customfield\{category_controller, field_controller};
use mod_cms\customfield\cmsfield_handler;
use mod_cms\local\datasource\fields as dsfields;
use mod_cms\local\model\cms;
use mod_cms\local\model\cms_types;
use mod_cms_generator;

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
     * Get generator for mod_cms.
     * @return mod_cms_generator
     */
    protected function get_generator(): mod_cms_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_cms');
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
     * Tests get_key functions when the hash is not set.
     *
     * @covers \mod_cms\local\datasource\fields::get_config_cache_key
     * @covers \mod_cms\local\datasource\fields::get_instance_cache_key
     */
    public function test_no_hash() {
        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->set('idnumber', 'test-name');
        $cmstype->set('datasources', ['fields']);
        $cmstype->save();

        $cms = $cmstype->get_sample_cms();
        $cms->set('intro', '');
        $cms->set('course', 0);
        $cms->set('name', '');
        $cms->save();

        $ds = new dsfields($cms);
        // A cache key should be generated if one does not already exist.
        $this->assertNotEmpty($ds->get_full_cache_key());
        $this->assertDebuggingCalledCount(2);
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
        $cmstype->set('idnumber', 'test-name');
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
        $cmstype->set('idnumber', 'test-name');
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
     * @covers \mod_cms\local\datasource\fields::get_config_cache_key
     * @covers \mod_cms\local\datasource\fields::update_config_cache_key
     * @covers \mod_cms\customfield\cmsfield_handler::clear_configuration_cache
     */
    public function test_config_cache_key() {

        $manager = new manage_content_types();
        $cmstype = $manager->create((object) [
            'name' => 'name',
            'idnumber' => 'test-name',
            'datasources' => 'fields',
        ]);
        $cms = $cmstype->get_sample_cms();

        $ds = new dsfields($cms);

        $oldkey = $ds->get_config_cache_key();

        $importdata = json_decode(file_get_contents(self::IMPORT_DATAFILE));
        $ds->set_from_import($importdata);
        $cmstype->read();
        $newkey = $ds->get_config_cache_key();
        $this->assertNotEquals($oldkey, $newkey);
        $oldkey = $newkey;

        $cfhandler = cmsfield_handler::create($cmstype->get('id'));
        $catid = $cfhandler->create_category('x');
        $cc = category_controller::create($catid);

        $cmstype->read();
        $newkey = $ds->get_config_cache_key();
        $this->assertNotEquals($oldkey, $newkey);
        $oldkey = $newkey;

        $cfhandler->rename_category($cc, 'y');

        $cmstype->read();
        $newkey = $ds->get_config_cache_key();
        $this->assertNotEquals($oldkey, $newkey);
        $oldkey = $newkey;

        $fieldobj = (object) [
            'name' => 'Extra Field',
            'shortname' => 'extra_field',
            'type' => 'text',
            'description' => 'Extra Field',
        ];
        $field = field_controller::create(0, $fieldobj, $cc);
        $cfhandler->save_field_configuration($field, $fieldobj);

        $cmstype->read();
        $newkey = $ds->get_config_cache_key();
        $this->assertNotEquals($oldkey, $newkey);
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

    /**
     * Test caching.
     *
     * @covers \mod_cms\local\datasource\fields::get_cached_data
     * @covers \mod_cms\local\datasource\fields::get_full_cache_key
     * @covers \mod_cms\local\datasource\fields::update_instance
     */
    public function  test_cache() {
        $cmstype = $this->import();
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $cms = new cms($moduleinfo->instance);
        $ds = new dsfields($cms);
        $oldkey = $ds->get_full_cache_key();
        $this->assertNotNull($oldkey);
        $ds->update_instance((object) ['id' => $moduleinfo->instance, 'customfield_afield' => 'Not field A'], false);
        $newkey = $ds->get_full_cache_key();
        $this->assertNotEquals($oldkey, $newkey);

        $cache = \cache::make('mod_cms', 'cms_content_fields');

        $data = $ds->get_cached_data();
        $this->assertEquals($data, $cache->get($newkey));
        $this->assertEquals($data, $ds->get_data());
    }

    /**
     * Test duplication (also tests backup and restore).
     *
     * @covers \mod_cms\local\datasource\fields::instance_backup_define_structure
     * @covers \mod_cms\local\datasource\fields::restore_define_structure
     */
    public function test_duplicate() {
        $cmstype = $this->import();
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $cm = get_coursemodule_from_id('', $moduleinfo->coursemodule, 0, false, MUST_EXIST);

        $cms = new cms($cm->instance);
        $ds = new dsfields($cms);
        $newcm = duplicate_module($course, $cm);
        $newcms = new cms($newcm->instance);
        $newds = new dsfields($newcms);

        $this->assertEquals($ds->get_data(), $newds->get_data());

        // Assert that the CMS type is not duplicated.
        $this->assertEquals($cms->get('typeid'), $newcms->get('typeid'));
    }

    /**
     * Tests backup and restore of embedded files in textarea fields.
     *
     * @covers \mod_cms\local\datasource\fields::instance_backup_define_structure
     * @covers \mod_cms\local\datasource\fields::restore_define_structure
     */
    public function test_file_backup_and_restore() {
        if (!method_exists('\core_customfield\handler', 'backup_define_structure')) {
            $this->markTestSkipped('Only test if backup and restore is supported for embedded files.');
        }

        $filename = 'somefilename.txt';

        $course = $this->getDataGenerator()->create_course();
        $cmstype = $this->get_generator()->create_cms_type(['datasources' => 'fields']);
        $category = $this->get_generator()->create_datasource_fields_category($cmstype);
        $cffield = $this->get_generator()->create_datasource_fields_field([
            'categoryid' => $category->get('id'),
            'shortname' => 'field1',
            'type' => 'textarea'
        ]);

        $fs = get_file_storage();

        $fileid = $this->get_generator()->make_file($filename, 'Some content');

        // File from other place in the server.
        $syscontext = \context_system::instance();
        $filedata = [
            'contextid' => $syscontext->id,
            'component' => 'course',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/textfiles/',
            'filename'  => 'testtext.txt',
        ];
        $fs->create_file_from_string($filedata, 'text contents');
        $url = \moodle_url::make_pluginfile_url($filedata['contextid'], $filedata['component'], $filedata['filearea'],
            $filedata['itemid'], $filedata['filepath'], $filedata['filename']);

        // Create data for making a module. Add the files to the custom field.
        $instancedata = [
            'modulename' => 'cms',
            'course' => $course->id,
            'section' => 0,
            'visible' => true,
            'typeid' => $cmstype->get('id'),
            'name' => 'Some module',
            'customfield_field1_editor' => [
                'text' => 'Here is a file: @@PLUGINFILE@@/' . $filename . ' AND ' . $url->out(),
                'format' => FORMAT_HTML,
                'itemid' => $fileid,
            ]
        ];

        $module = create_module((object) $instancedata);
        $cm = get_coursemodule_from_id('', $module->coursemodule, 0, false, MUST_EXIST);
        $cms = new cms($cm->instance);
        $context = context_module::instance($cm->id);

        // Get the data ID to find the file with.
        $cfhandler = cmsfield_handler::create($cmstype->get('id'));
        $instancedata = $cfhandler->get_instance_data($cms->get('id'));
        $fielddata = $instancedata[$cffield->get('id')];
        $itemid = $fielddata->get('id');
        $originaltext = $fielddata->get('value');
        $originalexportvalue = $fielddata->export_value();

        // Check if the permanent file exists.
        $file = $fs->get_file($context->id, 'customfield_textarea', 'value', $itemid, '/', $filename);
        $this->assertNotEmpty($file);

        // Duplicate the module (which is done via backup and restore).
        $newcm = duplicate_module($course, $cm);
        $newcms = new cms($newcm->instance);
        $newcontext = context_module::instance($newcm->id);

        // Get the data ID to find the new file with.
        $newinstancedata = $cfhandler->get_instance_data($newcms->get('id'));
        $newfielddata = $newinstancedata[$cffield->get('id')];
        $newitemid = $newfielddata->get('id');
        $newtext = $newfielddata->get('value');
        $newexportvalue = $newfielddata->export_value();

        // Check if the permanent file exists.
        $newfile = $fs->get_file($newcontext->id, 'customfield_textarea', 'value', $newitemid, '/', $filename);
        $this->assertNotEmpty($newfile);

        // Check that the files are distinct.
        $this->assertNotEquals($file->get_id(), $newfile->get_id());

        // Check the files have the same content.
        $this->assertEquals($file->get_content(), $newfile->get_content());

        // Value should be same but export value should have different URL.
        $this->assertEquals($originaltext, $newtext);
        $this->assertNotEquals($originalexportvalue, $newexportvalue);

        // Check the URL is using correct ids.
        $this->assertStringContainsString(
            '/' . $context->id . '/customfield_textarea/value/' . $itemid . '/' . $filename,
            $originalexportvalue);
        $this->assertStringContainsString(
            '/' . $newcontext->id . '/customfield_textarea/value/' . $newitemid . '/' . $filename,
            $newexportvalue);

        // Check URL is correctly restored.
        $this->assertStringContainsString($url->out(), $originalexportvalue);
        $this->assertStringContainsString($url->out(), $newexportvalue);
    }
}
