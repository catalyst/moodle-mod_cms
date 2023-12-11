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

use mod_cms\local\model\cms_types;
use core_customfield\category_controller;
use core_customfield\field_controller;

/**
 * Test data generator for mod_cms.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2024, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_cms_generator extends \component_generator_base {

    /** @var int */
    protected $cmstypecount = 0;

    /**
     * Get generator for custom fields.
     * @return core_customfield_generator
     */
    protected function get_cf_generator() {
        return $this->datagenerator->get_plugin_generator('core_customfield');
    }

    /**
     * Create a CMS type.
     *
     * @param array|object|null $record
     * @return cms_types
     */
    public function create_cms_type($record = null): cms_types {
        $cmstype = new cms_types();
        $record = (object) $record;
        $i = $this->cmstypecount++;

        if (!isset($record->name)) {
            $record->name = 'CMS type ' . $i;
        }
        if (!isset($record->idnumber)) {
            $record->idnumber = 'cmstypetype' . $i;
        }
        $cmstype->from_record($record);
        $cmstype->save();

        return $cmstype;
    }

    /**
     * Create a custom field category for use with a CMS type with a fields datasource.
     *
     * @param cms_types $cmstype
     * @param array|object|null $record
     * @return category_controller
     */
    public function create_datasource_fields_category(cms_types $cmstype, $record = null): category_controller {
        $record = (object) $record;
        $record->component = 'mod_cms';
        $record->area = 'cmsfield';
        $record->itemid = $cmstype->get('id');
        return $this->get_cf_generator()->create_category($record);
    }

    /**
     * Create a field for use with a fields datasoruce.
     *
     * @param array|object|null $record
     * @return field_controller
     */
    public function create_datasource_fields_field($record = null): field_controller {
        return $this->get_cf_generator()->create_field($record);
    }

    /**
     * Create a custom field category for use with a CMS type with a userlist datasource.
     * Do not call this directly, as there shoudl be only one category for each CMS type.
     *
     * @param cms_types $cmstype
     * @param array|object|null $record
     * @return category_controller
     */
    protected function create_datasource_userlist_category(cms_types $cmstype, $record = null): category_controller {
        $record = (object) $record;
        $record->component = 'mod_cms';
        $record->area = 'cmsuserlist';
        $record->itemid = $cmstype->get('id');
        return $this->get_cf_generator()->create_category($record);
    }

    /**
     * Create a field for use with a userlist datasoruce.
     *
     * @param cms_types $cmstype
     * @param array|object|null $record
     * @return field_controller
     */
    public function create_datasource_userlist_field(cms_types $cmstype, $record = null): field_controller {
        $record = (object) $record;
        $typeid = $cmstype->get('id');
        if (!isset($this->userlistcategories[$typeid])) {
            $this->userlistcategories[$typeid] = $this->create_datasource_userlist_category($cmstype);
        }
        $record->categoryid = $this->userlistcategories[$typeid]->get('id');
        return $this->get_cf_generator()->create_field($record);
    }

    /**
     * Make a draft file.
     *
     * @param string $filename
     * @param string $content
     * @return mixed The files's item ID.
     */
    public function make_file(string $filename, string $content) {
        global $USER;

        $fs = get_file_storage();
        $filerecord = [
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => $filename,
        ];
        $fs->create_file_from_string($filerecord, $content);
        return $filerecord['itemid'];
    }
}
