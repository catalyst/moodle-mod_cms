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

namespace mod_cms\local\datasource;

use core_customfield\{category_controller, field, output\management};
use mod_cms\customfield\cmsfield_handler;
use mod_cms\helper;
use mod_cms\local\datasource\traits\hashcache;
use mod_cms\local\lib;
use mod_cms\local\model\cms;

/**
 * Data source for custom fields.
 *
 * This datasource has the ability to import fields that of an unsupported type. The data will be in the database, but will not
 * appear in the website, and will not be re-exported.
 *
 * If the supporting type is installed, the field will appear on the custom field edit page.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields extends base_mod_cms {
    use hashcache;

    /** @var cmsfield_handler Custom field handler. */
    protected $cfhandler;

    /**
     * Get the display name.
     *
     * @return string
     */
    public static function get_displayname(): string {
        return get_string('fields:custom_fields', 'mod_cms');
    }

    /**
     * Constructs a datasource for the given cms.
     *
     * @param cms $cms
     */
    public function __construct(cms $cms) {
        parent::__construct($cms);
        $this->cfhandler = cmsfield_handler::create($this->cms->get('typeid'));
    }

    /**
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        $instancedata = $this->cfhandler->get_instance_data($this->cms->get('id'), true);
        $customfields = new \stdClass();
        foreach ($instancedata as $field) {
            $shortname = $field->get_field()->get('shortname');
            $customfields->$shortname = $this->cms->issample ? $this->get_sample($field) : $field->export_value();
        }
        return $customfields;
    }

    /**
     * Get a sample value for a custom field.
     *
     * @param \core_customfield\data_controller $field
     * @return string
     */
    protected function get_sample($field): string {
        if ($field->get_field()->get('type') === 'date') {
            return get_string('fields:sample_time', 'mod_cms');
        } else {
            switch ($field->datafield()) {
                case 'intvalue':
                    return '10';
                case 'decvalue':
                    return '10.5';
                default:
                    return get_string('fields:sample_text', 'mod_cms');
            }
        }
    }

    /**
     * Add fields to the CMS type config form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        global $PAGE;

        // Add a heading.
        $mform->addElement('header', 'fields_heading', get_string('fields:config:header', 'mod_cms'));

        $output = $PAGE->get_renderer('core_customfield');
        $outputpage = new management($this->cfhandler);

        // This may not fill the screen. Add '.form-control-static {width: 100%}' to custom CSS.
        $html = $output->render($outputpage);

        $mform->addElement('static', 'fields', get_string('fields:config:columns', 'mod_cms'), $html);
    }

    /**
     * Add fields to the CMS instance form.
     *
     * @param \moodleform_mod $form
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\moodleform_mod $form, \MoodleQuickForm $mform) {
        $this->cfhandler->instance_form_definition($mform, $this->cms->get('id'));
    }

    /**
     * Get extra data needed to add to the form.
     * @param mixed $data
     */
    public function instance_form_default_data(&$data) {
        $this->cfhandler->instance_form_before_set_data($data);
    }

    /**
     * Validate fields added by datasource.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array {
        return $this->cfhandler->instance_form_validation($data, $files);
    }

    /**
     * Get configuration data for exporting.
     *
     * @return \stdClass
     */
    public function get_for_export(): \stdClass {
        $data = new \stdClass();
        $data->categories = [];
        $categories = $this->cfhandler->get_categories_with_fields();

        foreach ($categories as $category) {
            $record = $category->to_record();
            $catexport = (object) [
                'name' => $record->name,
                'description' => $record->description,
                'descriptionformat' => $record->descriptionformat,
                'sortorder' => $record->sortorder,
                'fields' => [],
            ];
            $fields = $category->get_fields();

            foreach ($fields as $field) {
                $record = $field->to_record();
                $catexport->fields[] = (object) [
                    'name' => $record->name,
                    'shortname' => $record->shortname,
                    'type' => $record->type,
                    'description' => $record->description,
                    'descriptionformat' => $record->descriptionformat,
                    'sortorder' => $record->sortorder,
                    'configdata' => $record->configdata,
                ];
            }

            $data->categories[] = $catexport;
        }
        return $data;
    }

    /**
     * Import configuration from an object.
     *
     * @param \stdClass $data
     */
    public function set_from_import(\stdClass $data) {
        if (!empty($data->categories)) {
            foreach ($data->categories as $category) {
                $catid = $this->cfhandler->create_category($category->name);
                $cc = category_controller::create($catid);
                $cc->set('description', $category->description);
                $cc->set('descriptionformat', $category->descriptionformat);
                $cc->save();
                if (!empty($category->fields)) {
                    foreach ($category->fields as $fielddata) {
                        $record = clone $fielddata;
                        $record->categoryid = $catid;
                        // Use field rather than field_controller so we can import unsupported fields.
                        $field = new field(0, $record);
                        $field->save();
                    }
                }
            }
        }
        $this->update_config_cache_key();
    }

    /**
     * Called when an instance is added/updated.
     *
     * @param \stdClass $instancedata
     * @param bool $isnewinstance
     */
    public function update_instance(\stdClass $instancedata, bool $isnewinstance) {
        // Save the custom field data.
        $this->cfhandler->instance_form_save($instancedata, $isnewinstance);

        $this->update_instance_cache_key();
    }

    /**
     * Called when deleting a CMS type.
     */
    public function config_on_delete() {
        $this->cfhandler->delete_all();
    }

    /**
     * Called when deleting a CMS instance.
     */
    public function instance_on_delete() {
        $this->cfhandler->delete_instance($this->cms->get('id'));
    }

    /**
     * Create a structure of the instance for backup.
     *
     * @param \backup_nested_element $parent
     */
    public function instance_backup_define_structure(\backup_nested_element $parent) {
        $fields = new \backup_nested_element('fields');
        $field = new \backup_nested_element('field', ['id'], ['shortname', 'type', 'value', 'valueformat']);

        $parent->add_child($fields);
        $fields->add_child($field);

        $fieldsforbackup = $this->cfhandler->get_instance_data_for_backup($this->cms->get('id'));
        $field->set_source_array($fieldsforbackup);
    }

    /**
     * Add restore path elements to the restore activity.
     *
     * @param array $paths
     * @param \restore_cms_activity_structure_step $stepslib
     * @return array
     */
    public static function restore_define_structure(array $paths, \restore_cms_activity_structure_step $stepslib): array {
        $processor = new restore\fields($stepslib);

        $element = new \restore_path_element('restore_ds_fields', '/activity/cms/instance_datasources/fields/field');
        $element->set_processing_object($processor);
        $paths[] = $element;

        return $paths;
    }
}
