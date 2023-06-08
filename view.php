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

/**
 * Viewing a custom content instance
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_cms\local\model\cms;
use mod_cms\local\renderer;
require_once('../../config.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$action = optional_param('action', '', PARAM_TEXT);
$foruserid = optional_param('user', 0, PARAM_INT);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('cms', $id, 0, false, MUST_EXIST);

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$cms = new cms($cm->instance);

$PAGE->set_cm($cm, $course); // Set's up global $COURSE.
$context = context_module::instance($cm->id);
$PAGE->set_context($context);

require_login($course, true, $cm);
require_capability('mod/cms:view', $context);

$url = new moodle_url('/mod/cms/view.php', array('id' => $cm->id));
$PAGE->set_url($url);

// Print the page header.
echo $OUTPUT->header();

// Render the content of the mod.
$renderer = new renderer($cms);
echo $renderer->get_html();

// Finish the page.
echo $OUTPUT->footer();
