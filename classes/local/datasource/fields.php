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

use mod_cms\customfield\cmsfield_handler;
use mod_cms\helper;
use mod_cms\local\model\cms;

/**
 * Data source for custom fields.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields extends base {
    /** @var cmsfield_handler Custom field handler. */
    protected $cfhandler;

    /**
     * Constructs a datasource for the given cms.
     *
     * @param cms $cms
     */
    public function __construct(cms $cms) {
        parent::__construct($cms);
        $this->cfhandler = cmsfield_handler::create($this->cms->get('typeid'));
    }

    /**
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        $instancedata = $this->cfhandler->get_instance_data($this->cms->get('id'), true);
        $customfields = new \stdClass();
        foreach ($instancedata as $field) {
            $shortname = $field->get_field()->get('shortname');
            $customfields->$shortname = $this->cms->issample ? $this->get_sample($field) : $field->export_value();
        }
        return $customfields;
    }

    /**
     * Get a sample value for a custom field.
     *
     * @param \core_customfield\data_controller $field
     * @return string
     */
    protected function get_sample($field): string {
        if ($field->get_field()->get('type') === 'date') {
            return get_string('fields:sample_time', 'mod_cms');
        } else {
            switch ($field->datafield()) {
                case 'intvalue':
                    return '10';
                case 'decvalue':
                    return '10.5';
                default:
                    return get_string('fields:sample_text', 'mod_cms');
            }
        }
    }

    /**
     * Return a action link to add to the CMS type table.
     *
     * @return string|null
     */
    public function config_action_link(): ?string {
        // Link for custom fields.
        return helper::format_icon_link(
            $this->cfhandler->get_configuration_url(),
            't/index_drawer',
            get_string('fields:custom_fields', 'mod_cms'),
            null
        );
    }

    /**
     * Add fields to the CMS instance form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform) {
        $this->cfhandler->instance_form_definition($mform, $this->cms->get('id'));
    }

    /**
     * Get extra data needed to add to the form.
     * @param mixed $data
     */
    public function instance_form_default_data(&$data) {
        $this->cfhandler->instance_form_before_set_data($data);
    }

    /**
     * Validate fields added by datasource.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array {
        return $this->cfhandler->instance_form_validation($data, $files);
    }
}
