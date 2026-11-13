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
$string['cachedef_cms_content'] = 'CMS content';
$string['cachedef_cms_content_course'] = 'CMS course datasource';
$string['cachedef_cms_content_fields'] = 'CMS fields datasource';
$string['cachedef_cms_content_images'] = 'CMS images datasource';
$string['cachedef_cms_content_roles'] = 'CMS roles datasource';
$string['cachedef_cms_content_userlist'] = 'CMS userlist datasource';
$string['cachedef_cms_name'] = 'CMS name';
$string['chooser:hide'] = 'Hide in activity chooser';
$string['chooser:show'] = 'Show in activity chooser';
$string['cms:addinstance'] = 'Add a new custom content instance';
$string['cms:seeall'] = 'Can see hidden CMS types in the activity chooser';
$string['cms:view'] = 'View a new custom content instance';
$string['cms_type:icon_desc'] = 'This icon will be displayed in the activity chooser menu.';
$string['course:description'] = 'Basic details of the course the activity is in: name, short name, ID number, summary, link, course image and course custom fields. Read live from the course record.';
$string['course:displayname'] = 'Course info';
$string['course:sample:fullname'] = 'Example Course';
$string['course:sample:shortname'] = 'EXAMPLE101';
$string['course:sample:summary'] = 'An example course summary.';
$string['customfield_manage_heading'] = 'Manage custom fields for content type "{$a}"';
$string['datasources'] = 'Datasources';
$string['datasources_desc'] = 'Select the datasources for your content type. Only selected datasources will be configurable, or editable at the instance level. Remove any unused sources as they may have performance impacts.';
$string['editcontenttype'] = 'Edit content type';
$string['error:cant_delete_content_type'] = 'Cannot delete content type.';
$string['error:class_missing'] = 'Datasource class \'{$a}\' does not exist.';
$string['error:invalid'] = 'Invalid: {$a}';
$string['error:must_be_base'] = 'Datasource class \'($a}\' must inherit from mod_cms\\datasource\\base.';
$string['error:name_not_unique'] = 'Datasource class shortname \'($a}\' must be unique.';
$string['error:no_config_hash'] = 'Module {$a} has no config hash.';
$string['error:no_file_uploaded'] = 'No file uploaded';
$string['error:no_instance_hash'] = 'Module {$a} has no instance hash.';
$string['event:cms_type_created'] = 'Custom content type created';
$string['event:cms_type_deleted'] = 'Custom content type deleted';
$string['event:cms_type_updated'] = 'Custom content type updated';
$string['event_cms_type_created_desc'] = 'The user with ID: {$a->userid} has created a custom content type with ID: {$a->typeid}';
$string['event_cms_type_deleted_desc'] = 'The user with ID: {$a->userid} has deleted a custom content type with ID: {$a->typeid}';
$string['event_cms_type_updated_desc'] = 'The user with ID: {$a->userid} has updated a custom content type with ID: {$a->typeid}';
$string['export'] = 'Export';
$string['fields:config:columns'] = 'Fields and categories';
$string['fields:config:header'] = 'Custom field settings';
$string['fields:custom_fields'] = 'Custom fields';
$string['fields:description'] = 'This allows you to define custom fields that the course editor fills in for each module instance.';
$string['fields:sample_text'] = 'text';
$string['fields:sample_time'] = 'Thursday, 15 June 2023, 12:00 AM';
$string['idnumber'] = 'ID number';
$string['idnumber_exists'] = 'Id number \'{$a}\' already exists in the system.';
$string['idnumber_help'] = 'An identifier to uniquely label this CMS type. This will be used in backup and restoring. It must be unique within the system, and ideally, unique universally.';
$string['images:config:header'] = 'Images settings';
$string['images:description'] = 'Images uploaded and stored with this type. They are shared by every instance of the type and served publicly like other theme images.';
$string['images:images'] = 'Images';
$string['import'] = 'Import';
$string['import_cms_type'] = 'Import content type';
$string['import_file'] = 'Import file';
$string['instance:header'] = 'Activity templates';
$string['instance:name'] = 'Activity name template';
$string['instance:name_help'] = 'A template for the name of each activity created from this type. It can use the same variables as the content template, so the name can come from the data. This is the name shown on the course page, and in the activity header if that is enabled.

