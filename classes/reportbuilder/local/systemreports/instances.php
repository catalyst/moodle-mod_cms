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

namespace mod_cms\reportbuilder\local\systemreports;

use context_system;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\system_report;
use lang_string;
use mod_cms\reportbuilder\local\entities\cms_instance;

/**
 * System report listing the CMS instances, optionally for a single content type.
 *
 * Pass the 'typeid' parameter to restrict the report to one content type.
 *
 * @package     mod_cms
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instances extends system_report {
    /**
     * Initialise the report: main table, entities, columns and filters.
     */
    protected function initialise(): void {
        $main = new cms_instance();
        $cms = $main->get_table_alias('cms');

        $this->set_main_table('cms', $cms);
        $this->add_entity($main);

        // Always join, so that deleted instances are excluded even if no column needs the join.
        foreach ($main->get_instance_joins() as $join) {
            $this->add_join($join);
        }
        $this->add_base_fields("{$cms}.id");

        $course = new course();
        $coursealias = $course->get_table_alias('course');
        $this->add_entity($course->add_join("JOIN {course} {$coursealias} ON {$coursealias}.id = {$cms}.course"));

        $this->add_columns_from_entities([
            'course:coursefullnamewithlink',
            'cms_instance:name',
            'cms_instance:type',
            'cms_instance:timecreated',
            'cms_instance:timemodified',
        ]);
        $this->set_initial_sort_column('cms_instance:name', SORT_ASC);

        $this->add_filters_from_entities([
            'cms_instance:name',
            'cms_instance:type',
            'course:fullname',
            'course:shortname',
            'cms_instance:timemodified',
        ]);

        $this->set_downloadable(true, get_string('instances:heading', 'mod_cms'));
    }

    /**
     * Only site administrators can view this report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('moodle/site:config', context_system::instance());
    }

    /**
     * The visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('instances:heading', 'mod_cms');
    }
}
