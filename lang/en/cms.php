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
 * English language strings for the plugin.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addnewtype'] = 'Add new content type';
$string['editcontenttype'] = 'Edit content type';
$string['event:cms_type_created'] = 'Custom content type created';
$string['event_cms_type_created_desc'] = 'The user with ID: {$a->userid} has created a custom content type with ID: {$a->typeid}';
$string['event:cms_type_deleted'] = 'Custom content type deleted';
$string['event_cms_type_deleted_desc'] = 'The user with ID: {$a->userid} has deleted a custom content type with ID: {$a->typeid}';
$string['event:cms_type_updated'] = 'Custom content type updated';
$string['event_cms_type_updated_desc'] = 'The user with ID: {$a->userid} has updated a custom content type with ID: {$a->typeid}';
$string['export'] = 'Export';
$string['import'] = 'Import';
$string['managetypes'] = 'Manage content types';
$string['maxgrade'] = 'Default max grade';
$string['maxgrade_desc'] = 'The default max grade when creating a new custom content type instance.';
$string['modulename'] = 'CMS';
$string['modulenameplural'] = 'CMS';
$string['newcontenttype'] = 'Add new content type';
$string['pluginname'] = 'CMS';
$string['privacy:metadata:cms'] = 'Custom content type instances';
$string['privacy:metadata:cms:usermodified'] = 'User who modified the instances';
$string['privacy:metadata:cms_types'] = 'Custom content types';
$string['privacy:metadata:cms_types:usermodified'] = 'User who modified the custom content types';
$string['settings'] = 'Custom content type settings';
$string['table:name'] = 'Custom content type';
$string['cms:addinstance'] = 'Add a new custom content instance';
$string['pluginadministration'] = 'Plugin administration';
$string['mustache'] = 'Mustache template';
$string['mustache_help'] = 'The above content will need to be a valid {$a}. Sample variables that are available for use in this template are given below.';
$string['mustache_template'] = 'mustache template';
$string['preview'] = 'Preview (click "{$a}" to update)';
$string['customfield_manage_heading'] = 'Manage custom fields for content type "{$a}"';
$string['manage_types_return'] = 'Return to manage types';
$string['sample_value'] = 'Sample value';
$string['some_name'] = 'Some name';
$string['import_file'] = 'Import file';
$string['import_cms_type'] = 'Import content type';
$string['datasources'] = 'Datasources';
$string['datasources_desc'] = 'This is a performance measure. Select datasources to be included in this type. Only selected datasources will be configurable,
 or editable at the instance level, or be called upon to provide data. Some datasources are always included and do not appear in
 this list.';

// Site datasource strings.
$string['site:displayname'] = 'Site Info';

// Image datasource strings.
$string['images:images'] = 'Images';

// Custom field datasource strings.
$string['fields:custom_fields'] = 'Custom fields';
$string['fields:sample_text'] = 'text';
$string['fields:sample_time'] = 'Thursday, 15 June 2023, 12:00 AM';

// Error strings.
$string['error:class_missing'] = 'Datasource class \'{$a}\' does not exist.';
$string['error:must_be_base'] = 'Datasource class \'($a}\' must inherit from mod_cms\\datasource\\base.';
$string['error:name_not_unique'] = 'Datasource class shortname \'($a}\' must be unique.';
$string['error:no_file_uploaded'] = 'No file uploaded';
