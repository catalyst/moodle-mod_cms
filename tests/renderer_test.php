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
use mod_cms\local\datasource\images as dsimages;
use mod_cms\local\model\{cms, cms_types};
use mod_cms\local\renderer;
use mod_cms\null_datasource as dsnull;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/null_datasource.php');

/**
 * Unit test for renderer
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the renderer::get_data() function.
     *
     * @covers \mod_cms\local\renderer::get_data
     */
    public function test_get_data() {
        $manager = new manage_content_types();
        $cmstype = $manager->create((object) [
            'name' => 'somename',
            'idnumber' => 'test-somename',
            'datasources' => 'fields,images,roles,userlist',
        ]);

        $cms = $cmstype->get_sample_cms();

        $renderer = new renderer($cms);
        $data = $renderer->get_data();

        $this->assertIsObject($data);

        // Test the existence of objects for built in datasources.
        foreach (dsbase::BUILTIN_DATASOURCES as $ds) {
            $classname = 'mod_cms\\local\\datasource\\' . $ds;
            $attribute = $classname::get_shortname();
            $this->assertObjectHasAttribute($attribute, $data);
            $this->assertIsObject($data->$attribute);
        }
    }

    /**
     * Test the renderer::get_html() function.
     *
     * @covers \mod_cms\local\renderer::get_html
     */
    public function test_get_html() {
        global $SITE;

        $template = '<p>{{site.fullname}}</p>';
        $expected = '<p>' . $SITE->fullname . '</p>';

        $manager = new manage_content_types();
        $cmstype = $manager->create((object) [
            'name' => 'somename',
            'idnumber' => 'test-somename',
            'mustache' => $template,
            'datasources' => '',
        ]);
        $cms = $cmstype->get_sample_cms();

        $cache = \cache::make('mod_cms', 'cms_content');

        $renderer = new renderer($cms);
        $key = $renderer->get_cache_key();
        $this->assertNotNull($key);
        $html = $renderer->get_html();

        $this->assertEquals($expected, $html);
        $this->assertEquals($expected, $cache->get($key));
    }

    /**
     * Tests null keys.
     * Tests that a datasource with a null key with casue the overall HTML to no be cached, but not interfere with the caching
     * of other datasources.
     *
     * @covers \mod_cms\local\renderer::get_html
     */
    public function test_null_key() {
        global $SITE;

        $labels = dsbase::get_datasource_labels(false);
        if (!array_key_exists(dsnull::get_shortname(), $labels)) {
            dsbase::add_datasource_class('\mod_cms\null_datasource');
        }

        $template = '<p>Joy to {{site.fullname}}</p>';
        $expected = '<p>Joy to ' . $SITE->fullname . '</p>';

        $manager = new manage_content_types();
        $cmstype = $manager->create((object) [
            'name' => 'somename',
            'idnumber' => 'test-somename',
            'mustache' => $template,
            'datasources' => 'images,null_datasource',
        ]);

        $cms = $cmstype->get_sample_cms();

        $cache = \cache::make('mod_cms', 'cms_content');

        $renderer = new renderer($cms);
        $key = $renderer->get_cache_key();
        $this->assertNull($key);
        $html = $renderer->get_html();

        // Check that the HMTL was correctly generated, but not cached.
        $this->assertEquals($expected, $html);
        $this->assertFalse($cache->get($key));

        // Check that the other datasource(s) was still cached.
        $ds = new dsimages($cms);
        $dskey = $ds->get_full_cache_key();
        $dscache = \cache::make('mod_cms', 'cms_content_images');
        $this->assertNotFalse($dscache->get($dskey));
    }
}
