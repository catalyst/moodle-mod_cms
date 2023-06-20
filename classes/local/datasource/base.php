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

use mod_cms\local\model\cms;
use mod_cms\local\model\cms_types;

/**
 * Base class for data sources.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    /** Datasources provided by this plugin. */
    public const BUILTIN_DATASOURCES = [
        'site',
        'fields',
        'images',
    ];

    /** @var array List of datasource class names in use. */
    protected static $datasourceclasses = [];

    /**
     * Create the list of datasources to be used.
     */
    public static function register_datasources() {
        // Only need to do this once.
        if (!empty(self::$datasourceclasses)) {
            return;
        }

        // Register the datasources that are defines in this plugin.
        foreach (self::BUILTIN_DATASOURCES as $classname) {
            self::add_datasource_class(__NAMESPACE__ . '\\' . $classname);
        }

        // Regsiter any datasources defined in other plugins.
        $plugintypes = get_plugins_with_function('modcms_datasources', 'lib.php');
        foreach ($plugintypes as $plugins) {
            foreach ($plugins as $pluginfunction) {
                $result = $pluginfunction();
                foreach ($result as $classname) {
                    self::add_datasource_class($classname);
                }
            }
        }
    }

    /**
     * Register a datasource.
     *
     * @param string $classname
     * @throws \moodle_exception
     */
    public static function add_datasource_class(string $classname) {
        // Test for existence.
        if (!class_exists($classname)) {
            throw new \moodle_exception('error:class_missing', 'mod_cms', '', $classname);
        }
        // Test for inheritance.
        if (!is_subclass_of($classname, __NAMESPACE__ . '\\' . 'base')) {
            throw new \moodle_exception('error:must_be_base', 'mod_cms', '', $classname);
        }
        // Test for uniqueness.
        $name = $classname::get_shortname();
        if (isset(self::$datasourceclasses[$name])) {
            throw new \moodle_exception('error:name_not_unique', 'mod_cms', '', $name);
        }
        self::$datasourceclasses[$name] = $classname;
    }

    /**
     * Get enabled datasources for the particular cms/cms_types.
     *
     * @param cms|cms_types $cms
     * @return \Generator
     */
    public static function get_datasources($cms) {
        self::register_datasources();

        if ($cms instanceof cms_types) {
            $cms = $cms->get_sample_cms();
        }
        // TODO: Use some kind of simple caching here.
        foreach (self::$datasourceclasses as $classname) {
            $ds = new $classname($cms);
            if ($ds->is_enabled()) {
                yield $ds;
            }
        }
    }

    /** @var cms */
    protected $cms;

    /**
     * Constructs a datasource for the given cms.
     *
     * @param cms $cms
     */
    public function __construct(cms $cms) {
        $this->cms = $cms;
    }

    /**
     * The short name of the datasource type. Must be unique.
     *
     * @return string
     */
    public static function get_shortname() {
        // Defaults to the unqualified class name.
        $name = get_called_class();
        $pos = strrpos($name, '\\');
        return $pos !== false ? substr($name, $pos + 1) : $name;
    }

    /**
     * Is this datasource enabled for this CMS type?
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return true;
    }

    /**
     * Can this datasource be disabled?
     *
     * @return bool
     */
    public function is_optional(): bool {
        return true;
    }

    /**
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    abstract public function get_data(): \stdClass;

    /**
     * Add fields to the CMS instance form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform) {
    }

    /**
     * Get extra data needed to add to the form.
     * @param mixed $data
     */
    public function instance_form_default_data(&$data) {
    }

    /**
     * Validate fields added by datasource.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array {
        return [];
    }

    /**
     * Add fields to the CMS type config form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
    }

    /**
     * Add extra data needed to add to the config form.
     *
     * @param mixed $data
     */
    public function config_form_default_data($data) {
    }

    /**
     * Validate fields added by datasource.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function config_form_validation(array $data, array $files): array {
        return [];
    }

    /**
     * Return an action link to add to the CMS type table.
     *
     * @return string|null
     */
    public function config_action_link(): ?string {
        return null;
    }

    /**
     * Called after updating cms type to perform any extra saving required by datasource.
     *
     * @param mixed $data
     */
    public function config_on_update($data) {
    }
}
