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

namespace mod_cms\form;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Import form for CMS types.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_types_import_form extends \moodleform {

    /**
     * Build form for importing workflows.
     *
     * {@inheritDoc}
     * @see \moodleform::definition()
     */
    public function definition() {

        $mform = $this->_form;

        // Workflow file.
        $mform->addElement(
            'filepicker',
            'importfile',
            get_string('import_file', 'mod_cms'),
            null,
            ['maxbytes' => 256000, 'accepted_types' => ['.yml', '.yaml', '.txt']]
        );
        $mform->addRule('importfile', get_string('required'), 'required');

        $this->add_action_buttons();
    }

    /**
     * Validate uploaded YAML file.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        global $USER;

        $validationerrors = [];

        // Get the file from the filestystem. $files will always be empty.
        $fs = get_file_storage();

        $context = \context_user::instance($USER->id);
        $itemid = $data['importfile'];

        // This is how core gets files in this case.
        if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $itemid, 'id DESC', false)) {
            $validationerrors['nofile'] = get_string('error:no_file_uploaded', 'mod_cms');
            return $validationerrors;
        }
        $file = reset($files);

        // Check if file is valid YAML.
        $content = $file->get_content();
        if (!empty($content)) {
            $validation = self::validate_yaml($content);
            if ($validation !== true) {
                $validationerrors['importfile'] = $validation;
            }
        }

        return $validationerrors;
    }

    /**
     * Get the errors returned during form validation.
     *
     * @return array|mixed
     */
    public function get_errors() {
        $form = $this->_form;
        $errors = $form->_errors;

        return $errors;
    }

    /**
     * Validate a YAML string to parse into an object.
     *
     * @param string $yaml
     * @return true|\lang_string Either true, or a string documenting the error.
     */
    public static function validate_yaml(string $yaml) {
        $invalid = false;
        try {
            $parsed = Yaml::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP);
            if (isset($parsed) && !is_object($parsed)) {
                $invalid = true;
            }
        } catch (ParseException $e) {
            $invalid = true;
        }

        if ($invalid) {
            return new \lang_string('invalidyaml', 'mod_cms');
        }

        return true;
    }
}
