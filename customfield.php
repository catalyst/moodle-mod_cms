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
 * Edit page for managing custom fields for a cms type.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_cms\customfield\cmsfield_handler;
use mod_cms\local\model\cms_types;
use core_customfield\output\management;

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$itemid  = required_param('itemid', PARAM_INT);

admin_externalpage_setup('mod_cms/managetypes');

$url = new moodle_url('/mod/cms/customfield.php', ['itemid' => $itemid]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);

$cfhandler = cmsfield_handler::create($itemid);

$cmstype = new cms_types($itemid);

$returnurl = new moodle_url('/mod/cms/managetypes.php');

$pageheading = get_string('customfield_manage_heading', 'mod_cms', $cmstype->get('name'));

// Need to add extra breadcrumbs when URL does not exactly match admin setting page URL.
foreach (['modsettings', 'modcmsfolder', 'mod_cms/managetypes'] as $label) {
    if ($node = $PAGE->settingsnav->find($label, \navigation_node::TYPE_SETTING)) {
        $PAGE->navbar->add($node->get_content(), $node->action());
    }
}
$PAGE->navbar->add($pageheading);

$output = $PAGE->get_renderer('core_customfield');
$outputpage = new management($cfhandler);

echo $output->header();
echo $output->heading($pageheading);

echo $output->render($outputpage);
echo $output->footer();
