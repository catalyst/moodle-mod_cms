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

namespace mod_cms\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{date, select, text};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use lang_string;
use moodle_url;
use stdClass;

/**
 * Report builder entity for CMS instances.
 *
 * @package     mod_cms
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_instance extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['cms', 'cms_types', 'course_modules'];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('instances:entity', 'mod_cms');
    }

    /**
     * Joins needed by the columns and filters of this entity. The main table must be cms.
     *
     * @return string[]
     */
    public function get_instance_joins(): array {
        $cms = $this->get_table_alias('cms');
        $types = $this->get_table_alias('cms_types');
        $cm = $this->get_table_alias('course_modules');
        return [
            "JOIN {cms_types} {$types} ON {$types}.id = {$cms}.typeid",
            // Instances being deleted by the recycle bin or cron still have a cms record, so skip them.
            "JOIN {course_modules} {$cm} ON {$cm}.instance = {$cms}.id
                AND {$cm}.module = (SELECT id FROM {modules} WHERE name = 'cms')
                AND {$cm}.deletioninprogress = 0",
        ];
    }

    /**
     * Returns the list of columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $cms = $this->get_table_alias('cms');
        $types = $this->get_table_alias('cms_types');
        $cm = $this->get_table_alias('course_modules');

        $columns[] = (new column('name', new lang_string('instances:name', 'mod_cms'), $this->get_entity_name()))
            ->add_joins($this->get_instance_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$cms}.name")
            ->add_field("{$cm}.id", 'cmid')
            ->set_is_sortable(true)
            ->add_callback(static function (?string $value, stdClass $row): string {
                return \html_writer::link(
                    new moodle_url('/mod/cms/view.php', ['id' => $row->cmid]),
                    format_string((string) $value)
                );
            });

        $columns[] = (new column('type', new lang_string('instances:type', 'mod_cms'), $this->get_entity_name()))
            ->add_joins($this->get_instance_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$types}.name")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $value): string {
                return s($value);
            });

        $columns[] = (new column('timecreated', new lang_string('instances:timecreated', 'mod_cms'), $this->get_entity_name()))
            ->add_joins($this->get_instance_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$cms}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        $columns[] = (new column('timemodified', new lang_string('instances:timemodified', 'mod_cms'), $this->get_entity_name()))
            ->add_joins($this->get_instance_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$cms}.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        return $columns;
    }

    /**
     * Returns the list of filters.
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
        $cms = $this->get_table_alias('cms');

        $filters[] = (new filter(text::class, 'name', new lang_string('instances:name', 'mod_cms'), $this->get_entity_name(), "{$cms}.name"))
            ->add_joins($this->get_instance_joins());

        $filters[] = (new filter(select::class, 'type', new lang_string('instances:type', 'mod_cms'), $this->get_entity_name(), "{$cms}.typeid"))
            ->add_joins($this->get_instance_joins())
            ->set_options_callback(static function (): array {
                global $DB;
                return $DB->get_records_menu('cms_types', null, 'name', 'id, name');
            });

        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('instances:timemodified', 'mod_cms'),
            $this->get_entity_name(),
            "{$cms}.timemodified"
        ))->add_joins($this->get_instance_joins());

        return $filters;
    }
}
