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
 * Default settings for the cms module.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add(
    'modsettings',
    new admin_category(
        'modcmsfolder',
        new lang_string('pluginname', 'mod_cms'),
        $module->is_enabled() === false
    )
);

$ADMIN->add(
    'modcmsfolder',
    new admin_externalpage(
        'mod_cms/managetypes',
        get_string('managetypes', 'mod_cms'),
        new moodle_url('/mod/cms/managetypes.php')
    )
);

$settings = new admin_settingpage(
    $section,
    get_string('settings', 'mod_cms'),
    'moodle/site:config',
    $module->is_enabled() === false
);

if ($ADMIN->fulltree) {
    ;// TODO: There will probably be some more interesting settings to add here in the future.
}

$ADMIN->add('modcmsfolder', $settings);

// Tell core we already added the settings structure.
$settings = null;
