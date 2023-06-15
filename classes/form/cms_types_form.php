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
use html_writer;
use mod_cms\local\renderer;
use moodle_url;

/**
 * Form for manipulating the content types
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_types_form extends persistent_form {

    /** @var string Persistent class name. */
    protected static $persistentclass = 'mod_cms\\local\\model\\cms_types';

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('name'));
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('editor', 'description', get_string('description'));
        $mform->setType('description', PARAM_RAW);

        $mform->addElement(
            'textarea',
            'mustache',
            get_string('mustache', 'cms'),
            ['rows' => 10, 'style' => 'font-family: monospace; font-size: 12px;']
        );
        $mform->setType('mustache', PARAM_RAW);

        // Generate the help text for mustache template.
        $cmstype = $this->get_persistent();
        $cms = $cmstype->get_sample_cms();
        $renderer = new renderer($cms);
        $data = $renderer->get_data();
        $syntaxlink = html_writer::link(
            new moodle_url('https://moodledev.io/docs/guides/templates'),
            get_string('mustache_template', 'cms')
        );
        $helptext = get_string('mustache_help', 'cms', $syntaxlink);
        $helptext .= html_writer::table($renderer->get_data_as_table(true));
        $mform->addElement('static', 'mustache_help', '', $helptext);

        $html = $renderer->get_html(true);
        $mform->addElement('static', 'preview', get_string('preview', 'cms', get_string('savechangesanddisplay')), $html);

        $this->add_action_buttons();
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
}
