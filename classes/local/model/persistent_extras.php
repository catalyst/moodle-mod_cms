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

/**
 * A trait to provide extra functions for use with persistent derived classes.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait persistent_extras {
    /**
     * Clean a record to be able to pass it to from_record(). This is needed for V3.10 and earlier.
     *
     * @param \stdClass $from
     * @return \stdClass
     */
    public static function clean_record(\stdClass $from): \stdClass {
        $to = new \stdClass();
        $properties = array_keys(static::define_properties());
        foreach ($from as $key => $value) {
            if (in_array($key, $properties)) {
                $to->$key = $value;
            }
        }

        return $to;
    }
}
