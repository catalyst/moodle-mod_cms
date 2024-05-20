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

namespace mod_cms\task;

use core\task\adhoc_task;

/**
 * Runs customfield context update.
 *
 * @package    mod_cms
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_customfield_context extends adhoc_task {

    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        // Collect records which have wrong contextid in the customfield data.
        $sql = "SELECT mcd.id mcdid, mcd.contextid mcdcontextid, mc.id mcid
                  FROM {customfield_data} mcd
                  JOIN {customfield_field} mcf ON mcf.id = mcd.fieldid
                  JOIN {customfield_category} mcc ON mcc.id = mcf.categoryid
                  JOIN {course_modules} mcm ON mcm.instance = mcd.instanceid AND mcm.module = (
                      SELECT id FROM {modules} WHERE name = 'cms'
                  )
                  JOIN {context} mc ON mc.instanceid = mcm.id AND contextlevel = " . CONTEXT_MODULE . "
                 WHERE mcc.component = 'mod_cms' AND mcd.contextid != mc.id";
        $records = $DB->get_records_sql($sql);
        // Update records with correct contextid.
        foreach ($records as $record) {
            $DB->set_field('customfield_data', 'contextid', $record->mcid, ['id' => $record->mcdid]);
        }
    }
}
