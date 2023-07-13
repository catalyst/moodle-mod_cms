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

use mod_cms\helper;
use mod_cms\local\lib;
use mod_cms\local\model\{cms_userlist, cms_userlist_columns};

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
    /** Name used for the repeat hidden name (repeat counts). */
    const FORM_REPEATHIDDENNAME = self::FORM_PREFIX . 'option_repeats';

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
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        $data = new \stdClass();
        $columns = cms_userlist_columns::get_from_typeid($this->cms->get('typeid'));
        if ($columns !== null) {
            $data->numcolumns = $columns->get('numcolumns');
            $data->columns = $columns->get('columndefs');

            if ($this->cms->issample) {
                $data->data = $this->get_sample($columns);
            } else {
                $list = cms_userlist::get_from_cmsid($this->cms->get('id'));
                $data->data = $list->get('data');
            }
        } else {
            $data->numcolumns = 0;
            $data->columns = [];
        }

        return $data;
    }

    /**
     * Get a sample list for display on the config page.
     *
     * @param cms_userlist_columns $columns
     * @return array
     */
    protected function get_sample(cms_userlist_columns $columns): array {
        $rows = 2;

        $data = [];
        for ($i = 0; $i < $rows; ++$i) {
            $row = new \stdClass();
            foreach ($columns->get('columndefs') as $coldef) {
                $name = $coldef->shortname;
                $row->$name = 'A';
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
        global $CFG;

        // TODO: Add a link to the config url page?
    }

    /**
     * Return a action link to add to the CMS type table.
     *
     * @return string|null
     */
    public function config_action_link(): ?string {
        return helper::format_icon_link(
            new \moodle_url(self::CONFIG_URL, ['typeid' => $this->cms->get('typeid')]),
            self::ACTION_ICON,
            get_string('userlist:displayname', 'mod_cms'),
            null
        );
    }

    /**
     * Add fields to the CMS instance form.
     *
     * @param \moodleform_mod $form
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\moodleform_mod $form, \MoodleQuickForm $mform) {
        $listdef = cms_userlist_columns::get_from_typeid($this->cms->get('typeid'));
        $list = cms_userlist::get_from_cmsid($this->cms->get('id'));
        $columndef = $listdef->get('columndefs');

        $repeatable = [];
        $repeatoptions = [];
        $repeatno = $list ? $list->get('numrows') : self::DEFAULT_NUM_ROWS;

        // Put together the list row elements based on column definitions.
        foreach ($columndef as $column) {
            $repeatable[] = $mform->createElement('text', self::FORM_PREFIX . $column->shortname, $column->label);
        }
        $repeatable[] = $mform->createElement('submit', 'delete', 'Remove', [], false);

        foreach ($columndef as $column) {
            $repeatoptions[self::FORM_PREFIX . $column->shortname] = [
                'default' => '',
                'type' => PARAM_TEXT,
            ];
        }

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
     *
     * @param \stdClass $data Form data as returned by moodleform::get_data().
     */
    protected function convert(\stdClass $data) {
        $deletehidden = 'delete-hidden';
        $deletehidden = isset($data->$deletehidden) ? $data->$deletehidden : [];

        $listdef = cms_userlist_columns::get_from_typeid($this->cms->get('typeid'));
        $columndefs = $listdef->get('columndefs');

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
            $name = $columndef->shortname;
            $formname = self::FORM_PREFIX . $name;
            foreach ($data->$formname as $i => $val) {
                if (isset($deletehidden[$i])) {
                    continue;
                }
                $defs[$i]->$name = $val;
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

        $listdef = cms_userlist_columns::get_from_typeid($this->cms->get('typeid'));

        $data->columndefs = $listdef->get('columndefs');
        return $data;
    }

    /**
     * Import configuration from an object.
     *
     * @param \stdClass $data
     */
    public function set_from_import(\stdClass $data) {
        $listdef = new cms_userlist_columns();
        $listdef->set('typeid', $this->cms->get('typeid'));
        $listdef->set('numcolumns', count($data->columndefs));
        $listdef->set('columndefs', $data->columndefs);
        $listdef->save();
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
        $listdef = cms_userlist_columns::get_from_typeid($this->cms->get('typeid'));
        if (isset($listdef)) {
            $listdef->delete();
        }
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
