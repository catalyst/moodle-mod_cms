<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Library functions for custom content type module
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_course\local\entity\content_item;
use mod_cms\local\lib;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/vendor/autoload.php');

/**
 *  Triggered as soon as practical on every moodle bootstrap after config has
 *  been loaded. The $USER object is available at this point too.
 *
 *  NOTE: DO NOT REMOVE. This currently ensures all vendor libraries are loaded.
 */
function mod_cms_after_config() {
}

/**
 * Returns whether a feature is supported or not.
 *
 * @uses FEATURE_IDNUMBER
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @uses FEATURE_MOD_ARCHETYPE
 * @uses MOD_ARCHETYPE_RESOURCE
 * @param string $feature FEATURE_xx constant for requested feature
 *
 * @return bool|null
 */
function cms_supports($feature) {
    global $version;

    switch($feature) {
        case FEATURE_IDNUMBER:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_NO_VIEW_LINK:
            return true;
        case FEATURE_MOD_INTRO:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        default:
    }

    // Version 4.0.0 and later.
    if ($version >= 2022041900) {
        switch ($feature) {
            case FEATURE_MOD_PURPOSE:
                return MOD_PURPOSE_CONTENT;
            default:
        }
    }
    return null;
}

/**
 * Obtains a list of defined content types to be included in the activity chooser panel.
 *
 * @param content_item $defaultmodulecontentitem
 * @param stdClass $user
 * @param stdClass $course
 * @return array
 */
function cms_get_course_content_items(content_item $defaultmodulecontentitem, stdClass $user,
    stdClass $course) {
    return lib::get_course_content_items($defaultmodulecontentitem, $user, $course);
}

/**
 * Adds an instance of a CMS activity.
 *
 * @param stdClass $instancedata Data to populate the instance.
 * @param moodleform_mod|null $mform
 * @return int The ID of the newly crated instance.
 */
function cms_add_instance(stdClass $instancedata, $mform = null): int {
    return lib::add_instance($instancedata, $mform);
}

/**
 * Updates an activity instance.
 *
 * @param stdClass $instancedata
 * @param moodleform_mod $mform
 * @return bool
 */
function cms_update_instance(stdClass $instancedata, $mform): bool {
    return lib::update_instance($instancedata, $mform);
}

/**
 * Removes an instance of an activity.
 *
 * @param int $id
 * @return bool
 */
function cms_delete_instance(int $id): bool {
    return lib::delete_instance($id);
}

/**
 * Sets info into cminfo at the dynamic stage.
 *
 * @param \cm_info $cminfo
 */
function cms_cm_info_dynamic(cm_info $cminfo) {
    lib::cm_info_dynamic($cminfo);
}

/**
 * Sets info into cminfo at the view stage.
 *
 * @param \cm_info $cminfo
 */
function cms_cm_info_view(cm_info $cminfo) {
    lib::cm_info_view($cminfo);
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
function cms_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {
    return lib::pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options);
}
