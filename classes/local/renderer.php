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

namespace mod_cms\local;

use context_system;
use mod_cms\customfield\cmsfield_handler;
use mod_cms\local\model\cms;
use Mustache_Engine;

/**
 * Renders the contents of a mustache template.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer {
    /** @var cms */
    protected $cms;

    /**
     * Constructs a renderer for the given cms.
     *
     * @param cms $cms
     */
    public function __construct(cms $cms) {
        $this->cms = $cms;
    }

    /**
     * Get the data array for the cms.
     *
     * @param bool $sample Fill with sample data rather than actual data.
     * @return array
     */
    public function get_data($sample = false): array {
        global $CFG, $SITE;

        $data = [
            'name' => $this->cms->get('name'),
        ];

        $data['site'] = [
            'fullname'  => $SITE->fullname,
            'shortname' => $SITE->shortname,
            'wwwroot'   => $CFG->wwwroot,
        ];

        $data['images'] = $this->get_images();
        $cfhandler = cmsfield_handler::create($this->cms->get('typeid'));
        $instancedata = $cfhandler->get_instance_data($this->cms->get('id'), true);
        $data['customfield'] = [];
        foreach ($instancedata as $field) {
            $shortname = $field->get_field()->get('shortname');
            $data['customfield'][$shortname] = $sample ? $this->get_sample($field) : $field->export_value();
        }

        return $data;
    }

    /**
     * Get the images stored with the template to be added to the data.
     *
     * @return array
     */
    protected function get_images() {
        $files = $this->cms->get_type()->get_images();

        $imagedata = [];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $shortfilename = pathinfo($filename, PATHINFO_FILENAME);
            if ($filename == '.') {
                continue;
            }
            $url = \moodle_url::make_pluginfile_url(
                context_system::instance()->id,
                'mod_cms',
                'cms_type_images',
                $this->cms->get('typeid'),
                '/',
                $filename
            );
            $imagedata[$shortfilename] = $url->out();
        }
        return $imagedata;
    }

    /**
     * Get a sample value for a custom field.
     *
     * @param \core_customfield\data_controller $field
     * @return string
     */
    public function get_sample($field): string {
        if ($field->get_field()->get('type') === 'date') {
            return 'Thursday, 15 June 2023, 12:00 AM';
        } else {
            switch ($field->datafield()) {
                case 'intvalue':
                    return '10';
                case 'decvalue':
                    return '10.5';
                default:
                    return 'text';
            }
        }
    }

    /**
     * Flattens an array with nested arrays into a single array.
     *
     * @param array $output The output array to be written to.
     * @param array $source The source array to be read from.
     * @param string $prefix String to put at the front of the key name.
     */
    public static function flatten(array &$output, array $source, string $prefix = '') {
        foreach ($source as $key => $value) {
            if (is_array($value)) {
                self::flatten($output, $value, $prefix . '.' . $key);
            } else {
                $output[ltrim($prefix . '.' . $key, '.')] = $value;
            }
        }
    }

    /**
     * Retrieves the data for the cms as a flat array, with the keys concatenated using dots.
     *
     * @param bool $sample Fill with sample data rather than actual data.
     * @return \html_table
     */
    public function get_data_as_table($sample = false): \html_table {
        $flatarray = [];
        self::flatten($flatarray, $this->get_data($sample));

        $table = new \html_table();
        $table->attributes['class'] = 'noclass';
        $table->head = [get_string('name'), get_string('sample_value', 'mod_cms')];
        foreach ($flatarray as $name => $value) {
            $left = new \html_table_cell('{{' . $name . '}}');
            $right = new \html_table_cell($value);
            $row = new \html_table_row([$left, $right]);
            $table->data[] = $row;
        }
        return $table;
    }

    /**
     * Renders the template with the data and returns the content.
     *
     * @param bool $sample Fill with sample data rather than actual data.
     * @return string
     */
    public function get_html($sample = false): string {
        $data = $this->get_data($sample);

        $mustache = self::get_mustache();
        $template = $this->cms->get_type()->get('mustache');
        $html = $mustache->render($template, $data);

        return $html;
    }

    /**
     * Get a Mustache engine suitable for use with this renderer.
     *
     * @return Mustache_Engine
     */
    public static function get_mustache(): Mustache_Engine {
        $mustache = new Mustache_Engine([
            'escape' => 's',
            'pragmas' => [Mustache_Engine::PRAGMA_BLOCKS],
        ]);
        return $mustache;
    }
}
