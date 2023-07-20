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

use core_customfield\field;
use core_customfield\output\management;
use mod_cms\customfield\cmsuserlist_handler;
use mod_cms\helper;
use mod_cms\local\lib;
use mod_cms\local\model\{cms, cms_userlist, cms_userlist_columns};

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
class userlist extends base {

    /** URL for the config page. */
    const CONFIG_URL = '/mod/cms/userlist.php';
    /** Icon to use for the link in the table list. */
    const ACTION_ICON = 't/grades';

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
            $list = cms_userlist::get_from_cmsid($this->cms->get('id'));
            if (isset($list)) {
                $data->data = $list->get('data');
            }
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

        $output = $PAGE->get_renderer('core_customfield');
        $outputpage = new management($this->cfhandler);

        // This may not fill the screen. Add '.form-control-static {width: 100%}' to custom CSS.
        $html = $output->render($outputpage);

        $mform->addElement('static', 'userlist', 'List columns', $html);
    }

    /**
     * Add fields to the CMS instance form.
     *
     * @param \moodleform_mod $form
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\moodleform_mod $form, \MoodleQuickForm $mform) {
        $fakeform = new \MoodleQuickForm('a', 'b', 'c');
        $this->cfhandler->instance_form_definition($fakeform, $this->cms->get('id'));
        $list = cms_userlist::get_from_cmsid($this->cms->get('id'));

        $repeatable = [];
        $repeatoptions = [];
        $repeatno = $list ? $list->get('numrows') : self::DEFAULT_NUM_ROWS;

        $fields = $this->cfhandler->get_fields();
        foreach ($fields as $field) {
            $actualname = self::FORM_PREFIX . $field->get('shortname');
            $fakename = self::CUSTOMFIELD_PREFIX . $field->get('shortname');
            $element = $fakeform->getElement($fakename);
            $element->setName($actualname);
            $repeatable[] = $element;

            // Sometimes, the default has to be an integer.
            $default = $field->get_configdata_property('defaultvalue');
            if ($default == (int) $default) {
                $default = (int) $default;
            }
            $repeatoptions[$actualname] = [
                'default' => $default,
                'type' => $fakeform->getCleanType($fakename, 0),
            ];
            // Set the rule. Only one rule is allowed.
            if ($field->get_configdata_property('required')) {
                $repeatoptions[$actualname]['rule'] = 'required';
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
     * @param mixed $data
     */
    public function instance_form_default_data(&$data) {
        $list = cms_userlist::get_from_cmsid($this->cms->get('id'));

        // Extract the values of the column defs and convert into the format that the form requires.
        $listdata = $list->get('data');
        foreach ($listdata as $count => $row) {
            foreach ($row as $idx => $val) {
                $key = self::FORM_PREFIX . "{$idx}[{$count}]";
                $data->$key = $val;
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
        $this->convert($objdata, self::CUSTOMFIELD_PREFIX);

        $errors = [];
        foreach ($objdata->data as $count => $group) {
            $fielderrors = $this->cfhandler->instance_form_validation((array)$group, $files);
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
        $list = cms_userlist::get_from_cmsid($instancedata->id);
        if ($list === null) {
            $list = new cms_userlist();
            $list->set('cmsid', $instancedata->id);
            $list->set('typeid', $this->cms->get('typeid'));
        }

        $this->convert($instancedata);
        $list->set('data', $instancedata->data);
        $list->set('numrows', $instancedata->numrows);
        $list->save();

        // Update hash.
        $hash = hash(lib::HASH_ALGO, serialize($this->get_data()));
        // The content hash is stored as a part fo the cms.
        $this->cms->set_custom_data('userlistinstancehash', $hash);
        $this->cms->save();
    }

    /**
     * Convert the form data to something settable to the persistent.
     * Data comes in as
     *   {
     *     userlist_name : ['John', 'Andy'],
     *     userlist_age  : [12, 14]
     *   }
     * Converts to
     *  [
     *    { name : 'John', age : 12 },
     *    { name : 'Andy', age : 14 }
     *  ]
     *
     * @param \stdClass $data Form data as returned by moodleform::get_data().
     * @param string $prefix A prefix to put at the front of names when adding to data.
     */
    protected function convert(\stdClass $data, $prefix = '') {
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
            $name = $columndef->get('shortname');
            $formname = self::FORM_PREFIX . $name;
            foreach ($data->$formname as $i => $val) {
                if (isset($deletehidden[$i])) {
                    continue;
                }
                $savename = $prefix . $name;
                $defs[$i]->$savename = $val;
            }
        }

        // Re-sequence array indexes.
        $defs = array_values($defs);

        $data->data = $defs;
        $data->numrows = count($defs);
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
        // Create a dummy category to hold the fields.
        $catid = $this->cfhandler->create_category('');
        if (!empty($data->columns)) {
            foreach ($data->columns as $columndata) {
                $record = clone $columndata;
                $record->categoryid = $catid;
                // Use field rather than field_controller so we can import unsupported fields.
                $field = new field(0, $record);
                $field->save();
            }
        }
    }

    /**
     * Returns a hash of the content, representing the data stored for the datasource.
     *
     * @return string
     */
    public function get_content_hash(): string {
        // Hash is stored in the DB with the cms, so gets returned by cms::get_content_hash().
        return '';
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
        $list = cms_userlist::get_from_cmsid($this->cms->get('id'));
        if (isset($list)) {
            $list->delete();
        }
    }
}
