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
        $data = new \stdClass();
        $data->name = $this->cms->get('name');

        foreach (dsbase::get_datasources($this->cms) as $ds) {
            $name = $ds::get_shortname();
            $data->$name = $ds->get_cached_data();
        }

        // Create a debug variable that contains the whole structure.
        $debug = json_encode($data, JSON_PRETTY_PRINT);
        $data->debug = "<pre>$debug</pre>";

        return $data;
    }

    /**
     * Loop through loops recursively to generate lists of variables.
     *
     * @param object|array $source
     * @param string $prefix
     * @return array
     */
    private function looper($source, string $prefix = ''): array {
        $lines = [];
        foreach ($source as $key => $value) {
            if (is_bool($value)) {
                $lines[] = $prefix . '{{#' . $key . '}} // As a boolean';
                $lines[] = $prefix . '{{/' . $key . '}}';
            }
            if (is_object($value)) {
                $lines[] = $prefix . '{{#' . $key . '}} // As an object';
                $lines = array_merge($lines, $this->looper($value, $prefix . '  '));
                $lines[] = $prefix . '{{/' . $key . '}}';
            } else if (is_array($value)) {
                if (is_object($value[0])) {
                    $lines[] = $prefix . '{{#' . $key . '}} // As an array of objects';
                    $lines = array_merge($lines, $this->looper($value[0], $prefix . '  '));
                    $lines[] = $prefix . '{{/' . $key . '}}';
                } else {
                    $lines[] = $prefix . '{{#' . $key . '}} // As an array of values';
                    $lines[] = $prefix . '{{.}}';
                    $lines[] = $prefix . '{{/' . $key . '}}';
                }
            } else {
                $lines[] = $prefix . '{{' . $key . '}}';
            }
        }
        return $lines;
    }

    /**
     * Get a list of variables accessible to a CMS type.
     *
     * @return array A list of lines, including loops and indents.
     */
    public function get_variable_list(): array {
        $data = $this->get_data();
        $lines = $this->looper($data);
        return $lines;
    }

    /**
     * Renders the template with the data and returns the content.
     *
     * @return string
     */
    public function get_html(): string {
        return $this->get_content('cms_content', 'mustache');
    }

    /**
     * Renders the template label with the data and returns the result.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->get_content('cms_name', 'title_mustache');
    }

    /**
     * Gets content from a template using a cache.
     * If the cache key is null, then the cache is not used.
     *
     * @param string $cachename
     * @param string $varname Variable that holds the template.
     * @return string
     */
    protected function get_content(string $cachename, string $varname): string {
        $cache = \cache::make('mod_cms', $cachename);
        $key = $this->get_cache_key();
        if (is_null($key)) {
            return $this->resolve_mustache($varname);
        }

        $content = $cache->get($key);

        if ($content === false) {
            $content = $this->resolve_mustache($varname);
            $cache->set($key, $content);
        }

        return $content;
    }

    /**
     * Resolves a mustache template using CMS data.
     *
     * @param string $varname The name of the template to use.
     * @return string
     */
    protected function resolve_mustache(string $varname): string {
        $data = $this->get_data();
        $mustache = self::get_mustache();
        $template = $this->cms->get_type()->get($varname);
        return $mustache->render($template, $data);
    }

    /**
     * Gets the cache key for the rendered content.
     * Will return null if any datasource has a null key.
     *
     * @return string|null
     */
    public function get_cache_key(): ?string {
        foreach (dsbase::get_datasources($this->cms) as $ds) {
            $key = $ds->get_full_cache_key();
            if (is_null($key)) {
                return null;
            }
        }

        $ckey = $this->cms->get_type()->get_cache_key();
        $ikey = $this->cms->get_cache_key();

        if (is_null($ckey) || is_null($ikey)) {
            return null;
        }

        return $ckey . $ikey;
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

    /**
     * Test the mustaceh template to see ifi it is valid.
     *
     * @param string $template
     * @return bool|string Returns true if no error occurred or an error message.
     */
    public static function validate_template(string $template) {
        $engine = self::get_mustache();
        try {
            $engine->render($template);
            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
