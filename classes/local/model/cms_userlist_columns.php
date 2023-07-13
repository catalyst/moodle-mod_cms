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

namespace mod_cms\local\model;

use core\persistent;

/**
 * Column definition for the user list.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_userlist_columns extends persistent {
    /**
     * Table name.
     */
    const TABLE = 'cms_userlist_columns';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'typeid' => [
                'type' => PARAM_INT,
                'default' => '0'
            ],
            'numcolumns' => [
                'type' => PARAM_INT,
                'default' => '0'
            ],
            'columndefs' => [
                'type' => PARAM_RAW,
                'default' => '[]'
            ],
        ];
    }

    /**
     * Getter for columndefs.
     *
     * @return array
     */
    protected function get_columndefs(): array {
        return json_decode($this->raw_get('columndefs'));
    }

    /**
     * Setter for columndefs.
     *
     * @param array $value
     */
    protected function set_columndefs(array $value) {
        $this->raw_set('columndefs', json_encode($value));
    }

    /**
     * Gets a cms_userlist_columns from the type ID. Returns null if not found.
     *
     * @param int $typeid
     * @return cms_userlist_columns|null
     */
    public static function get_from_typeid(int $typeid): ?cms_userlist_columns {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['typeid' => $typeid]);
        if ($record === false) {
            return null;
        }

        return new cms_userlist_columns(0, $record);
    }
}
