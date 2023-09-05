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

namespace mod_cms\local\datasource\restore;

use mod_cms\local\datasource\roles as dsroles;
use mod_cms\local\model\cms_types;

/**
 * Class to handle restoring of roles datasource backups.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roles {
    /** @var \restore_cms_activity_structure_step The stepslib controlling this process. */
    protected $stepslib;

    /**
     * Constructs the processor.
     *
     * @param \restore_cms_activity_structure_step $stepslib
     */
    public function __construct(\restore_cms_activity_structure_step $stepslib) {
        $this->stepslib = $stepslib;
    }

    /**
     * Restores the roles data.
     *
     * @param array $data
     */
    public function process_restore_ds_roles(array $data) {
        $newtypeid = $this->stepslib->get_new_parentid('cms_types');

        // Restore the roles information to the CMS type record.
        $cmstype = new cms_types($newtypeid);
        $data = (object) $data;
        $data->list = explode(',', $data->list);
        $cmstype->set_custom_data('roles_config', $data);
        $cmstype->save();
    }
}
