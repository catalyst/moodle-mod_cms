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

use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\model\cms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Defines the edit form for CMS activities.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_cms_mod_form extends moodleform_mod {
    /** @var int ID of the CMS type.  */
    protected $typeid;

    /** @var cms CMS being edited. */
    protected $cms;

    /**
     * Construct an instance of moodleform_mod.
     *
     * @param mixed $current
     * @param mixed $section
     * @param mixed $cm
     * @param mixed $course
     */
    public function __construct($current, $section, $cm, $course) {
        $update = $current->update ?? 0;
        if ($update) {
            $this->cms = new cms($cm->instance);
            $this->typeid = $this->cms->get('typeid');
        } else {
            $this->typeid = optional_param('typeid', false, PARAM_INT);
            $this->cms = new cms();
            $this->cms->set('typeid', $this->typeid);
        }

        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Defines the form.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'typeid', $this->typeid);
        $mform->setType('typeid', PARAM_INT);

        // Add form elements for data sources.
        foreach (dsbase::get_datasources($this->cms) as $ds) {
            $ds->instance_form_definition($this, $mform);
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Load in existing data as form defaults.
     *
     * @param mixed $defaultvalues object or array of default values
     */
    public function set_data($defaultvalues) {
        // Add form elements for data sources.
        foreach (dsbase::get_datasources($this->cms) as $ds) {
            $ds->instance_form_default_data($defaultvalues);
        }
        parent::set_data($defaultvalues);
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        foreach (dsbase::get_datasources($this->cms) as $ds) {
            $errors = array_merge($errors, $ds->instance_form_validation($data, $files));
        }

        return $errors;
    }
}
