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

namespace mod_cms\local\datasource\traits;

use mod_cms\local\lib;

/**
 * A trait to implement functions that make use of hash based cache keys.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait hashcache {
    /**
     * Update the cache key fragment for the instance.
     */
    public function update_instance_cache_key() {
        $data = $this->get_data();

        $hash = hash(lib::HASH_ALGO, serialize($data));
        $this->cms->set_custom_data(self::get_shortname() . 'instancehash', $hash);
        $this->cms->save();

        // Since we have the data, we may as well save it into the cache now.
        $key = $this->get_full_cache_key();
        $cache = $this->get_cache();
        if (!$cache->has($key)) {
            $cache->set($key, $data);
        }
    }

    /**
     * Returns the cache key fragment for the instance data.
     * If null, then caching should be avoided, both here and for the overall instance.
     *
     * @return string|null
     */
    public function get_instance_cache_key(): ?string {
        $key = '';
        // We test for an ID because temporary cms instances (i.e. sample cms) do not have caching.
        if (!empty($this->cms->get('id'))) {
            // By default, all stored instances are expected to have a stored hash to be used as a key. If one is not stored,
            // then an exception will be thrown.
            // If a datasource doesn't use caching (either returning '' or null), then this method should be overridden.
            $this->cms->read();
            $key = $this->cms->get_custom_data(self::get_shortname() . 'instancehash');
            // We expect there to be something, so false, null, '', and 0 are all illigit.
            if (empty($key)) {
                debugging('Trying to gain an instance cache key for ' . $this->cms->get('id') . ' without it being made.');
                $this->update_instance_cache_key();
                $key = $this->cms->get_custom_data(self::get_shortname() . 'instancehash');
            }
        }
        return $key;
    }
}
