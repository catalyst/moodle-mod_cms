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

use mod_cms\local\datasource\images as dsimages;
use mod_cms\local\model\cms_types;
use mod_cms\local\model\cms;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_import1_trait.php');

/**
 * Unit test for image datasource.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datasource_images_test extends \advanced_testcase {
    use test_import1_trait;

    /** Test data for import/export. */
    const IMPORT_DATAFILE = __DIR__ . '/fixtures/images_data.json';

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
     * @covers \mod_cms\local\datasource\images::get_shortname
     */
    public function test_name() {
        $this->assertEquals('images', dsimages::get_shortname());
    }

    /**
     * Tests get_key functions when the hash is not set.
     *
     * @covers \mod_cms\local\datasource\images::get_config_cache_key
     * @covers \mod_cms\local\datasource\images::get_instance_cache_key
     */
    public function test_no_hash() {
        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->set('idnumber', 'test-name');
        $cmstype->save();

        $cms = $cmstype->get_sample_cms();
        $cms->set('intro', '');
        $cms->set('course', 0);
        $cms->set('name', '');
        $cms->save();

        $ds = new dsimages($cms);
        $this->assertEquals('', $ds->get_instance_cache_key());
        $this->expectException('moodle_exception');
        $cmstype->set_custom_data('imagesconfighash', null);
        $cmstype->save();
        $ds->get_config_cache_key();
    }

    /**
     * Tests import and export.
     *
     * @covers \mod_cms\local\datasource\images::set_from_import
     * @covers \mod_cms\local\datasource\images::get_for_export
     */
    public function test_import() {
        $importdata = json_decode(file_get_contents(self::IMPORT_DATAFILE));
        $manager = new manage_content_types();
        $cmstype = $manager->create((object) [
            'name' => 'name',
            'idnumber' => 'test-name',
            'datasources' => 'images',
        ]);
        $cms = $cmstype->get_sample_cms();

        $ds = new dsimages($cms);
        $ds->set_from_import($importdata);
        $exportdata = $ds->get_for_export();
        $this->assertEquals($importdata, $exportdata);
    }

    /**
     * Tests that image info is removed when a cms type is deleted.
     *
     * @covers \mod_cms\local\datasource\images::config_on_delete
     */
    public function test_config_delete() {
        global $DB;

        $cmstype = $this->import();
        // Test that stuff gets deleted even if not included in datasource list.
        $cmstype->set('datasources', []);
        $cmstype->save();

        $count = $DB->count_records('files', ['itemid' => $cmstype->get('id'), 'filearea' => dsimages::FILE_AREA]);
        $this->assertNotEquals(0, $count);

        $manager = new manage_content_types();
        $manager->delete($cmstype->get('id'));

        $count = $DB->count_records('files', ['itemid' => $cmstype->get('id'), 'filearea' => dsimages::FILE_AREA]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test the cache for images.
     *
     * @covers \mod_cms\local\datasource\images::update_config_cache_key
     * @covers \mod_cms\local\datasource\images::get_config_cache_key
     * @covers \mod_cms\local\datasource\images::get_cached_data
     */
    public function test_cache() {
        $importdata = json_decode(file_get_contents(self::IMPORT_DATAFILE));
        $manager = new manage_content_types();
        $cmstype = $manager->create((object) [
            'name' => 'name',
            'idnumber' => 'test-name',
            'datasources' => 'images',
        ]);
        $cms = $cmstype->get_sample_cms();

        $ds = new dsimages($cms);

        // Instance cache key is constant.
        $this->assertEquals('', $ds->get_instance_cache_key());

        // Setting the config should update the cache key.
        $oldkey = $ds->get_config_cache_key();
        $ds->set_from_import($importdata);
        $newkey = $ds->get_config_cache_key();
        $this->assertNotEquals($oldkey, $newkey);

        $cache = \cache::make('mod_cms', 'cms_content_' . dsimages::get_shortname());
        // Nothing in cache yet.
        $this->assertFalse($cache->get($newkey));
        $data = $ds->get_cached_data();
        // Cache should now have data in it.
        $this->assertEquals($data, $cache->get($newkey));
    }

    /**
     * Test duplication (also tests backup and restore).
     *
     * @covers \mod_cms\local\datasource\images::instance_backup_define_structure
     * @covers \mod_cms\local\datasource\images::restore_define_structure
     */
    public function test_duplicate() {
        $cmstype = $this->import();
        $course = $this->create_course();
        $moduleinfo = $this->create_module($cmstype->get('id'), $course->id);

        $cm = get_coursemodule_from_id('', $moduleinfo->coursemodule, 0, false, MUST_EXIST);

        $cms = new cms($cm->instance);
        $ds = new dsimages($cms);
        $newcm = duplicate_module($course, $cm);
        $newcms = new cms($newcm->instance);
        $newds = new dsimages($newcms);

        $this->assertEquals($ds->get_data(), $newds->get_data());
    }
}
