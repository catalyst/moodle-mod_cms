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

use core_course\local\entity\{content_item, string_title};
use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\datasource\images as dsimages;
use mod_cms\local\model\{cms, cms_types};


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

    /** @var string Preferred hashing algorithm to be used. */
    public const HASH_ALGO = 'sha1';

    /**
     * Obtains a list of defined content types to be included in the activity chooser panel.
     *
     * @param content_item $defaultmodulecontentitem
     * @param \stdClass $user Not used.
     * @param \stdClass $course
     * @return array
     */
    public static function get_course_content_items(content_item $defaultmodulecontentitem, \stdClass $user,
            \stdClass $course): array {

        $context = \context_course::instance($course->id);

        $items = [];

        $baseurl = $defaultmodulecontentitem->get_link();
        $linkurl = new \moodle_url(
            self::MODEDIT_URL,
            ['id' => $baseurl->param('id'), 'course' => $course->id, 'add' => 'cms']
        );

        // Get the types, but only those that are visible.
        $filter = [];
        if (!has_capability('mod/cms:seeall', $context)) {
            $filter['isvisible'] = 1;
        }
        $types = cms_types::get_records($filter);
        foreach ($types as $type) {
            $iconurl = $type->get_type_icon();
            if (!is_null($iconurl)) {
                $icon = \html_writer::empty_tag('img', ['src' => $iconurl->out(), 'alt' => $type->get('name'), 'class' => 'icon']);
            } else {
                $icon = $defaultmodulecontentitem->get_icon();
            }
            $linkurl->param('typeid', $type->get('id'));
            $items[] = new content_item(
                $type->get('id'),
                $defaultmodulecontentitem->get_name(),
                new string_title($type->get('name')),
                clone($linkurl),
                $icon,
                $type->get('description'),
                $defaultmodulecontentitem->get_archetype(),
                $defaultmodulecontentitem->get_component_name(),
                method_exists($defaultmodulecontentitem, 'get_purpose') ? $defaultmodulecontentitem->get_purpose() : null
            );
        }

        return $items;
    }

    /**
     * Adds an instance of a CMS activity.
     *
     * @param \stdClass $instancedata Data to populate the instance.
     * @param \moodleform_mod|null $mform Not used.
     * @return int The ID of the newly crated instance.
     */
    public static function add_instance(\stdClass $instancedata, $mform = null): int {
        global $DB;

        $cms = new cms();
        $cms->set('typeid', $instancedata->typeid);
        $cms->set('intro', '');
        $cms->set('course', $instancedata->course);
        $cms->set('name', '');
        $cms->save();
        $instancedata->id = $cms->get('id');

        // We must do this here before updating the datasources, otherwise customfield handler will not be able to find the right
        // context.
        $DB->set_field('course_modules', 'instance', $instancedata->id, ['id' => $instancedata->coursemodule]);

        foreach (dsbase::get_datasources($cms) as $ds) {
            $ds->update_instance($instancedata, true);
        }

        $renderer = new renderer($cms);
        $cms->set('name', $renderer->get_name());
        $cms->save();

        return $cms->get('id');
    }

    /**
     * Updates an activity instance.
     *
     * @param \stdClass $instancedata
     * @param \moodleform_mod $mform
     * @return bool
     */
    public static function update_instance(\stdClass $instancedata, $mform): bool {
        $cm = get_coursemodule_from_id('cms', $instancedata->coursemodule, 0, false, MUST_EXIST);
        $cms = new cms($cm->instance);
        $cms->set('typeid', $instancedata->typeid);
        $cms->set('intro', '');
        $cms->set('course', $instancedata->course);

        $instancedata->id = $cm->instance;
        foreach (dsbase::get_datasources($cms) as $ds) {
            $ds->update_instance($instancedata, false);
        }

        $renderer = new renderer($cms);
        $cms->set('name', $renderer->get_name());
        $cms->save();

        return true;
    }

    /**
     * Reset the name field of cms instances.
     *
     * @param int $typeid
     * @throws \coding_exception
     */
    public static function reset_cms_names(int $typeid) {
        $records = cms::get_records(['typeid' => $typeid]);
        foreach ($records as $cms) {
            $renderer = new renderer($cms);
            $cms->set('name', $renderer->get_name());
            $cms->save();
        }
    }

    /**
     * Removes an instance of an activity.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_instance(int $id): bool {
        $cms = new cms($id);
        foreach (dsbase::get_datasources($cms, false) as $ds) {
            $ds->instance_on_delete();
        }
        $cms->delete();
        return true;
    }

    /**
     * Sets info into cminfo at the dynamic stage.
     * See lib/modinfolib.php - cm_info for more.
     *
     * @param \cm_info $cminfo
     */
    public static function cm_info_dynamic(\cm_info $cminfo) {
        $cms = new cms($cminfo->instance);
        $cminfo->set_name($cms->get('name'));
    }

    /**
     * Sets info into cminfo at the view stage.
     * See lib/modinfolib.php - cm_info for more.
     *
     * @param \cm_info $cminfo
     */
    public static function cm_info_view(\cm_info $cminfo) {
        $cms = new cms($cminfo->instance);
        $renderer = new renderer($cms);
        $content = $renderer->get_html();
        $cminfo->set_content($content);
        // This removes all the 'activity' chrome which makes it easier to style.
        $cminfo->set_custom_cmlist_item(true);
    }

    /**
     * Serves file
     *
     * @param \stdClass $course
     * @param \stdClass $cm
     * @param \context $context
     * @param string $filearea
     * @param array $args
     * @param bool $forcedownload
     * @param array $options additional options affecting the file serving
     * @return bool|null false if file not found, does not return anything if found - just send the file
     */
    public static function pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {
        // Make sure the filearea is one of those used by the plugin.
        if (!in_array($filearea, [dsimages::FILE_AREA, cms_types::ICON_FILE_AREA])) {
            return false;
        }

        $itemid = array_shift($args); // The first item in the $args array.

        // Extract the filename / filepath from the $args array.
        $filename = array_pop($args); // The last item in the $args array.
        if (!$args) {
            $filepath = '/';
        } else {
            $filepath = '/'.implode('/', $args).'/';
        }

        // Retrieve the file from the Files API.
        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_cms', $filearea, $itemid, $filepath, $filename);
        if (!$file) {
            return false; // The file does not exist.
        }

        // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
        send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
    }
}
