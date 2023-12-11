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

namespace mod_cms\customfield;

use core_customfield\{api, field_controller, handler};
use mod_cms\local\model\cms_types;
use mod_cms\local\datasource\fields;

/**
 * Custom field handler for cms types.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmsfield_handler extends handler {
    use cms_restore;

    /**
     * Context that should be used for new categories created by this handler.
     *
     * @return \context
     */
    public function get_configuration_context(): \context {
        // TODO caching.
        return \context_system::instance();
    }

    /**
     * URL for configuration of the fields on this handler.
     *
     * @return \moodle_url
     */
    public function get_configuration_url(): \moodle_url {
        return new \moodle_url('/mod/cms/customfield.php', ['itemid' => $this->get_itemid()]);
    }

    /**
     * Context that should be used for data stored for the given record.
     * Uses the activity context.
     *
     * @param int $instanceid id of the instance or 0 if the instance is being created
     * @return \context
     */
    public function get_instance_context(int $instanceid = 0): \context {
        $modinfo = get_coursemodule_from_instance('cms', $instanceid);
        if ($modinfo) {
            return \context_module::instance($modinfo->id);
        }
        return \context_system::instance();
    }

    /**
     * The current user can configure custom fields on this component.
     *
     * @return bool
     */
    public function can_configure(): bool {
        return has_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * The current user can edit given custom fields on the given instance.
     *
     * Called to filter list of fields displayed on the instance edit form.
     *
     * Capability to edit/create instance is checked separately.
     *
     * @param field_controller $field
     * @param int $instanceid id of the instance or 0 if the instance is being created
     * @return bool
     */
    public function can_edit(field_controller $field, int $instanceid = 0): bool {
        return true;
    }

    /**
     * The current user can view the value of the custom field for a given custom field and instance.
     *
     * Called to filter list of fields returned by methods get_instance_data(), get_instances_data(),
     * export_instance_data(), export_instance_data_object()
     *
     * Access to the instance itself is checked by handler before calling these methods
     *
     * @param field_controller $field
     * @param int $instanceid
     * @return bool
     */
    public function can_view(field_controller $field, int $instanceid): bool {
        return true;
    }

    /**
     * Clears the cache.
     * This is called whenever the configuration of the custom fields is changed, so this serves as a poor man's 'on_update'.
     */
    protected function clear_configuration_cache() {
        parent::clear_configuration_cache();
        $cmstype = new cms_types($this->get_itemid());
        $fields = new fields($cmstype->get_sample_cms());
        $fields->update_config_cache_key();
    }
}
