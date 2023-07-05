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

/**
 * Unit test for image datasource.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datasource_images_test extends \advanced_testcase {
    /** Test data for import/export. */
    const IMPORT_DATAFILE = __DIR__ . '/fixtures/images_data.json';

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
     * @covers \mod_cms\local\datasource\images::get_shortname
     */
    public function test_name() {
        $this->assertEquals('images', dsimages::get_shortname());
    }

    /**
     * Tests import and export.
     *
     * @covers \mod_cms\local\datasource\fields::set_from_import
     * @covers \mod_cms\local\datasource\fields::get_for_export
     */
    public function test_import() {
        $importdata = json_decode(file_get_contents(self::IMPORT_DATAFILE));
        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->save();
        $cms = $cmstype->get_sample_cms();

        $ds = new dsimages($cms);
        $ds->set_from_import($importdata);
        $exportdata = $ds->get_for_export();
        $this->assertEquals($importdata, $exportdata);
    }
}