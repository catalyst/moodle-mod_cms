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

namespace mod_cms\local;

use core_course\local\entity\content_item;

/**
 * Generic library functions for mod_cms.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib {
    /**
     * Obtains a list of defined content types to be included in the activity chooser panel.
     *
     * @param content_item $defaultmodulecontentitem
     * @param \stdClass $user Not used.
     * @param \stdClass $course Not used.
     * @return array
     */
    public static function get_course_content_items(content_item $defaultmodulecontentitem, \stdClass $user,
        \stdClass $course) : array {
        $items = [];

        $types = model\cms_types::get_records();
        foreach ($types as $type) {
            $items[] = new \core_course\local\entity\content_item(
                $type->get('id'),
                $defaultmodulecontentitem->get_name(),
                new \core_course\local\entity\string_title($type->get('name')),
                $defaultmodulecontentitem->get_link(),
                $defaultmodulecontentitem->get_icon(),
                $type->get('description'),
                $defaultmodulecontentitem->get_archetype(),
                $defaultmodulecontentitem->get_component_name(),
                $defaultmodulecontentitem->get_purpose()
            );
        }

        return $items;
    }
}
