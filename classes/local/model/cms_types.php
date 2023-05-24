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
 * A persistent for the mdl_cms_types table
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_cms\local\model;

use stdClass;
use context_system;
use core\persistent;
use mod_cms\event\cms_type_created;
use mod_cms\event\cms_type_updated;
use mod_cms\event\cms_type_deleted;

/**
 * A persistent for the mdl_cms_types table
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_types extends persistent {

    /**
     * Table name.
     */
    const TABLE = 'cms_types';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'description' => [
                'type' => PARAM_RAW,
                'default' => ''
            ],
            'descriptionformat' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
        ];
    }

    /**
     * Hook to execute after a create to trigger an event.
     *
     * @return void
     */
    protected function after_create(): void {
        $event = cms_type_created::create([
            'objectid' => $this->raw_get('id'),
            'userid' => $this->raw_get('usermodified'),
            'contextid' => context_system::instance()->id,
        ]);
        $event->trigger();
    }

    /**
     * Hook to execute after an update to trigger an event.
     *
     * @param bool $result
     *
     * @return void
     */
    protected function after_update($result): void {
        if ($result) {
            $event = cms_type_updated::create([
                'objectid' => $this->raw_get('id'),
                'userid' => $this->raw_get('usermodified'),
                'contextid' => context_system::instance()->id,
            ]);
            $event->trigger();
        }
    }

    /**
     * Hook to execute after a delete to trigger an event.
     *
     * @param bool $result
     *
     * @return void
     */
    protected function after_delete($result): void {
        if ($result) {
            $event = cms_type_deleted::create([
                'objectid' => $this->raw_get('id'),
                'userid' => $this->raw_get('usermodified'),
                'contextid' => context_system::instance()->id,
            ]);
            $event->trigger();
        }
    }

    /**
     * Whether or not we can delete this instance.
     *
     * @return bool
     */
    public function can_delete(): bool {
        return true;
    }
}
