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

use mod_cms\local\datasource\site as dssite;
use mod_cms\local\model\{cms, cms_types};

/**
 * Unit test for image datasource.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datasource_site_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests short name.
     *
     * @covers \mod_cms\local\datasource\site::get_shortname
     */
    public function test_name() {
        $this->assertEquals('site', dssite::get_shortname());
    }

    /**
     * Test the add_to_data() function.
     *
     * @covers \mod_cms\local\datasource\site::get_data
     */
    public function test_get_data() {
        $cmstype = new cms_types();
        $cmstype->set('name', 'somename');
        $cmstype->save();

        $cms = new cms();
        $cms->set('typeid', $cmstype->get('id'));

        $ds = new dssite($cms);
        $data = $ds->get_data();

        $this->assertObjectHasAttribute('fullname', $data);
        $this->assertObjectHasAttribute('shortname', $data);
        $this->assertObjectHasAttribute('wwwroot', $data);
    }
}
