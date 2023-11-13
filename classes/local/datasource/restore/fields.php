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

use mod_cms\customfield\cmsfield_handler;
use mod_cms\local\datasource\fields as dsfields;
use mod_cms\local\model\cms;

/**
 * Restore processor for fields.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields {
    /** @var \restore_cms_activity_structure_step The stepslib controlling this process. */
    protected $stepslib;

    /**
     * Constructs the processor.
     *
     * @param \restore_cms_activity_structure_step $stepslib
     */
    public function __construct(\restore_cms_activity_structure_step $stepslib) {
        $this->stepslib = $stepslib;
        $stepslib->add_to_after_party($this);
    }

    /**
     * Restore custom field data.
     *
     * @param array $data
     */
    public function process_restore_ds_fields(array $data) {
        $this->components[] = 'customfield_' . $data['type'];

        $cmsid = $this->stepslib->get_new_parentid('cms');
        $cms = new cms($cmsid);

        // Add data to instance.
        $handler = cmsfield_handler::create($cms->get('typeid'));
        $handler->cms_restore_instance_data_from_backup($this->stepslib->get_task(), $data, $cmsid);
    }

    /**
     * Code to be run after restoration.
     */
    public function after_execute() {
        $cmsid = $this->stepslib->get_new_parentid('cms');
        $cms = new cms($cmsid);
        $ds = new dsfields($cms);
        if ($ds->is_enabled()) {
            $ds->update_instance_cache_key();
        }
    }
}
