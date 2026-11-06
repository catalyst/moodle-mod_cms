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
use mod_cms\local\model\cms_types;

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
    const MAX_FILES = 1;

    /** @var string Persistent class name. */
    protected static $persistentclass = 'mod_cms\\local\\model\\cms_types';

    /**
     * Form definition.
     */
    protected function definition() {
        global $CFG, $OUTPUT;

        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('typename', 'mod_cms'), ['size' => 60]);
        $mform->addHelpButton('name', 'typename', 'mod_cms');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('text', 'idnumber', get_string('idnumber'));
        $mform->addRule('idnumber', get_string('required'), 'required', null, 'client');
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'idnumber', 'mod_cms');

        $mform->addElement('editor', 'description', get_string('description'));
        $mform->setType('description', PARAM_RAW);

        $mform->addElement(
            'static',
            'iconfile_desc',
            get_string('icon'),
            get_string('cms_type:icon_desc', 'mod_cms')
        );
        $mform->addElement(
            'filemanager',
            'iconfile',
            null,
            null,
            [
                'subdirs' => 0,
                'maxbytes' => $CFG->maxbytes,
                'maxfiles' => 1,
                'accepted_types' => ['web_image'],
                'return_types' => FILE_INTERNAL | FILE_EXTERNAL,
            ]
        );

        $mform->addElement(
            'advcheckbox',
            'showheader',
            get_string('showheader', 'mod_cms'),
            get_string('showheader_desc', 'mod_cms')
        );
        $mform->addHelpButton('showheader', 'showheader', 'mod_cms');

        $this->add_datasource_select_element();

        $mform->addElement('header', 'instance_heading', get_string('instance:header', 'mod_cms'));

        $mform->addElement('text', 'title_mustache', get_string('instance:name', 'mod_cms'), ['size' => 60]);
        $mform->addHelpButton('title_mustache', 'instance:name', 'mod_cms');
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
            // Keep the sections of enabled data sources open.
            $heading = $ds::get_shortname() . '_heading';
            if ($mform->elementExists($heading)) {
                $mform->setExpanded($heading, true);
            }
        }

        // Add a heading.
        $mform->addElement('header', 'preview_heading', get_string('preview'));

        // Rendered previews.
        $html = $renderer->get_html();
        // A raw html element spans the full width of the form, rather than sitting in the value column.
        $mform->addElement('html', $OUTPUT->render_from_template('mod_cms/type_preview', [
            'hint' => get_string('preview_with_hint', 'cms', get_string('savechangesanddisplay')),
            'content' => $html,
        ]));

        $this->add_action_buttons();
    }

    /**
     * Put together the datasource selection mechanism using checkboxes.
     */
    public function add_datasource_select_element() {
        $mform = $this->_form;
        $labels = dsbase::get_datasource_labels();
        $mform->addElement('header', 'datasources_heading', get_string('datasources', 'mod_cms'));
        $mform->addElement('static', 'datasources_desc', '', get_string('datasources_desc', 'mod_cms'));
        $descriptions = dsbase::get_datasource_descriptions();
        foreach ($labels as $shortname => $label) {
            $text = \html_writer::span(s($label), 'fw-bold');
            if (!empty($descriptions[$shortname])) {
                $text .= \html_writer::empty_tag('br') . \html_writer::span(s($descriptions[$shortname]), 'text-muted small');
            }
            $mform->addElement('checkbox', 'ds_' . $shortname, $shortname, $text);
        }
    }

    /**
     * Adds submit buttons to the form.
     *
     * @param bool $cancel
     * @param null $submitlabel Not used
     */
    public function add_action_buttons($cancel = true, $submitlabel = null) {
        $mform = $this->_form;

        $classarray = ['class' => 'form-submit'];
        $buttonarray = [
            $mform->createElement('submit', 'saveandreturn', get_string('savechangesandreturn'), $classarray),
            $mform->createElement('submit', 'saveanddisplay', get_string('savechangesanddisplay'), $classarray),
        ];
        if ($cancel) {
            $buttonarray[] = &$mform->createElement('cancel');
        }
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
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
        global $CFG;

        $data = parent::get_default_data();

        if (is_string($data->datasources)) {
            $datasources = explode(',', $data->datasources);
            foreach ($datasources as $shortname) {
                $name = 'ds_' . $shortname;
                $data->$name = $shortname;
            }
        }

        // Set up a draft area for managing the type icon file.
        $draftitemid = file_get_submitted_draft_itemid(cms_types::ICON_FILE_AREA);

        file_prepare_draft_area(
            $draftitemid,
            \context_system::instance()->id,
            'mod_cms',
            cms_types::ICON_FILE_AREA,
            $data->id,
            [
                'subdirs' => 0,
                'maxbytes' => $CFG->maxbytes,
                'maxfiles' => self::MAX_FILES,
            ]
        );
        $data->iconfile = $draftitemid;

        // Get default data for data sources.
        foreach (dsbase::get_datasources($this->get_persistent()) as $ds) {
            $ds->config_form_default_data($data);
        }
        return $data;
    }

    /**
     * Get anchor links to each major section of the form.
     *
     * @return array Each item has 'text' and 'url'.
     */
    public function get_section_links(): array {
        $links = [['text' => get_string('general'), 'url' => '#fitem_id_name']];
        foreach ($this->_form->_elements as $element) {
            if ($element->getType() !== 'header' || $element->getName() === '') {
                continue;
            }
            $element->_generateId();
            $links[] = ['text' => $element->toHtml(), 'url' => '#' . $element->getAttribute('id')];
        }
        return $links;
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
        foreach (array_keys($datasources) as $shortname) {
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
