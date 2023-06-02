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

use core_course\local\entity\content_item;
use mod_cms\local\lib;
use mod_cms\local\model\cms_types;


/**
 * Unit tests for mod_cms
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_content_items_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a default content item based on the logic in content_item_readonly_repository::find_all_for_course()
     *
     * @return content_item
     */
    public function create_default_item(): content_item {
        global $OUTPUT, $DB;

        $mod = $DB->get_record('modules', ['name' => 'cms']);

        $archetype = plugin_supports('mod', $mod->name, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
        $purpose = plugin_supports('mod', $mod->name, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER);

        $icon = 'monologo';

        return new content_item(
            $mod->id,
            $mod->name,
            new \core_course\local\entity\lang_string_title("modulename", $mod->name),
            new \moodle_url('/course/mod.php', ['id' => 0, 'add' => $mod->name]),
            $OUTPUT->pix_icon($icon, '', $mod->name, ['class' => "activityicon"]),
            'help',
            $archetype,
            'mod_' . $mod->name,
            $purpose,
        );
    }

    /**
     * Tests the lib::get_course_content_items function
     * @covers \mod_cms\local\lib::get_course_content_items
     */
    public function test_get_course_content_items() {
        $types = [
            [ 'name' => 'CMS1', 'description' => 'help1'],
            [ 'name' => 'CMS2', 'description' => 'help2'],
            [ 'name' => 'betamax', 'description' => 'some description'],
        ];

        foreach ($types as $type) {
            $ct = new cms_types(0, (object) $type);
            $ct->save();
        }

        $user = (object) [];
        $course = (object) [];

        $items = lib::get_course_content_items($this->create_default_item(), $user, $course);

        // Make sure the two arrays have the same ordering so they can be compared by index.
        usort(
            $types,
            function($a, $b) {
                return strcmp($a['name'], $b['name']);
            }
        );
        usort(
            $items,
            function($a, $b) {
                return strcmp($a->get_title()->get_value(), $b->get_title()->get_value());
            }
        );

        $this->assertEquals(count($types), count($items));
        foreach ($types as $idx => $type) {
            $this->assertEquals($type['name'], $items[$idx]->get_title()->get_value());
            $this->assertEquals($type['description'], $items[$idx]->get_help());
        }
    }
}
