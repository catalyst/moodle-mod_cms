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

use core_customfield\api;

/**
 * A trait to hold the restore function used by all handlers defined in this plugin.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait cms_restore {
    /**
     * Creates or updates custom field data. An improved version of course_handler::restore_instance_data_from_backup().
     *
     * @param \restore_task $task
     * @param array $data
     * @param int $instanceid
     * @return mixed
     * @throws \moodle_exception
     */
    public function cms_restore_instance_data_from_backup(\restore_task $task, array $data, int $instanceid) {
        $context = $this->get_instance_context($instanceid);
        $editablefields = $this->get_editable_fields($instanceid);
        $records = api::get_instance_fields_data($editablefields, $instanceid);
        $target = $task->get_target();
        $override = ($target != \backup::TARGET_CURRENT_ADDING && $target != \backup::TARGET_EXISTING_ADDING);

        foreach ($records as $d) {
            $field = $d->get_field();
            if ($field->get('shortname') === $data['shortname'] && $field->get('type') === $data['type']) {
                if (!$d->get('id') || $override) {
                    $d->set($d->datafield(), $data['value']);
                    $d->set('value', $data['value']);
                    $d->set('valueformat', $data['valueformat']);
                    $d->set('contextid', $context->id);
                    $d->save();
                }
                return $d->get('id');
            }
        }
    }
}
