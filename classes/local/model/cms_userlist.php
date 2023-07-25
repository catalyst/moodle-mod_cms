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
 * Row definitions for user list.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_userlist extends persistent {
    /**
     * Table name.
     */
    const TABLE = 'cms_userlists';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'cmsid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'typeid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'numrows' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'data' => [
                'type' => PARAM_RAW,
                'default' => '[]',
            ],
        ];
    }

    /**
     * Getter for list data.
     *
     * @return array The list as and array of objects.
     */
    protected function get_data(): array {
        return json_decode($this->raw_get('data'));
    }

    /**
     * Setter for list data.
     *
     * @param array $value The list os an array of object (or associative arrays).
     * @throws \coding_exception
     */
    protected function set_data(array $value) {
        $this->raw_set('data', json_encode($value));
    }

    /**
     * Get a record based on the CMS item ID.
     *
     * @param int $cmsid
     * @return cms_userlist|null
     */
    public static function get_from_cmsid(int $cmsid): ?cms_userlist {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['cmsid' => $cmsid]);
        if ($record === false) {
            return null;
        }

        return new cms_userlist(0, $record);
    }
}
