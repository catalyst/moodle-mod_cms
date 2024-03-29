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
use mod_cms\customfield\cmsuserlist_handler;
use mod_cms\local\datasource\userlist as dsuserlist;
use mod_cms\local\model\{cms, cms_types};
use mod_cms\manage_content_types;
use mod_cms_generator;

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
     * Get generator for mod_cms.
     * @return mod_cms_generator
     */
    protected function get_generator(): mod_cms_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_cms');
    }

    /**
     * Tests short name.
     *
     * @covers \mod_cms\local\datasource\userlist::get_shortname
     */
    public function test_name() {
        $this->assertEquals('userlist', dsuserlist::get_shortname());
    }

    /**
     * Tests get_key functions when the hash is not set.
     *
     * @covers \mod_cms\local\datasource\userlist::get_config_cache_key
     * @covers \mod_cms\local\datasource\userlist::get_instance_cache_key
     */
    public function test_no_hash() {
        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->set('idnumber', 'test-name');
        $cmstype->set('datasources', ['userlist']);
        $cmstype->save();

        $cms = $cmstype->get_sample_cms();
        $cms->set('intro', '');
        $cms->set('course', 0);
        $cms->set('name', '');
        $cms->save();

        $ds = new dsuserlist($cms);
        // A cache key should be generated if one does not already exist.
        $this->assertNotEmpty($ds->get_full_cache_key());
        $this->assertDebuggingCalledCount(2);
    }

    /**
     * Tests import and export.
     *
     * @covers \mod_cms\local\datasource\userlist::set_from_import
     * @covers \mod_cms\local\datasource\userlist::get_for_export
     */
    public function test_import() {
        $importdata = json_decode(file_get_contents(self::IMPORT_JSONFILE));

        $manager = new manage_content_types();
        $cmstype = $manager->create((object) [
            'name' => 'name',
            'idnumber' => 'test-name',
            'datasources' => 'userlist',
        ]);

        $cms = $cmstype->get_sample_cms();

        $ds = new dsuserlist($cms);
        $oldkey = $ds->get_config_cache_key();
        $ds->set_from_import($importdata);

        // Check the database directly.
        $this->check_database($importdata, $cmstype->get('id'));

        // Check that exporting produces the same content as was imported.
        $exportdata = $ds->get_for_export();
        $this->assertEquals($importdata, $exportdata);

        // Check caching.
        $newkey = $ds->get_config_cache_key();
        $this->assertNotEquals($oldkey, $newkey);
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
            ['itemid' => $itemid, 'component' => 'mod_cms', 'area' => 'cmsuserlist']
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

        $manager = new manage_content_types();
        $cmstype = $manager->create((object) ['name' => 'Name', 'idnumber' => 'test-name', 'datasources' => 'userlist']);

        // Test that stuff gets deleted even if not included in datasource list.
        $cmstype->set('datasources', []);
        $cmstype->save();

        $ids = $DB->get_records('customfield_category', ['component' => 'mod_cms', 'area' => 'cmsuserlist']);
        $this->assertEquals(1, count($ids));
        $catid = array_shift($ids)->id;

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

        $catid = $DB->get_field('customfield_category', 'id', ['component' => 'mod_cms', 'area' => 'cmsuserlist']);
        $fields = $DB->get_records_menu('customfield_field', ['categoryid' => $catid], '', 'id, shortname');
        $fields = array_keys($fields);
        [$fieldssql, $params] = $DB->get_in_or_equal($fields);
        $num = $DB->count_records_select('customfield_data', 'fieldid ' . $fieldssql, $params);
        $this->assertNotEquals(0, $num);

        // Test that stuff gets deleted even if not included in datasource list.
        $cmstype->set('datasources', []);
        $cmstype->save();

        course_delete_module($moduleinfo->coursemodule);

        $num = $DB->count_records_select('customfield_data', 'fieldid ' . $fieldssql, $params);
        $this->assertEquals(0, $num);
    }

    /**
     * Tests userlist::instance_form_validation
     *
     * @covers \mod_cms\local\datasource\userlist::instance_form_validation
     */
    public function test_instance_form_validataion() {
        $cmstype = $this->import();
        $cmstype->save();

        $cms = new cms();
        $cms->set('name', 'Name');
        $cms->set('typeid', $cmstype->get('id'));
        $cms->set('intro', 'x');
        $cms->save();

        $ul = new dsuserlist($cms);

        $formdata = [
            'userlist_name' => ['John', 'Andy'],
            'userlist_age' => [12, 13],
            'userlist_fav_hobby' => ['Inventing words', 'Eating rocks'],
            'userlist_option_repeats' => 2,
        ];

        $errors = $ul->instance_form_validation($formdata, []);
        $this->assertCount(0, $errors);
    }

    /**
     * Tests userlist::update_instance().
     *
     * @covers \mod_cms\local\datasource\userlist::update_instance
     */
    public function test_update_instance() {
        $cmstype = $this->import();
        $cmstype->save();

        $cms = new cms();
        $cms->set('name', 'Name');
        $cms->set('typeid', $cmstype->get('id'));
        $cms->set('intro', 'x');
        $cms->save();

        $ul = new dsuserlist($cms);

        $formdata = [
            'userlist_name' => ['John', 'Andy'],
            'userlist_age' => [12, 13],
            'userlist_fav_hobby' => ['Inventing words', 'Eating rocks'],
            'userlist_option_repeats' => 2,
        ];

        // Test creating.
        $ul->update_instance((object) $formdata, true);
        $this->check_instance_data($cms, $formdata);

        // Test updating.
        $formdata['userlist_name'][1] = 'Lewis';
        $formdata['userlist_age'][0] = 11;

        $ul->update_instance((object) $formdata, false);
        $this->check_instance_data($cms, $formdata);

        // Test adding.
        $formdata['userlist_name'][2] = 'Jane';
        $formdata['userlist_age'][2] = 16;
        $formdata['userlist_fav_hobby'][2] = 'Capacitor collecting';
        $formdata['userlist_option_repeats'] = 3;

        $ul->update_instance((object) $formdata, false);
        $this->check_instance_data($cms, $formdata);

        // Test removing.
        $formdata['delete-hidden'] = [1 => 1];
        $ul->update_instance((object) $formdata, false);
        // Must perform a bit of voodoo before checking.
        unset($formdata['userlist_name'][1]);
        $formdata['userlist_name'] = array_values($formdata['userlist_name']);
        unset($formdata['userlist_age'][1]);
        $formdata['userlist_age'] = array_values($formdata['userlist_age']);
        unset($formdata['userlist_fav_hobby'][1]);
        $formdata['userlist_fav_hobby'] = array_values($formdata['userlist_fav_hobby']);
        $formdata['userlist_option_repeats'] = 2;
        $this->check_instance_data($cms, $formdata);
    }

    /**
     * Performs tests to check data integrity.
     *
     * @param cms $cms
     * @param array $formdata
     */
    public function check_instance_data(cms $cms, array $formdata) {
        global $DB;

        $instanceids = $cms->get_custom_data('userlistinstanceids');
        $this->assertCount($formdata['userlist_option_repeats'], (array) $instanceids);

        $cfhandler = cmsuserlist_handler::create($cms->get('typeid'));
        foreach ($instanceids as $rownum => $id) {
            $row = $cfhandler->get_instance_data($id, true);
            foreach ($row as $d) {
                $ename = $d->get_form_element_name();
                $ename = str_replace(dsuserlist::CUSTOMFIELD_PREFIX, dsuserlist::FORM_PREFIX, $ename);
                $this->assertEquals($formdata[$ename][$rownum], $d->get_value());
            }
        }

        // A quick double check.
        $catid = $DB->get_field('customfield_category', 'id', ['component' => 'mod_cms', 'area' => 'cmsuserlist']);
        $fields = $DB->get_records_menu('customfield_field', ['categoryid' => $catid], '', 'id, shortname');
        $fields = array_keys($fields);
        [$fieldssql, $params] = $DB->get_in_or_equal($fields);
        $num = $DB->count_records_select('customfield_data', 'fieldid ' . $fieldssql, $params);
        $this->assertEquals($formdata['userlist_option_repeats'] * 3, $num);
    }

    /**
     * Tests userlist::get_data().
     *
     * @covers \mod_cms\local\datasource\userlist::get_data
     */
    public function test_get_data() {
        $expected = [
            (object) [
                'name' => 'John',
                'age' => '23',
                'fav_hobby' => 'Poodle juggling',
            ],
            (object) [
                'name' => 'Jane',
                'age' => '21',
                'fav_hobby' => 'Water sculpting',
            ],
        ];

        $cmstype = $this->import();

        // Create a course and add a module to it.
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $cms = new cms($moduleinfo->instance);
        $userlist = new dsuserlist($cms);
        $data = $userlist->get_cached_data();
        $this->assertEquals(2, $data->numrows);
        $this->assertEquals($expected, $data->data);

        // Check cache.
        $cache = \cache::make('mod_cms', 'cms_content_userlist');
        $key = $userlist->get_full_cache_key();
        $this->assertEquals($data, $cache->get($key));
        $this->assertEquals($data, $userlist->get_data());
    }

    /**
     * Test duplication (also tests backup and restore).
     *
     * @covers \mod_cms\local\datasource\userlist::instance_backup_define_structure
     * @covers \mod_cms\local\datasource\userlist::restore_define_structure
     */
    public function test_duplicate() {
        $cmstype = $this->import();
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $cm = get_coursemodule_from_id('', $moduleinfo->coursemodule, 0, false, MUST_EXIST);

        $cms = new cms($cm->instance);
        $ds = new dsuserlist($cms);
        $newcm = duplicate_module($course, $cm);
        $newcms = new cms($newcm->instance);
        $newds = new dsuserlist($newcms);

        $this->assertEquals($ds->get_data(), $newds->get_data());

        // Assert that the CMS type is not duplicated.
        $this->assertEquals($cms->get('typeid'), $newcms->get('typeid'));
    }

    /**
     * Tests backup and restore of embedded files in textarea fields.
     *
     * @covers \mod_cms\local\datasource\userlist::instance_backup_define_structure
     * @covers \mod_cms\local\datasource\userlsit::restore_define_structure
     */
    public function test_file_backup_and_restore() {
        if (!method_exists('\core_customfield\handler', 'backup_define_structure')) {
            $this->markTestSkipped('Only test if backup and restore is supported for embedded files.');
        }

        $file1name = 'anotherfilename.txt';
        $file2name = 'differentfilename.txt';

        $course = $this->getDataGenerator()->create_course();
        $cmstype = $this->get_generator()->create_cms_type(['datasources' => 'userlist']);
        $cffield = $this->get_generator()->create_datasource_userlist_field($cmstype, [
            'shortname' => 'fielda',
            'type' => 'textarea'
        ]);

        $fs = get_file_storage();

        $file1id = $this->get_generator()->make_file($file1name, 'More content');
        $file2id = $this->get_generator()->make_file($file2name, 'Different content');

        // Create data for making a module. Add the files to the custom fields.
        $instancedata = [
            'modulename' => 'cms',
            'course' => $course->id,
            'section' => 0,
            'visible' => true,
            'typeid' => $cmstype->get('id'),
            'name' => 'Some module',
            'userlist_fielda_editor' => [[
                'text' => 'Here is a file: @@PLUGINFILE@@/'.$file1name,
                'format' => FORMAT_HTML,
                'itemid' => $file1id,
            ], [
                'text' => 'Here is another file: @@PLUGINFILE@@/'.$file1name,
                'format' => FORMAT_HTML,
                'itemid' => $file2id,
            ]],
            dsuserlist::FORM_REPEATHIDDENNAME => 2,
        ];

        $module = create_module((object) $instancedata);
        $cm = get_coursemodule_from_id('', $module->coursemodule, 0, false, MUST_EXIST);
        $cms = new cms($cm->instance);
        $dsuserlist = new dsuserlist($cms);
        $context = context_module::instance($cm->id);

        // Get the data ID to find the file with.
        $cfhandler = cmsuserlist_handler::create($cmstype->get('id'));
        $d1 = $cfhandler->get_instance_data($dsuserlist->get_instance_id(0));
        $d2 = $cfhandler->get_instance_data($dsuserlist->get_instance_id(1));
        $item1id = $d1[$cffield->get('id')]->get('id');
        $item2id = $d2[$cffield->get('id')]->get('id');

        // Check if the permanent file exists.
        $file1 = $fs->get_file($context->id, 'customfield_textarea', 'value', $item1id, '/', $file1name);
        $this->assertNotEmpty($file1);
        $file2 = $fs->get_file($context->id, 'customfield_textarea', 'value', $item2id, '/', $file2name);
        $this->assertNotEmpty($file2);

        // Check that they are different.
        $this->assertNotEquals($file1->get_content(), $file2->get_content());

        // Duplicate the module (which is done via backup and restore).
        $newcm = duplicate_module($course, $cm);
        $newcms = new cms($newcm->instance);
        $newdsuserlist = new dsuserlist($newcms);
        $newcontext = context_module::instance($newcm->id);

        // Get the data ID to find the new file with.
        $d1 = $cfhandler->get_instance_data($newdsuserlist->get_instance_id(0));
        $d2 = $cfhandler->get_instance_data($newdsuserlist->get_instance_id(1));
        $item1id = $d1[$cffield->get('id')]->get('id');
        $item2id = $d2[$cffield->get('id')]->get('id');

        // Check if the permanent file exists.
        $newfile1 = $fs->get_file($newcontext->id, 'customfield_textarea', 'value', $item1id, '/', $file1name);
        $this->assertNotEmpty($newfile1);
        $newfile2 = $fs->get_file($newcontext->id, 'customfield_textarea', 'value', $item2id, '/', $file2name);
        $this->assertNotEmpty($newfile2);

        // Check that the files are distinct.
        $this->assertNotEquals($file1->get_id(), $newfile1->get_id());
        $this->assertNotEquals($file2->get_id(), $newfile2->get_id());

        // Check the files have the same content.
        $this->assertEquals($file1->get_content(), $newfile1->get_content());
        $this->assertEquals($file2->get_content(), $newfile2->get_content());

        // Check that they are different.
        $this->assertNotEquals($newfile1->get_content(), $newfile2->get_content());
    }
}
