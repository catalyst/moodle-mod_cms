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

/**
 * Data source for basic site metadata.
 *
 * This datasource does not use caching, but does not use nullcache. This is because the data returned is constant and small. It
 * does not affect the caching done by the CMS, so we do not use nullcache. We do not use a cache because it would not be faster.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site extends base_mod_cms {
    /**
     * Get the display name.
     *
     * @return string
     */
    public static function get_displayname(): string {
        return get_string('site:displayname', 'mod_cms');
    }

    /**
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        global $CFG, $SITE;

        return (object) [
            'fullname'  => $SITE->fullname,
            'shortname' => $SITE->shortname,
            'wwwroot'   => $CFG->wwwroot,
        ];
    }

    /**
     * Can this datasource be disabled?
     *
     * @return false
     */
    public static function is_optional(): bool {
        return false;
    }

    /**
     * Constructs the data structure to act as the data source. Uses a cache.
     *
     * @return \stdClass
     * @throws \coding_exception
     */
    public function get_cached_data(): \stdClass {
        // Because the data is constant, and small, using a cache would be counter-productive.
        return $this->get_data();
    }

    /**
     * Returns the cache key fragment for the instance data.
     * If null, then caching should be avoided, both here and for the overall instance.
     *
     * @return string|null
     */
    public function get_instance_cache_key(): ?string {
        // Returns a constant, because caching is not used in this datasource, but still used for the CMS.
        return '';
    }

    /**
     * Returns the cache key fragment for the config.
     * If null, then caching should be avoided, both here and for the overall instance.
     *
     * @return string|null
     */
    public function get_config_cache_key(): ?string {
        // Returns a constant, because caching is not used in this datasource, but still used for the CMS.
        return '';
    }

    /**
     * Gets the current cache key used for this datasource for this instance.
     *
     * @return string|null
     */
    public function get_full_cache_key(): ?string {
        // Returns a constant, because caching is not used in this datasource.
        return '';
    }
}
