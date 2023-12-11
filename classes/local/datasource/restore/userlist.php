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

use mod_cms\customfield\cmsuserlist_handler;
use mod_cms\local\datasource\userlist as dsuserlist;
use mod_cms\local\model\cms;

/**
 * Restore processor for userlist.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userlist {
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
     * Restores the row information about the userlist.
     *
     * @param array $data
     */
    public function process_restore_ds_userlist_row(array $data) {
        $cmsid = $this->stepslib->get_new_parentid('cms');
        $cms = new cms($cmsid);

        // Get a new row ID from the cms type.
        $ds = new dsuserlist($cms);
        $newid = $ds->get_new_row_id();

        // Update the row IDs.
        $instanceids = $cms->get_custom_data('userlistinstanceids');
        $instanceids[] = $newid;
        $cms->set_custom_data('userlistinstanceids', $instanceids);
        $cms->save();

        $dataids = explode(',', $data['dataids']);
        foreach ($dataids as $id) {
            $this->stepslib->set_mapping('cms_userlist_field_row', $id, $newid);
        }
    }

    /**
     * Restores the customfield data for the userlist.
     *
     * @param array $data
     */
    public function process_restore_ds_userlist_field(array $data) {
        $oldid = $data['id'];
        $rowid = $this->stepslib->get_mappingid('cms_userlist_field_row', $oldid);

        // Add data to instance. Get $newid.
        $cmsid = $this->stepslib->get_new_parentid('cms');
        $cms = new cms($cmsid);
        $handler = cmsuserlist_handler::create($cms->get('typeid'));
        $handler->cms = $cms;
        $newid = $handler->cms_restore_instance_data_from_backup($this->stepslib->get_task(), $data, $rowid);
        if ($newid && method_exists($handler, 'restore_define_structure')) {
            $handler->restore_define_structure($this->stepslib, $newid, $data['id']);
        }

        $this->stepslib->set_mapping('cms_userlist_field', $oldid, $newid, true, $handler->get_instance_context()->id);
    }

    /**
     * Code to be run after restoration.
     */
    public function after_execute() {
        $cmsid = $this->stepslib->get_new_parentid('cms');
        $cms = new cms($cmsid);
        $ds = new dsuserlist($cms);
        if ($ds->is_enabled()) {
            $ds->update_instance_cache_key();
        }
    }
}
