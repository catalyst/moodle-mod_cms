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

use core_customfield\{data_controller, field, field_controller};
use core_customfield\output\management;
use mod_cms\customfield\cmsuserlist_handler;
use mod_cms\local\lib;
use mod_cms\local\datasource\traits\hashcache;
use mod_cms\local\model\cms;

/**
 * User designed lists
 *
 * A user designed list is where the user creates and fills a list with arbitrary data.
 * The columns of the list are defined within the config of the CMS type.
 * The rows are filled in as part of the module instance editing.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userlist extends base_mod_cms {
    use hashcache;

    /** Default number of rows to include in the list edit form. */
    const DEFAULT_NUM_ROWS = 2;
    /** Prefix to use for list elements. */
    const FORM_PREFIX = 'userlist_';
    /** Custom field prefix. */
    const CUSTOMFIELD_PREFIX = 'customfield_';
    /** Name used for the repeat hidden name (repeat counts). */
    const FORM_REPEATHIDDENNAME = self::FORM_PREFIX . 'option_repeats';

    /** @var cmsuserlist_handler Custom field handler. */
    protected $cfhandler;

    /**
     * The short name of the datasource type. Must be unique.
     *
     * @return string
     */
    public static function get_shortname(): string {
        return 'list';
    }

    /**
     * Get the display name.
     *
     * @return string
     */
    public static function get_displayname(): string {
        return get_string('userlist:displayname', 'mod_cms');
    }

    /**
     * Constructs a datasource for the given cms.
     *
     * @param cms $cms
     */
    public function __construct(cms $cms) {
        parent::__construct($cms);
        $this->cfhandler = cmsuserlist_handler::create($this->cms->get('typeid'));
    }

    /**
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        $data = new \stdClass();
        $data->columns = [];
        $columndefs = $this->cfhandler->get_fields();
        $data->numcolumns = count($columndefs);
        foreach ($columndefs as $columndef) {
            $data->columns[] = (object) [
                'shortname' => $columndef->get('shortname'),
                'name' => $columndef->get_formatted_name(),
            ];
        }
        if ($this->cms->issample) {
            $data->data = $this->get_sample($columndefs);
        } else {
            $instanceids = $this->get_instance_ids();
            ksort($instanceids);
            $rows = [];
            foreach ($instanceids as $id) {
                $rowobj = new \stdClass();
                $row = $this->cfhandler->get_instance_data($id, true);
                foreach ($row as $datacontroller) {
                    $name = $datacontroller->get_field()->get('shortname');
                    $rowobj->$name = $datacontroller->export_value();
                }
                $rows[] = $rowobj;
            }
            $data->data = $rows;
        }
        if (isset($data->data)) {
            $data->numrows = count($data->data);
        } else {
            $data->numrows = 0;
        }

        return $data;
    }

    /**
     * Get a sample list for display on the config page.
     *
     * @param array $columndefs
     * @return array
     */
    protected function get_sample(array $columndefs): array {
        $rows = 2;

        $data = [];
        for ($i = 0; $i < $rows; ++$i) {
            $row = new \stdClass();
            foreach ($columndefs as $columndef) {
                $name = $columndef->get('shortname');
                $row->$name = $columndef->get_configdata_property('defaultvalue') ?? 0;
                if ($row->$name === '') {
                    $row->$name = 'text';
                }
            }
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Add fields to the CMS type config form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        global $PAGE;

        // Add a heading.
        $mform->addElement('header', 'userlist_heading', get_string('userlist:config:header', 'cms'));

        $output = $PAGE->get_renderer('core_customfield');
        $outputpage = new management($this->cfhandler);

        // This may not fill the screen. Add '.form-control-static {width: 100%}' to custom CSS.
        $html = $output->render($outputpage);

        $mform->addElement('static', 'userlist', get_string('userlist:config:columns', 'mod_cms'), $html);
    }

    /**
     * Add fields to the CMS instance form.
     *
     * @param \moodleform_mod $form
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\moodleform_mod $form, \MoodleQuickForm $mform) {
        $cmsid = $this->cms->get('id');
        $fakeform = new \MoodleQuickForm('a', 'b', 'c');
        $this->cfhandler->instance_form_definition($fakeform, $cmsid);

        $instanceids = $this->get_instance_ids();
        $repeatable = [];
        $repeatoptions = [];
        $repeatno = count($instanceids) ?: self::DEFAULT_NUM_ROWS;

        $fields = $this->cfhandler->get_fields();
        foreach ($fields as $field) {
            $names = $this->get_element_names($field);
            $element = $fakeform->getElement($names->cfelementname);
            $element->setName($names->ulelementname);
            $repeatable[] = $element;

            // Sometimes, the default has to be an integer.
            $default = $field->get_configdata_property('defaultvalue');
            if ($default == (int) $default) {
                $default = (int) $default;
            }
            $repeatoptions[$names->ulelementname] = [
                'default' => $default,
                'type' => $fakeform->getCleanType($names->cfelementname, 0),
            ];
            // Set the rule. Only one rule is allowed.
            if ($field->get_configdata_property('required')) {
                $repeatoptions[$names->ulelementname]['rule'] = 'required';
            }
        }
        $repeatable[] = $mform->createElement('submit', 'delete', 'Remove', [], false);

        // Add a heading.
        $mform->addElement('header', 'heading', get_string('userlist:listdata', 'mod_cms'));

        // Add the repeated elements.
        $form->repeat_elements(
            $repeatable,
            $repeatno,
            $repeatoptions,
            self::FORM_REPEATHIDDENNAME,
            'option_add_fields',
            1,
            null,
            true,
            'delete'
        );
    }

    /**
     * Get extra data needed to add to the form.
     *
     * @param mixed $data
     */
    public function instance_form_default_data(&$data) {
        $instanceids = $this->get_instance_ids();
        foreach ($instanceids as $rownum => $id) {
            $cfdata = new \stdClass();
            $cfdata->id = $id;
            $this->cfhandler->instance_form_before_set_data($cfdata);
            unset($cfdata->id);
            $cfdata = $this->swap_prefix($cfdata, self::CUSTOMFIELD_PREFIX, self::FORM_PREFIX);
            foreach ($cfdata as $name => $value) {
                $key = "{$name}[{$rownum}]";
                $data->$key = $value;
            }
        }
    }

    /**
     * Validate fields added by datasource.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array {
        $objdata = (object) $data;
        $this->from_repeatable($objdata);

        $errors = [];
        foreach ($objdata->data as $count => $group) {
            $datatovalidate = $this->swap_prefix($group, self::FORM_PREFIX, self::CUSTOMFIELD_PREFIX);
            $fielderrors = $this->cfhandler->instance_form_validation((array)$datatovalidate, $files);
            foreach ($fielderrors as $idx => $error) {
                $idx = str_replace(self::CUSTOMFIELD_PREFIX, self::FORM_PREFIX, $idx);
                $errors["{$idx}[{$count}"] = $error;
            }
        }
        return $errors;
    }

    /**
     * Called when an instance is added/updated.
     *
     * @param \stdClass $instancedata
     * @param bool $isnewinstance
     */
    public function update_instance(\stdClass $instancedata, bool $isnewinstance) {
        $this->from_repeatable($instancedata);

        $instanceids = $this->get_instance_ids();

        $numexisting = count($instanceids);

        foreach ($instancedata->data as $count => $group) {
            $datatosave = $this->swap_prefix($group, self::FORM_PREFIX, self::CUSTOMFIELD_PREFIX);
            $datatosave->id = $this->get_instance_id($count);
            $this->cfhandler->instance_form_save($datatosave, $count >= $numexisting);
        }

        // Remove excess.
        $count = count($instancedata->data);
        if ($count < $numexisting) {
            for ($i = $count; $i < $numexisting; ++$i) {
                $this->cfhandler->delete_instance($instanceids[$i]);
            }
            $instanceids = array_slice($instanceids, 0, $count, true);
            $this->cms->set_custom_data('userlistinstanceids', $instanceids);
        }

        $this->update_instance_cache_key();
    }

    /**
     * Convert the form data from the raw format returned by the form into a more usable format where each element group is
     * actually grouped.
     *
     * Data comes in as
     *   {
     *     userlist_name : ['John', 'Andy'],
     *     userlist_age  : [12, 14]
     *   }
     * Converts to
     *  [
     *    { userlist_name : 'John', userlist_age : 12 },
     *    { userlist_name : 'Andy', userlist_age : 14 }
     *  ]
     *
     * @param \stdClass $data Form data as returned by moodleform::get_data().
     */
    protected function from_repeatable(\stdClass $data) {
        $deletehidden = 'delete-hidden';
        $deletehidden = isset($data->$deletehidden) ? $data->$deletehidden : [];

        $columndefs = $this->cfhandler->get_fields();

        $repeathiddenname = self::FORM_REPEATHIDDENNAME;
        $defs = [];
        for ($i = 0; $i < $data->$repeathiddenname; ++$i) {
            if (isset($deletehidden[$i])) {
                continue;
            }
            $obj = new \stdClass();
            $defs[$i] = $obj;
        }

        foreach ($columndefs as $columndef) {
            $names = $this->get_element_names($columndef);
            $formname = $names->ulelementname;
            foreach ($data->$formname as $i => $val) {
                if (isset($deletehidden[$i])) {
                    continue;
                }
                $defs[$i]->$formname = $val;
            }
        }

        $defs = array_values($defs); // Re-sequence array indexes.
        $data->data = $defs;
        $data->numrows = count($defs);
    }

    /**
     * Swaps the prefixes of the data.
     *
     * @param \stdClass $data
     * @param string $fromprefix
     * @param string $toprefix
     * @return \stdClass
     */
    protected function swap_prefix(\stdClass $data, string $fromprefix, string $toprefix): \stdClass {
        $newdata = new \stdClass();
        foreach ($data as $name => $value) {
            $newname = str_replace($fromprefix, $toprefix, $name);
            $newdata->$newname = $value;
        }
        return $newdata;
    }

    /**
     * Called after updating cms type to perform any extra saving required by datasource.
     *
     * @param mixed $data
     */
    public function config_on_update($data) {
        $categories = $this->cfhandler->get_categories_with_fields();
        // Add a category if creating.
        if (count($categories) === 0) {
            $this->cfhandler->create_category();
        }
        $this->update_config_cache_key();
    }

    /**
     * Get configuration data for exporting.
     *
     * @return \stdClass|null
     */
    public function get_for_export(): ?\stdClass {
        $data = new \stdClass();
        $data->columns = [];
        $columndefs = $this->cfhandler->get_fields();
        foreach ($columndefs as $field) {
            $record = $field->to_record();
            $data->columns[] = (object) [
                'name' => $record->name,
                'shortname' => $record->shortname,
                'type' => $record->type,
                'description' => $record->description,
                'descriptionformat' => $record->descriptionformat,
                'sortorder' => $record->sortorder,
                'configdata' => $record->configdata,
            ];
        }
        return $data;
    }

    /**
     * Import configuration from an object.
     *
     * @param \stdClass $data
     */
    public function set_from_import(\stdClass $data) {
        // There needs to be at least one category. So create it if it doesn't exist.
        $categories = $this->cfhandler->get_categories_with_fields();
        if (count($categories) === 0) {
            $catid = $this->cfhandler->create_category();
        } else {
            $catid = array_shift($categories)->get('id');
        }

        if (!empty($data->columns)) {
            foreach ($data->columns as $columndata) {
                $record = clone $columndata;
                $record->categoryid = $catid;
                // Use field rather than field_controller so we can import unsupported fields.
                $field = new field(0, $record);
                $field->save();
            }
        }
        // Reset the handler to ensure that the fields get loaded.
        $this->cfhandler = cmsuserlist_handler::create($this->cms->get('typeid'));
        $this->update_config_cache_key();
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
        $ids = $this->get_instance_ids();
        foreach ($ids as $id) {
            $this->cfhandler->delete_instance($id);
        }
    }

    /**
     * Get the various names for the element.
     *
     * @param field_controller $columndef Custom field containing the definition of the list column elements.
     * @param int $rownum
     *
     * @return \stdClass An object with the various names for the column.
     *  - shortname: The actual name of the column. Use in the mustache template. e.g. 'mytextarea'.
     *  - cfelementname: The form name provided by the custom field. e.g. 'customfield_mytextarea_editor'.
     *  - ulelementname: The form name to be used in the userlist mod instance form. e.g 'userlist_mytextarea_editor'.
     */
    public function get_element_names(field_controller $columndef, int $rownum = 0): \stdClass {
        $names = new \stdClass();
        $datacontroller = data_controller::create(0, (object)['instanceid' => 0], $columndef);
        $names->shortname = $columndef->get('shortname');
        $names->cfelementname = $datacontroller->get_form_element_name();
        $names->ulelementname = str_replace(self::CUSTOMFIELD_PREFIX, self::FORM_PREFIX, $names->cfelementname);
        return $names;
    }

    /**
     * Obtain a unqiue instance ID to use to store the row in the custom field table.
     * Each ID needs to be unique across the cms type.
     *
     * @param int $rownum
     * @return int
     */
    protected function get_instance_id(int $rownum): int {
        $ids = $this->get_instance_ids();
        if (!isset($ids[$rownum])) {
            $ids[$rownum] = $this->get_new_row_id();
            $this->cms->set_custom_data('userlistinstanceids', $ids);
            $this->cms->save();
        }
        return $ids[$rownum];
    }

    /**
     * Get a new ID unique within the CMS type.
     * @return int
     */
    public function get_new_row_id(): int {
        $cmstype = $this->cms->get_type();
        $nextid = $cmstype->get_custom_data('userlistmaxinstanceid') + 1;
        $cmstype->set_custom_data('userlistmaxinstanceid', $nextid);
        $cmstype->save();
        return $nextid;
    }

    /**
     * Gets the custom fields instance IDs for this CMS instance. Indexed by row number.
     *
     * @return array
     */
    protected function get_instance_ids(): array {
        $ids = $this->cms->get_custom_data('userlistinstanceids');
        if (is_null($ids)) {
            $ids = [];
        }
        return (array) $ids;
    }

    /**
     * Create a structure of the config for backup.
     *
     * @param \backup_nested_element $element
     */
    public function config_backup_define_structure(\backup_nested_element $element) {
    }

    /**
     * Create a structure of the instance for backup.
     *
     * @param \backup_nested_element $parent
     */
    public function instance_backup_define_structure(\backup_nested_element $parent) {
        $userlist = new \backup_nested_element('userlist');
        $parent->add_child($userlist);

        $rows = new \backup_nested_element('userlistrows');
        $row = new \backup_nested_element('userlistrow', ['id'], ['dataids']);
        $userlist->add_child($rows);
        $rows->add_child($row);

        $fields = new \backup_nested_element('userlistfields');
        $field = new \backup_nested_element('userlistfield', ['id'], ['shortname', 'type', 'value', 'valueformat']);
        $userlist->add_child($fields);
        $fields->add_child($field);

        $fieldsforbackup = [];
        $instanceids = [];
        foreach ($this->get_instance_ids() as $id) {
            $fielddata = $this->cfhandler->get_instance_data_for_backup($id);
            $instanceids[] = [
                'id' => $id,
                'dataids' => implode(
                    ',',
                    array_map(
                        function ($arr) {
                            return $arr['id'];
                        },
                        $fielddata
                    )
                )
            ];
            $fieldsforbackup = array_merge($fieldsforbackup, $this->cfhandler->get_instance_data_for_backup($id));
        }
        $row->set_source_array($instanceids);
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
        $processor = new restore\userlist($stepslib);

        $element = new \restore_path_element('restore_ds_userlist_row',
                '/activity/cms/instance_datasources/userlist/userlistrows/userlistrow');
        $element->set_processing_object($processor);
        $paths[] = $element;

        $element = new \restore_path_element('restore_ds_userlist_field',
                '/activity/cms/instance_datasources/userlist/userlistfields/userlistfield');
        $element->set_processing_object($processor);
        $paths[] = $element;

        return $paths;
    }
}
