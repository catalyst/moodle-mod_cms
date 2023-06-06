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
use core_course\local\entity\string_title;
use mod_cms\local\model\cms;
use mod_cms\local\model\cms_types;
use moodle_url;
use moodleform_mod;
use stdClass;

/**
 * Generic library functions for mod_cms.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib {
    /** @var string The URL for editing a mod instance. */
    protected const MODEDIT_URL = '/course/modedit.php';

    /**
     * Obtains a list of defined content types to be included in the activity chooser panel.
     *
     * @param content_item $defaultmodulecontentitem
     * @param stdClass $user Not used.
     * @param stdClass $course Not used.
     * @return array
     */
    public static function get_course_content_items(content_item $defaultmodulecontentitem, stdClass $user,
            stdClass $course): array {
        global $COURSE;

        $items = [];

        $baseurl = $defaultmodulecontentitem->get_link();
        $linkurl = new moodle_url(
            self::MODEDIT_URL,
            ['id' => $baseurl->param('id'), 'course' => $COURSE->id, 'add' => 'cms']
        );

        $types = cms_types::get_records();
        foreach ($types as $type) {
            $linkurl->param('typeid', $type->get('id'));
            $items[] = new content_item(
                $type->get('id'),
                $defaultmodulecontentitem->get_name(),
                new string_title($type->get('name')),
                clone($linkurl),
                $defaultmodulecontentitem->get_icon(),
                $type->get('description'),
                $defaultmodulecontentitem->get_archetype(),
                $defaultmodulecontentitem->get_component_name(),
                $defaultmodulecontentitem->get_purpose()
            );
        }

        return $items;
    }

    /**
     * Adds an instance of a CMS activity.
     *
     * @param stdClass $instancedata Data to populate the instance.
     * @param moodleform_mod|null $mform Not used.
     * @return int The ID of the newly crated instance.
     */
    public static function add_instance(stdClass $instancedata, $mform = null): int {
        // TODO: This is a stub.
        $cms = new cms();
        $cms->set('name', $data->name);
        $cms->set('typeid', $data->typeid);
        $cms->set('intro', '');
        $cms->save();
        return $cms->get('id');
    }
}
