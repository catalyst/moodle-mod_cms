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

use mod_cms\local\lib;
use mod_cms\local\model\cms;
use mod_cms\local\model\cms_types;

/**
 * Base class for mod_cms defined data sources.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_mod_cms extends base {
    /**
     * Get the cache used by the datasource.
     *
     * @return \cache
     */
    public function get_cache(): \cache {
        return \cache::make('mod_cms', 'cms_content_' . self::get_shortname());
    }
}
