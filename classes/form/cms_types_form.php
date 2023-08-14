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
 * Form for manipulating the content types
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_cms\form;

use core\form\persistent as persistent_form;
use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\renderer;

/**
 * Form for manipulating the content types
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_types_form extends persistent_form {

    /** The maximum amount of files allowed. */
    const MAX_FILES = 50;

    /** @var string Persistent class name. */
    protected static $persistentclass = 'mod_cms\\local\\model\\cms_types';

    /**
     * Form definition.
     */
    protected function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('name'));
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('editor', 'description', get_string('description'));
        $mform->setType('description', PARAM_RAW);

        $this->add_datasource_select_element();

        $mform->addElement('header', 'instance_heading', get_string('instance:header', 'mod_cms'));

        $mform->addElement('text', 'title_mustache', get_string('instance:name', 'mod_cms'));
        $mform->addRule('title_mustache', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'mustache',
            get_string('mustache', 'cms'),
            ['rows' => 10, 'style' => 'font-family: monospace; font-size: 12px;']
        );
        $mform->setType('mustache', PARAM_RAW);

        // Generate the help text for mustache template.
        $cmstype = $this->get_persistent();
        $renderer = new renderer($cmstype);
        $syntaxlink = \html_writer::link(
            new \moodle_url('https://moodledev.io/docs/guides/templates'),
            get_string('mustache_template', 'cms')
        );
        $helptext = get_string('mustache_help', 'cms', $syntaxlink);
        $helptext .= \html_writer::tag('pre', implode(PHP_EOL, $renderer->get_variable_list()));
        $mform->addElement('static', 'mustache_help', '', $helptext);

        // Add form elements for data sources.
        foreach (dsbase::get_datasources($cmstype) as $ds) {
            $ds->config_form_definition($mform);
        }

        // Rendered previews.
        $html = $renderer->get_html();
        $mform->addElement('static', 'preview', get_string('preview', 'cms', get_string('savechangesanddisplay')), $html);

        $this->add_action_buttons();
    }

    /**
     * Extra validataion on the data.
     *
     * @param \stdClass $data
     * @param array $files
     * @param array $errors
     * @return array
     */
    public function extra_validation($data, $files, array &$errors) {
        $errors = parent::extra_validation($data, $files, $errors);

        $valid = renderer::validate_template($data->title_mustache);
        if ($valid !== true) {
            $errors['title_mustache'] = $valid;
        }

        $valid = renderer::validate_template($data->mustache);
        if ($valid !== true) {
            $errors['mustache'] = $valid;
        }

        return $errors;
    }

    /**
     * Put together the datasource selection mechanism using checkboxes.
     */
    public function add_datasource_select_element() {
        $mform = $this->_form;
        $labels = dsbase::get_datasource_labels();
        $mform->addElement('static', 'datasources_desc', get_string('datasources', 'mod_cms'),
                get_string('datasources_desc', 'mod_cms'));
        $boxes = [];
        foreach ($labels as $shortname => $label) {
            $name = 'ds_' . $shortname;
            $boxes[] = $mform->createElement('checkbox', $name, $label, null);
        }
        $mform->addGroup($boxes);
    }

    /**
     * Adds submit buttons to the form.
     *
     * @param bool $cancel
     * @param null $submitlabel Not used
     */
    public function add_action_buttons($cancel = true, $submitlabel=null) {
        $mform = $this->_form;

        $classarray = ['class' => 'form-submit'];
        $buttonarray = [
            $mform->createElement('submit', 'saveandreturn', get_string('savechangesandreturn'), $classarray),
            $mform->createElement('submit', 'saveanddisplay', get_string('savechangesanddisplay'), $classarray),
        ];
        if ($cancel) {
            $buttonarray[] = &$mform->createElement('cancel');
        }
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Get the default data.
     *
     * This is the data that is prepopulated in the form at it loads, we automatically
     * fetch all the properties of the persistent however some needs to be converted
     * to map the form structure.
     *
     * Extend this class if you need to add more conversion.
     *
     * @return \stdClass
     */
    protected function get_default_data() {
        $data = parent::get_default_data();

        if (is_string($data->datasources)) {
            $datasources = explode(',', $data->datasources);
            foreach ($datasources as $shortname) {
                $name = 'ds_' . $shortname;
                $data->$name = $shortname;
            }
        }

        // Get default data for data sources.
        foreach (dsbase::get_datasources($this->get_persistent()) as $ds) {
            $ds->config_form_default_data($data);
        }
        return $data;
    }

    /**
     * Convert some fields.
     *
     * @param  \stdClass $data The whole data set.
     * @return \stdClass The amended data set.
     */
    protected static function convert_fields(\stdClass $data) {
        $data = parent::convert_fields($data);
        $datasources = dsbase::get_datasource_labels();
        $selected = [];
        foreach ($datasources as $shortname => $unused) {
            $name = 'ds_' . $shortname;
            if (isset($data->$name)) {
                $selected[] = $shortname;
                unset($data->$name);
            }
        }
        $data->datasources = implode(',', $selected);
        return $data;
    }
}
