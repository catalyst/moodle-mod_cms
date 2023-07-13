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

/**
 * Content types list table.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_cms\local\table;

use mod_cms\helper;
use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\model\cms_types;
use mod_cms\manage_content_types;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/tablelib.php');

/**
 * Content types list table.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_types_list extends \flexible_table {
    /**
     * @var int Autogenerated id.
     */
    private static $autoid = 0;

    /**
     * Constructor
     *
     * @param int $id Used by the table, autogenerated if null
     */
    public function __construct(?int $id = null) {
        global $PAGE;

        $id = $id ?? self::$autoid++;
        parent::__construct('mod_cms'.$id);

        $this->define_baseurl($PAGE->url);
        $this->set_attribute('class', 'generaltable admintable w-auto');

        // Column definition.
        $this->define_columns([
            'name',
            'actions',
        ]);

        $this->define_headers([
            get_string('table:name', 'mod_cms'),
            get_string('actions'),
        ]);

        $this->setup();
    }

    /**
     * Display name column.
     *
     * @param cms_types $type
     *
     * @return string
     */
    protected function col_name(cms_types $type): string {
        return \html_writer::link(
            new \moodle_url(
                manage_content_types::get_base_url(),
                [
                    'id' => $type->get('id'),
                    'action' => 'edit',
                ]
            ),
            $type->get('name')
        );
    }

    /**
     * Display actions column.
     *
     * @param cms_types $type
     *
     * @return string
     */
    protected function col_actions(cms_types $type): string {
        $actions = [];

        $actions[] = helper::format_icon_link(
            new \moodle_url(
                manage_content_types::get_base_url(),
                [
                    'id' => $type->get('id'),
                    'action' => 'edit',
                ]
            ),
            't/edit',
            get_string('edit')
        );

        $actions[] = helper::format_icon_link(
            new \moodle_url(
                manage_content_types::get_base_url(),
                [
                    'id' => $type->get('id'),
                    'action' => 'export',
                ]
            ),
            't/download',
            get_string('export', 'mod_cms')
        );

        // Get links for data sources.
        foreach (dsbase::get_datasources($type) as $ds) {
            $actions[] = $ds->config_action_link();
        }

        if ($type->can_delete()) {
            $actions[] = helper::format_delete_link(
                new \moodle_url(
                    manage_content_types::get_base_url(),
                    [
                        'id' => $type->get('id'),
                        'action' => 'delete',
                        'sesskey' => sesskey(),
                    ]
                ),
                'Confirm delete?'
            );
        }

        return implode('&nbsp;', $actions);
    }

    /**
     * Sets the data of the table.
     *
     * @param cms_types[] $types
     *
     * @return void
     */
    public function display(array $types): void {
        foreach ($types as $type) {
            $this->add_data_keyed($this->format_row($type));
        }

        $this->finish_output();
    }
}
