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
 * Admin page listing the instances of the custom content types.
 *
 * @package     mod_cms
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core_reportbuilder\local\filters\select;
use core_reportbuilder\system_report_factory;
use mod_cms\local\model\cms_types;
use mod_cms\reportbuilder\local\systemreports\instances;

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$typeid = optional_param('typeid', 0, PARAM_INT);

$url = new moodle_url('/mod/cms/instances.php', $typeid ? ['typeid' => $typeid] : []);
admin_externalpage_setup('mod_cms/managetypes', '', null, $url);

$heading = get_string('instances:heading', 'mod_cms');
if ($typeid) {
    $heading = get_string('instances:headingfortype', 'mod_cms', s((new cms_types($typeid))->get('name')));
}

$PAGE->navbar->add(get_string('managetypes', 'mod_cms'), new moodle_url('/mod/cms/managetypes.php'));
$PAGE->navbar->add($heading);
$PAGE->set_title($heading);
$PAGE->set_heading($SITE->fullname);

$report = system_report_factory::create(instances::class, context_system::instance());
if ($typeid) {
    // Preset the type filter; the user can change or clear it.
    $report->set_filter_values([
        'cms_instance:type_operator' => select::EQUAL_TO,
        'cms_instance:type_value' => $typeid,
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $report->output();
echo $OUTPUT->footer();
