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
use mod_cms\local\model\{cms, cms_types, cms_userlist, cms_userlist_columns};

/**
 * User designed lists
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userlist extends base {

    const CONFIG_URL = '/mod/cms/userlist.php';
    const ACTION_ICON = 't/grades';

    const DEFAULT_NUM_ROWS = 2;
    const FORM_PREFIX = 'userlist_';

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
        }

        return $data;
    }

    protected function get_sample(cms_userlist_columns $columns) {
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

        // Add a link to the config url page.
    }

    /**
     * Return a action link to add to the CMS type table.
     *
     * @return string|null
     */
    public function config_action_link(): ?string {
        // Link for custom fields.
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
        $columndef = $listdef->get('columndefs');
        $list = null;

        $repeatable = [];
        $repeatoptions = [];
        $repeatno = self::DEFAULT_NUM_ROWS;

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

        $mform->addElement('header', 'heading', 'List data');

        $form->repeat_elements(
            $repeatable,
            $repeatno,
            $repeatoptions,
            'option_repeats',
            'option_add_fields',
            1,
            null,
            true,
            'delete',
        );

    }

    /**
     * Validate fields added by datasource.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array {
        return [];
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
    }

    /**
     * Convert the form data to something settable to the persistent.
     *
     * @param \stdClass $data
     */
    protected function convert(\stdClass $data) {
        $deletehidden = 'delete-hidden';
        $deletehidden = isset($data->$deletehidden) ? $data->$deletehidden : [];

        $listdef = cms_userlist_columns::get_from_typeid($this->cms->get('typeid'));
        $columndefs = $listdef->get('columndefs');

        $defs = [];
        for ($i = 0; $i < $data->option_repeats; ++$i) {
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
     * Returns a hash of the content, representing the data stored for the datasource.
     *
     * @return string
     */
    public function get_content_hash(): string { return ''; }
}
