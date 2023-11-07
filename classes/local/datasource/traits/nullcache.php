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
 * A trait to implement for any datasource that uses null cache keys.
 * A datasource that implements this does not use caches. In addition, it 'vetos' the
 * instance from caching the rendered content.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait nullcache {
    /**
     * Get the cache used by the datasource.
     * For null caching, this returns null.
     *
     * @return \cache|null
     */
    public function get_cache(): ?\cache {
        return null;
    }

    /**
     * Returns the cache key fragment for the instance data.
     * If null, then caching should be avoided, both here and for the overall instance.
     *
     * @return string|null
     */
    public function get_instance_cache_key(): ?string {
        return null;
    }

    /**
     * Returns the cache key fragment for the config.
     * If null, then caching should be avoided, both here and for the overall instance.
     *
     * @return string|null
     */
    public function get_config_cache_key(): ?string {
        return null;
    }

    /**
     * Gets the current cache key used for this datasource for this instance. It concatenates the instance and config keys.
     * If either key is null, then this function returns null.
     *
     * @return string|null
     */
    public function get_full_cache_key(): ?string {
        return null;
    }

    /** Updates the config cache key fragment. */
    public function update_config_cache_key() {
        // Do nothing as caching is not used.
    }
}
