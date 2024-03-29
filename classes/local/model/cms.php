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
use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\lib;

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
            'customdata' => [
                'type' => PARAM_TEXT,
                'default' => '{}',
            ],
        ];
    }

    /**
     * Returns a hash representing the contents of the CMS.
     * Includes hashes for the CMS type and the datasources as well, as they
     * contribute to what gets displayed.
     *
     * @deprecated
     * @return string
     * @throws \moodle_exception
     */
    public function get_content_hash(): string {
        throw new \moodle_exception('This method is deprecated. Use get_cache_key instead');
    }

    /**
     * Creates and returns a cache key. This key is a hash of the combination of the
     * datasources, the CMS type and the CMS data.
     *
     * @return string|null
     */
    public function get_cache_key(): ?string {
        $key = '';
        foreach (dsbase::get_datasources($this) as $ds) {
            $dskey = $ds->get_full_cache_key();
            if (is_null($dskey)) {
                return null;
            }
            $key .= $dskey;
        }

        $key .= serialize($this->get_type()->to_record());
        $key .= serialize($this->to_record());

        // Return a hash to keep the key length to an acceptable size.
        return hash(lib::HASH_ALGO, $key);
    }

    /**
     * Sets an arbitrary value.
     *
     * @param string $name
     * @param mixed $value
     */
    public function set_custom_data(string $name, $value) {
        $cdata = json_decode($this->raw_get('customdata'), false);
        if (is_null($cdata)) {
            $cdata = new \stdClass();
        }
        // If the value is null, then let's remove the value entirely.
        if (is_null($value)) {
            unset($cdata->$name);
        } else {
            $cdata->$name = $value;
        }
        $this->raw_set('customdata', json_encode($cdata));
    }

    /**
     * Retrieves an arbitrary value.
     *
     * @param string $name
     * @return mixed
     */
    public function get_custom_data(string $name) {
        $cdata = json_decode($this->raw_get('customdata'), false);
        return $cdata->$name ?? null;
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
