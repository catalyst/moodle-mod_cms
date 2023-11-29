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

use mod_cms\form\cms_types_form;
use mod_cms\local\model\cms_types;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_import1_trait.php');

/**
 * Unit tests for cms_types.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_types_test extends \advanced_testcase {
    /** Test data for import/export. */
    public const IMPORT_DATAFILE = __DIR__ . '/fixtures/type_data.json';

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Tests validation of mustache templates.
     *
     * @covers \mod_cms\local\model\cms_types::validate_title_mustache
     * @covers \mod_cms\local\model\cms_types::validate_mustache
     * @dataProvider mustache_validity_datasource
     * @param string $field
     * @param string $mustache
     * @param bool $valid
     */
    public function test_mustache_validity(string $field, string $mustache, bool $valid) {
        $cmstype = new cms_types();
        $cmstype->set($field, $mustache);

        $errors = $cmstype->validate();
        if ($valid) {
            $this->assertArrayNotHasKey($field, $errors);
        } else {
            $this->assertArrayHasKey($field, $errors);
        }
    }

    /**
     * Data source for test_mustache_validity
     *
     * @return array[]
     */
    public function mustache_validity_datasource(): array {
        return [
            ['title_mustache', 'test', true],
            ['title_mustache', '{{test}}', true],
            ['title_mustache', '{{/test}}', false],
            ['title_mustache', '{{#test}}', false],
            ['mustache', 'test', true],
            ['mustache', '{{test}}', true],
            ['mustache', '{{/test}}', false],
            ['mustache', '{{#test}}', false],
        ];
    }

    /**
     * Tests validation of idnumber.
     *
     * @covers \mod_cms\local\model\cms_types::validate_idnumber
     * @dataProvider idnumber_validity_datasource
     * @param string|null $idnumber
     * @param bool $valid
     */
    public function test_idnumber(?string $idnumber, bool $valid) {

        $cmstype = new cms_types();
        $cmstype->set('name', 'name');
        $cmstype->set('idnumber', 'test-exists');
        $cmstype->save();

        $cmstype = new cms_types();
        $cmstype->set('idnumber', $idnumber);

        $errors = $cmstype->validate();
        if ($valid) {
            $this->assertArrayNotHasKey('idnumber', $errors);
        } else {
            $this->assertArrayHasKey('idnumber', $errors);
        }
    }

    /**
     * Data source for test_idnumber
     *
     * @return array[]
     */
    public function idnumber_validity_datasource(): array {
        return [
            [null, false],
            ['', false],
            ['0', true],
            ['test-unique', true],
            ['test-exists', false],
        ];
    }

    /**
     * Tests the import/export functions.
     *
     * @covers \mod_cms\local\model\cms_types::get_for_export
     * @covers \mod_cms\local\model\cms_types::get_from_import
     * @covers \mod_cms\local\model\cms_types::get_cache_key
     * @covers \mod_cms\local\model\cms_types::get_icon_metadata
     */
    public function test_import() {
        $importdata = json_decode(file_get_contents(self::IMPORT_DATAFILE));
        $cmstype = new cms_types();
        $cmstype->set_from_import($importdata);
        $cachekey = $cmstype->get_cache_key();
        $exportdata = $cmstype->get_for_export();

        $this->assertNotNull($cmstype->get_icon_metadata());
        $this->assertEquals($importdata, $exportdata);

        unset($importdata->icon);
        $importdata->idnumber = 'diffname';
        $cmstype = new cms_types();
        $cmstype->set_from_import($importdata);
        $exportdata = $cmstype->get_for_export();

        $this->assertNull($cmstype->get_icon_metadata());
        $this->assertNotEquals($cachekey,  $cmstype->get_cache_key());
        $this->assertEquals($importdata, $exportdata);
    }
}