If you want a different name for each instance, add a custom field for it and use it here, for example {{fields.name}}.';
$string['manage_types_return'] = 'Return to manage types';
$string['managetypes'] = 'Manage content types';
$string['maxgrade'] = 'Default max grade';
$string['maxgrade_desc'] = 'The default max grade when creating a new custom content type instance.';
$string['modulename'] = 'CMS';
$string['modulenameplural'] = 'CMS';
$string['javascript'] = 'JavaScript';
$string['javascript_help'] = 'JavaScript for this type. It is served as its own file and included once on any page that shows an instance of the type, so it needs to handle every instance on the page. It is only editable by site administrators, so it is not treated as user-generated content and still runs when logged in as another user.';
$string['mustache'] = 'Content';
$string['mustache_help'] = 'The two fields above will form the content displayed in the activity. They both will need to be valid {$a}. Variables that are available for use in these templates are given below.';
$string['mustache_template'] = 'mustache templates';
$string['newcontenttype'] = 'Add new content type';
$string['pluginadministration'] = 'Plugin administration';
$string['pluginname'] = 'CMS';
$string['preview_with_hint'] = 'Preview (click "{$a}" to update)';
$string['privacy:metadata:cms'] = 'Custom content type instances';
$string['privacy:metadata:cms:usermodified'] = 'User who modified the instances';
$string['privacy:metadata:cms_types'] = 'Custom content types';
$string['privacy:metadata:cms_types:usermodified'] = 'User who modified the custom content types';
$string['roles:config:available'] = 'Available course role shortnames';
$string['roles:config:duplicates'] = 'Duplicates';
$string['roles:config:duplicates:all'] = 'Show in all';
$string['roles:config:duplicates:firstonly'] = 'Show in first';
$string['roles:config:duplicates:nest'] = 'Show in first with roles';
$string['roles:config:duplicates_help'] = 'Controls what happens when a user has more than one of the listed course roles.

* Show in all: the user is listed under every one of their course roles.
* Show in first: the user is listed only under the first of their course roles, in the order given in Roles included.
* Show in first with roles: the user is listed under their first role, along with the shortnames of all their listed course roles.';
$string['roles:config:header'] = 'Role list settings';
$string['roles:config:list'] = 'Roles included';
$string['roles:config:list_help'] = 'The shortnames of the course roles to list, one per line, for example student or editingteacher. Only roles that can be assigned at course level are available; they are listed below the field. Roles are shown in the order given. If left empty, all course roles are listed. Only roles assigned in the course itself are included.';
$string['roles:description'] = 'The users with roles in the course, grouped by role. Read from the course\'s role assignments and the user records.';
$string['roles:displayname'] = 'Roles list';
$string['roles:error:role_does_not_exist'] = 'Role "{$a}" does nor exist.';
$string['sample_value'] = 'Sample value';
$string['search:activity'] = 'CMS - activity information';
$string['sections'] = 'Sections';
$string['settings'] = 'Custom content type settings';
$string['showheader'] = 'Show activity header';
$string['showheader_desc'] = 'Show the icon and name above the content, like other activities in the course page.';
$string['showheader_help'] = 'When enabled, the type icon and the name of the activity are shown above the rendered template, in the same style as the other activities in a course.';
$string['site:displayname'] = 'Site Info';
$string['some_name'] = 'Some name';
$string['table:name'] = 'Custom content type';
$string['table:numinstances'] = 'Number of instances';
$string['typename'] = 'Type name';
$string['typename_help'] = 'The name of this content type, shown to administrators and in the activity chooser. It is not displayed on the course page. Use the activity name template below to set the name of each activity.';
$string['userlist:config:columns'] = 'Custom data table columns';
$string['userlist:config:header'] = 'Custom data table settings';
$string['userlist:config:listheading'] = 'Instance form heading';
$string['userlist:config:listheading_help'] = 'The heading shown above the rows in the activity settings form. Leave empty to use the default, "List data".';
$string['userlist:description'] = 'A table of rows that course editors fill in on each module instance, such as a list of links or contacts. The columns are set up in this type\'s configuration, and the rows are saved with the instance.';
$string['userlist:displayname'] = 'Custom data table';
$string['userlist:listdata'] = 'List data';
$string['userlist:pageheading'] = 'List definition for \'{$a}\'';
$string['visibility_updated'] = 'Visibility updated';
