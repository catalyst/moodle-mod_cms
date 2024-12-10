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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_cms\task;

use core\task\adhoc_task;
use csv_export_writer;
use file_storage;
use moodle_exception;

/**
 * Update the context of embedded files to match the context of the mod_cms module instance.
 *
 * @package     mod_cms
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2024 Catalyst IT Australia
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_files_context extends adhoc_task {

    /**
     * Factory method to create a new update_files_context task.
     *
     * @param int|null $courseid Limit the task to a specific course or all courses if null.
     * @param bool $dryrun Whether to run the task without making database changes.
     * @return update_files_context The task instance.
     */
    public static function instance(?int $courseid = null, bool $dryrun = true): update_files_context {
        $task = new self();
        $task->set_custom_data((object) ['courseid' => $courseid, 'dryrun' => $dryrun]);
        return $task;
    }

    /**
     * Run the task to delete course results for a user.
     */
    public function execute(): void {
        global $CFG;
        $data = $this->get_custom_data();
        $csv = self::update_contexts($data->courseid, $data->dryrun);
        file_put_contents($CFG->dataroot . '/' . $csv->filename, $csv->print_csv_data(true));
    }

    /**
     * Update mod_cms customfield_textarea embedded file contexts to match the context of the mod_cms module instance.
     * @param int|null $courseid Limit the task to a specific course or all courses if null.
     * @param bool $dryrun Whether to run the task without making database changes.
     * @return csv_export_writer The CSV export writer containing the results of the task.
     */
    private static function update_contexts(?int $courseid, bool $dryrun): csv_export_writer {
        global $CFG, $DB;

        require_once($CFG->libdir . '/csvlib.class.php');

        $sql = "SELECT f.*, ctx.id AS ctxid, cms.course AS courseid
                  FROM {files} f
                  JOIN {customfield_data} cfd ON cfd.id = f.itemid
                  JOIN {customfield_field} cff ON cff.id = cfd.fieldid
                  JOIN {cms} cms ON cms.id = cfd.instanceid
                  JOIN {cms_types} cmst ON cmst.id = cms.typeid AND cmst.datasources LIKE '%fields%'
                  JOIN {course_modules} cm ON cm.instance = cms.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'cms'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulecontextlevel
                 WHERE f.contextid = 1 AND f.component = 'customfield_textarea' AND f.filearea = 'value'";

        $params = ['modulecontextlevel' => CONTEXT_MODULE];

        if (!empty($courseid)) {
            $sql .= " AND cms.course = :courseid";
            $params['courseid'] = $courseid;
        }

        $csv = new csv_export_writer();
        $csv->set_filename('mod_cms_update_files_context');
        $csv->add_data([
            'courseid',
            'newpathnamehash',
            'newcontextid',
            'id',
            'contenthash',
            'pathnamehash',
            'contextid',
            'component',
            'filearea',
            'itemid',
            'filepath',
            'filename',
            'timecreated',
            'timemodified',
        ]);

        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $newcontextid = $record->ctxid;

            $newpathnamehash = file_storage::get_pathname_hash(
                $newcontextid,
                $record->component,
                $record->filearea,
                $record->itemid,
                $record->filepath,
                $record->filename
            );

            $csv->add_data([
                $record->courseid,
                $newpathnamehash,
                $newcontextid,
                $record->id,
                $record->contenthash,
                $record->pathnamehash,
                $record->contextid,
                $record->component,
                $record->filearea,
                $record->itemid,
                $record->filepath,
                $record->filename,
                $record->timecreated,
                $record->timemodified,
            ]);

            // Update the record with the new context id and path name hash.
            $record->contextid = $newcontextid;
            $record->pathnamehash = $newpathnamehash;

            if (!$dryrun) {
                // Remove the ctxid and courseid fields.
                unset($record->ctxid);
                unset($record->courseid);

                // Update the record in the database.
                try {
                    $DB->update_record('files', $record);
                } catch (moodle_exception $e) {
                    debugging('Failed to insert record into files table: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        $records->close();

        return $csv;
    }
}
