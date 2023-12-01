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

/**
 * A trait to implement functions that make use of revision based cache keys.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait revcache {
    /**
     * Update the cache key fragment for the instance.
     */
    public function update_instance_cache_key() {
        $name = self::get_shortname() . 'instancerev';
        $cacherev = $this->cms->get_custom_data($name) ?? 0;
        $this->cms->set_custom_data($name, ++$cacherev);
        $this->cms->save();
    }

    /**
     * Returns the cache key fragment for the instance data.
     * If null, then caching should be avoided, both here and for the overall instance.
     *
     * @return string|null
     */
    public function get_instance_cache_key(): ?string {
        $name = self::get_shortname() . 'instancerev';
        $cacherev = 0;
        if (!empty($this->cms->get('id'))) {
            $this->cms->read();
            $cacherev = $this->cms->get_custom_data($name);
            // We expect there to be something, so false, null, '', and 0 are all illigit.
            if (empty($cacherev)) {
                debugging('Trying to gain an instance cache key for ' . $this->cms->get('id') . ' without it being made.');
                $this->update_instance_cache_key();
                $cacherev = $this->cms->get_custom_data($name);
            }
        }
        // Combine with the ID to avoid clashes between instances.
        return $this->cms->get('id') . 'o' . $cacherev;
    }
}
