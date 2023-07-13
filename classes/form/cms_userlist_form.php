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

namespace mod_cms\form;

use core\form\persistent as persistent_form;

/**
 * Userlist column definition form.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_userlist_form extends persistent_form {

    /** Default number of column definitions to put into the form. */
    public const DEFAULT_NUM_COLUMNS = 2;

    /** @var string Persistent class name. */
    protected static $persistentclass = 'mod_cms\\local\\model\\cms_userlist_columns';

    /**
     * Build form for importing workflows.
     *
     * {@inheritDoc}
     * @see \moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;

        // These are the elements in each group.
        $repeatable = [
            $mform->createElement('text', 'shortname', 'Name'),
            $mform->createElement('text', 'label', 'Label'),
            $mform->createElement('submit', 'delete', 'Remove', [], false),
        ];

        $repeatoptions = [
            'shortname' => [
                'default' => '',
                'type' => PARAM_ALPHANUMEXT,
                // TODO: I Want to set a required rule here, but setting one rule will
                // cause a complaint if you remove an element group.
            ],
            'label' => [
                'default' => '',
                'type' => PARAM_TEXT,
            ],
        ];

        $listdef = $this->get_persistent();
        $repeatno = $listdef ? $listdef->get('numcolumns') : self::DEFAULT_NUM_COLUMNS;

        $this->repeat_elements(
            $repeatable,
            $repeatno,
            $repeatoptions,
            'option_repeats',
            'option_add_fields',
            1,
            null,
            true,
            'delete'
        );

        $this->add_action_buttons();
    }

    /**
     * {@inheritDoc}
     */
    protected function get_default_data() {
        $data = parent::get_default_data();

        // Extract the values of the column defs and convert into the format that the form requires.
        $defs = $this->get_persistent()->get('columndefs');
        foreach ($defs as $count => $def) {
            foreach ($def as $idx => $val) {
                $key = "{$idx}[{$count}]";
                $data->$key = $val;
            }
        }
        return $data;
    }

    /**
     * Convert some fields.
     *
     * @param  \stdClass $data The whole data set.
     * @return \stdClass The amended data set.
     */
    protected static function convert_fields(\stdClass $data) {
        $data = parent::convert_fields($data);
        $deletehidden = 'delete-hidden';
        $deletehidden = isset($data->$deletehidden) ? $data->$deletehidden : [];

        // Create the objects to contain the column defs, leaving out the ones that have been deleted.
        $defs = [];
        for ($i = 0; $i < $data->option_repeats; ++$i) {
            if (isset($deletehidden[$i])) {
                continue;
            }
            $obj = new \stdClass();
            $defs[$i] = $obj;
        }

        // Fill the objects, again leaving out the oned being deleted.
        foreach (['shortname', 'label'] as $name) {
            foreach ($data->$name as $i => $val) {
                if (isset($deletehidden[$i])) {
                    continue;
                }
                $defs[$i]->$name = $val;
            }
        }

        // Re-sequence array indexes.
        $defs = array_values($defs);

        $data->columndefs = json_encode($defs);
        $data->numcolumns = count($defs);
        return $data;
    }
}
