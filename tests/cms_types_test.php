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

/**
 * Unit tests for cms_types.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_types_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Tests validation mustaceh templates.
     *
     * @covers \mod_cms\form\cms_types_form::extra_validation
     * @dataProvider mustache_validity_datasource
     * @param string $field
     * @param string $mustache
     * @param bool $valid
     */
    public function test_mustache_validity(string $field, string $mustache, bool $valid) {

        $form = new cms_types_form(null, ['persistent' => null]);

        $data = (object) [
            'mustache' => '',
            'title_mustache' => '',
        ];
        $data->$field = $mustache;

        $errors = [];
        $errors = $form->extra_validation($data, [], $errors);
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
}
