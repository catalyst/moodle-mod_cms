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

namespace mod_cms\local\datasource;

/**
 * Data source for course metadata.
 *
 * Exposes basic course information to CMS templates, e.g.:
 *   {{#course}}{{fullname}}{{/course}}
 *
 * The instance cache key is derived from the course's timemodified timestamp,
 * so the cache is automatically invalidated whenever the course is updated —
 * no event observer required.
 *
 * When used on a sample CMS (type configuration preview), placeholder values
 * are returned instead of real course data.
 *
 * @package   mod_cms
 * @author    Brendan Heywood <brendan@catalyst-au.net>
 * @copyright Catalyst IT 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course extends base_mod_cms {
    /**
     * Get the display name.
     *
     * @return string
     */
    public static function get_displayname(): string {
        return get_string('course:displayname', 'mod_cms');
    }

    /**
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        if ($this->cms->issample) {
            $fields = new \stdClass();
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_fields() as $field) {
                $shortname = $field->get('shortname');
                $fields->$shortname = get_string('sample_value', 'mod_cms');
            }
            return (object) [
                'fullname'    => get_string('course:sample:fullname', 'mod_cms'),
                'shortname'   => get_string('course:sample:shortname', 'mod_cms'),
                'courseurl'   => '#',
                'summary'     => get_string('course:sample:summary', 'mod_cms'),
                'idnumber'    => '',
                'courseimage' => '',
                'fields'      => $fields,
            ];
        }

        $courseid = $this->cms->get('course');
        $course = get_course($courseid);
        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

        $courseimage = '';
        $context = \context_course::instance($courseid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
        if ($files) {
            $file = reset($files);
            $courseimage = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }

        $fields = new \stdClass();
        $handler = \core_course\customfield\course_handler::create();
        foreach ($handler->get_instance_data($courseid) as $data) {
            $shortname = $data->get_field()->get('shortname');
            $fields->$shortname = $data->export_value() ?? '';
        }

        return (object) [
            'fullname'    => format_string($course->fullname),
            'shortname'   => format_string($course->shortname),
            'courseurl'   => $courseurl->out(false),
            'summary'     => format_text($course->summary, $course->summaryformat),
            'idnumber'    => $course->idnumber,
            'courseimage' => $courseimage,
            'fields'      => $fields,
        ];
    }

    /**
     * Returns the course's timemodified as the instance cache key fragment.
     *
     * This means the cache is automatically invalidated whenever the course is
     * updated, with no need for an event observer.
     *
     * @return string|null
     */
    public function get_instance_cache_key(): ?string {
        if ($this->cms->issample || empty($this->cms->get('course'))) {
            return '';
        }
        global $DB;
        return (string) $DB->get_field('course', 'timemodified', ['id' => $this->cms->get('course')]);
    }

    /**
     * Nothing to store — the instance key is derived live from the course record.
     */
    public function update_instance_cache_key() {
        // Key is derived from course timemodified, not stored in the CMS instance.
    }

    /**
     * Returns a config cache key derived from a stored revision counter.
     *
     * The counter is bumped by {@see on_course_customfield_changed()} whenever a course
     * custom field definition is created, updated, or deleted, so that both the datasource
     * cache and the renderer caches (cms_content, cms_name) are automatically invalidated.
     *
     * @return string
     */
    public function get_config_cache_key(): ?string {
        return (string) (int) get_config('mod_cms', 'course_fields_rev');
    }

    /**
     * Nothing to update — the config cache key is managed via the course_fields_rev config setting.
     */
    public function update_config_cache_key() {
        // Key is derived from course_fields_rev, not stored in the CMS type.
    }

    /**
     * Called when a course custom field definition is created, updated, or deleted.
     *
     * Bumps the course_fields_rev config counter so that get_config_cache_key() returns a
     * new value, invalidating both the datasource cache and the renderer caches (cms_content,
     * cms_name) for all CMS instances that use the course datasource.
     *
     * @param \core\event\base $event
     */
    public static function on_course_customfield_changed(\core\event\base $event): void {
        global $DB;

        // Use the record snapshot to get the categoryid — safe for deleted fields too.
        $fieldrecord = $event->get_record_snapshot('customfield_field', $event->objectid);
        if (!$fieldrecord) {
            return;
        }

        $component = $DB->get_field('customfield_category', 'component', ['id' => $fieldrecord->categoryid]);
        if ($component !== 'core_course') {
            return;
        }

        set_config('course_fields_rev', (int) get_config('mod_cms', 'course_fields_rev') + 1, 'mod_cms');
        \cache::make('mod_cms', 'cms_content_course')->purge();
    }
}
