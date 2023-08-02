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

use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\model\cms;
use mod_cms\local\model\cms_types;

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
     * @param cms|cms_types $cms
     */
    public function __construct($cms) {
        if ($cms instanceof cms_types) {
            $cms = $cms->get_sample_cms();
        }
        $this->cms = $cms;
    }

    /**
     * Get the data array for the cms.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        global $CFG, $SITE;

        $data = new \stdClass();
        $data->name = $this->cms->get('name');

        foreach (dsbase::get_datasources($this->cms) as $ds) {
            $name = $ds::get_shortname();
            $data->$name = $ds->get_data();
        }

        // Create a debug variable that contains the whole structure.
        $debug = json_encode($data, JSON_PRETTY_PRINT);
        $data->debug = "<pre>$debug</pre>";

        return $data;
    }

    /**
     * Flattens an array with nested arrays into a single array.
     *
     * @param array $output The output array to be written to.
     * @param mixed $source The source array to be read from.
     * @param string $prefix String to put at the front of the key name.
     */
    public static function flatten(array &$output, $source, string $prefix = '') {
        foreach ($source as $key => $value) {
            if (is_object($value) || is_array($value)) {
                self::flatten($output, $value, $prefix . '.' . $key);
            } else {
                $output[ltrim($prefix . '.' . $key, '.')] = $value;
            }
        }
    }

    /**
     * Retrieves the data for the cms as a flat array, with the keys concatenated using dots.
     *
     * @return \html_table
     */
    public function get_data_as_table(): \html_table {
        $flatarray = [];
        self::flatten($flatarray, $this->get_data());

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
     * @return string
     */
    public function get_html(): string {
        $contentcache = \cache::make('mod_cms', 'cms_content');

        $contenthash = $this->cms->get_content_hash();
        $content = $contentcache->get($contenthash);

        if ($content === false) {
            $data = $this->get_data();
            $mustache = self::get_mustache();
            $template = $this->cms->get_type()->get('mustache');
            $content = $mustache->render($template, $data);
            $contentcache->set($contenthash, $content);
        }

        return $content;
    }

    /**
     * Get a Mustache engine suitable for use with this renderer.
     *
     * @return \Mustache_Engine
     */
    public static function get_mustache(): \Mustache_Engine {
        $mustache = new \Mustache_Engine([
            'escape' => 's',
            'pragmas' => [\Mustache_Engine::PRAGMA_BLOCKS],
        ]);
        return $mustache;
    }
}
