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

use cm_info;
use context;
use core_course\local\entity\content_item;
use core_course\local\entity\string_title;
use mod_cms\customfield\cmsfield_handler;
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
                method_exists($defaultmodulecontentitem, 'get_purpose') ? $defaultmodulecontentitem->get_purpose() : null
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
        $cms->set('name', $instancedata->name);
        $cms->set('typeid', $instancedata->typeid);
        $cms->set('intro', '');
        $cms->save();

        // Save the custom field data.
        $instancedata->id = $cms->get('id');
        $cfhandler = cmsfield_handler::create($instancedata->typeid);
        $cfhandler->instance_form_save($instancedata, true);

        return $cms->get('id');
    }

    /**
     * Updates an activity instance.
     *
     * @param stdClass $instancedata
     * @param moodleform_mod $mform
     * @return bool
     */
    public static function update_instance(stdClass $instancedata, $mform): bool {
        $cm = get_coursemodule_from_id('cms', $instancedata->update, 0, false, MUST_EXIST);
        $cms = new cms($cm->instance);
        $cms->set('name', $instancedata->name);
        $cms->set('typeid', $instancedata->typeid);
        $cms->set('intro', '');
        $cms->save();

        // Save the custom field data.
        $instancedata->id = $cm->instance;
        $cfhandler = cmsfield_handler::create($instancedata->typeid);
        $cfhandler->instance_form_save($instancedata);

        return true;
    }

    /**
     * Sets info into cminfo at the dynamic stage.
     * See lib/modinfolib.php - cm_info for more.
     *
     * @param cm_info $cminfo
     */
    public static function cm_info_dynamic(cm_info $cminfo) {
        $cms = new cms($cminfo->instance);
        $cminfo->set_name($cms->get('name'));
    }

    /**
     * Sets info into cminfo at the view stage.
     * See lib/modinfolib.php - cm_info for more.
     *
     * @param cm_info $cminfo
     */
    public static function cm_info_view(cm_info $cminfo) {
        $cms = new cms($cminfo->instance);
        $renderer = new renderer($cms);
        $cminfo->set_content($renderer->get_html());
    }

    /**
     * Serves file
     *
     * @param stdClass $course
     * @param stdClass $cm
     * @param context $context
     * @param string $filearea
     * @param array $args
     * @param bool $forcedownload
     * @param array $options additional options affecting the file serving
     * @return bool|null false if file not found, does not return anything if found - just send the file
     */
    public static function pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {

        // Make sure the filearea is one of those used by the plugin.
        if ($filearea !== 'cms_type_images') {
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
