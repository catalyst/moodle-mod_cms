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
 * Unit test for nullcache trait.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nullcache_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the null cache key functions
     *
     * @covers \mod_cms\local\datasource\traits\nullcache::get_config_cache_key
     * @covers \mod_cms\local\datasource\traits\nullcache::get_instance_cache_key
     * @covers \mod_cms\local\datasource\traits\nullcache::get_full_cache_key
     */
    public function test_nullcache() {
        $labels = dsbase::get_datasource_labels(false);
        if (!array_key_exists(dsnull::get_shortname(), $labels)) {
            dsbase::add_datasource_class('\mod_cms\null_datasource');
        }

        $template = '<p>Joy to {{site.fullname}}</p>';

        $cmstype = new cms_types();
        $cmstype->set('name', 'somename');
        $cmstype->set('idnumber', 'test-somename');
        $cmstype->set('mustache', $template);
        $cmstype->set('datasources', ['images', 'null_datasource']);
        $cmstype->save();

        $cms = $cmstype->get_sample_cms();

        $ds = new dsnull($cms);
        $this->assertNull($ds->get_config_cache_key());
        $this->assertNull($ds->get_instance_cache_key());
        $this->assertNull($ds->get_full_cache_key());
    }
}
