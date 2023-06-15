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

use advanced_testcase;
use core_course\local\entity\content_item;
use core_course\local\entity\lang_string_title;
use mod_cms\local\lib;
use mod_cms\local\model\cms_types;
use mod_cms\local\model\cms;
use mod_cms\local\renderer;
use moodle_url;

/**
 * Unit test for renderer
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer_test extends advanced_testcase {
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
        $cmstype = new cms_types();
        $cmstype->set('name', 'somename');
        $cmstype->save();

        $cms = new cms();
        $cms->set('typeid', $cmstype->get('id'));

        $renderer = new renderer($cms);
        $data = $renderer->get_data();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('site', $data);
        $this->assertIsArray($data['site']);
        $this->assertArrayHasKey('fullname', $data['site']);
        $this->assertArrayHasKey('shortname', $data['site']);
        $this->assertArrayHasKey('wwwroot', $data['site']);
    }

    /**
     * Test the renderer::get_data_as_table() function.
     *
     * @covers \mod_cms\local\renderer::get_data_as_table
     */
    public function test_get_data_as_table() {
        global $SITE, $CFG;

        $cmstype = new cms_types();
        $cmstype->set('name', 'somename');
        $cmstype->save();

        $cms = $cmstype->get_sample_cms();
        $renderer = new renderer($cms);
        $table = $renderer->get_data_as_table(true);

        $this->assertInstanceOf(\html_table::class, $table);

        $this->assertEquals('{{name}}', $table->data[0]->cells[0]->text);
        $this->assertEquals('Some name', $table->data[0]->cells[1]->text);

        $this->assertEquals('{{site.fullname}}', $table->data[1]->cells[0]->text);
        $this->assertEquals($SITE->fullname, $table->data[1]->cells[1]->text);

        $this->assertEquals('{{site.shortname}}', $table->data[2]->cells[0]->text);
        $this->assertEquals($SITE->shortname, $table->data[2]->cells[1]->text);

        $this->assertEquals('{{site.wwwroot}}', $table->data[3]->cells[0]->text);
        $this->assertEquals($CFG->wwwroot, $table->data[3]->cells[1]->text);
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

        $cmstype = new cms_types();
        $cmstype->set('name', 'somename');
        $cmstype->set('mustache', $template);
        $cmstype->save();

        $cms = $cmstype->get_sample_cms();

        $renderer = new renderer($cms);
        $html = $renderer->get_html(true);

        $this->assertEquals($expected, $html);
    }
}
