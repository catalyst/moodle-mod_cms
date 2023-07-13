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
 * Userlist column configuration.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_cms\local\model\{cms_types, cms_userlist_columns};
use mod_cms\form\cms_userlist_form;

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$typeid  = required_param('typeid', PARAM_INT);

admin_externalpage_setup('mod_cms/managetypes');

$url = new moodle_url('/mod/cms/userlist.php', ['typeid' => $typeid]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);

$returnurl = '/mod/cms/managetypes.php';

$persistent = cms_userlist_columns::get_from_typeid($typeid);
$form = new cms_userlist_form($PAGE->url->out(false), ['persistent' => $persistent]);

if ($form->is_cancelled()) {
    redirect(new \moodle_url($returnurl));
} else if ($data = $form->get_data()) {
    try {
        if (empty($data->id)) {
            // If we don't have an ID, we know that we must create a new record.
            $data->typeid = $typeid;
            $persistent = new cms_userlist_columns(0, $data);
            $persistent->create();
        } else {
            // We had an ID, this means that we are going to update a record.
            $persistent->from_record($data);
            $persistent->update();
        }
        \core\notification::success(get_string('changessaved'));
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }

    // We are done, so let's redirect somewhere.
    redirect($returnurl);
}
$cmstype = new cms_types($typeid);
$pageheading = get_string('userlist:pageheading', 'mod_cms', $cmstype->get('name'));

// Need to add extra breadcrumbs when URL does not exactly match admin setting page URL.
foreach (['modsettings', 'modcmsfolder', 'mod_cms/managetypes'] as $label) {
    if ($node = $PAGE->settingsnav->find($label, \navigation_node::TYPE_SETTING)) {
        $PAGE->navbar->add($node->get_content(), $node->action());
    }
}
$PAGE->navbar->add($pageheading);

echo $OUTPUT->header();
echo $OUTPUT->heading($pageheading);

$form->display();

echo $OUTPUT->footer();
