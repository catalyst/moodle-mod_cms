<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * A persistent for the cms table.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_cms\local\model;

use core\persistent;

/**
 * A persistent for the cms table.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms extends persistent {

    /**
     * Table name.
     */
    const TABLE = 'cms';

    /** @var bool Is true if this is a sample CMS. */
    public $issample = false;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'course' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'intro' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
            ],
            'introformat' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'typeid' => [
                'type' => PARAM_INT,
            ],
            'grade' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Hook to execute after an update.
     *
     * @param bool $result
     */
    protected function after_update($result): void {
        $this->reset_cache();
    }

    /**
     * Hook to execute after a delete.
     *
     * @param bool $result
     */
    protected function after_delete($result): void {
        $this->reset_cache();
    }

    /**
     * Resets the cache to ensure content gets remade.
     */
    protected function reset_cache() {
        $hashcache = \cache::make('mod_cms', 'datasource_keys');
        $hashcache->delete('super_hash_' . $this->get('id'));
    }

    /**
     * Gets the type object for this cms.
     *
     * @return cms_types
     */
    public function get_type(): cms_types {
        return new cms_types($this->get('typeid'));
    }
}
