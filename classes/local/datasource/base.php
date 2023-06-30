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

        // Register any datasources defined in other plugins.
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
     * @param bool $enabledonly
     * @return \Generator
     */
    public static function get_datasources($cms, bool $enabledonly = true) {
        self::register_datasources();

        if ($cms instanceof cms_types) {
            $cms = $cms->get_sample_cms();
        }
        // TODO: Use some kind of simple caching here.
        foreach (self::$datasourceclasses as $classname) {
            $ds = new $classname($cms);
            if (!$enabledonly || $ds->is_enabled()) {
                yield $ds;
            }
        }
    }

    /**
     * Get a datasource.
     *
     * @param string $name The short name of the datasource
     * @param cms|cms_types $cms
     * @return false|base The datasource, or false if not found.
     */
    public static function get_datasource(string $name, $cms) {
        self::register_datasources();

        if (!isset(self::$datasourceclasses[$name])) {
            return false;
        }

        if ($cms instanceof cms_types) {
            $cms = $cms->get_sample_cms();
        }

        $dsclassname = self::$datasourceclasses[$name];
        return new $dsclassname($cms);
    }

    /**
     * Get datasource labels
     *
     * @param bool $optionalonly
     * @return array
     */
    public static function get_datasource_labels(bool $optionalonly = true): array {
        self::register_datasources();

        $labels = [];
        foreach (self::$datasourceclasses as $classname) {
            if (!$optionalonly || $classname::is_optional()) {
                $labels[$classname::get_shortname()] = $classname::get_displayname();
            }
        }
        return $labels;
    }

    /** @var cms */
    protected $cms = null;

    /**
     * Constructs a datasource.
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
    public static function get_shortname(): string {
        // Defaults to the unqualified class name.
        $name = get_called_class();
        $pos = strrpos($name, '\\');
        return $pos !== false ? substr($name, $pos + 1) : $name;
    }

    /**
     * Get the display name.
     *
     * @return string
     */
    abstract public static function get_displayname(): string;

    /**
     * Is this datasource enabled for this CMS type?
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return !static::is_optional() || in_array(static::get_shortname(), $this->cms->get_type()->get('datasources'));
    }

    /**
     * Can this datasource be disabled?
     *
     * @return bool
     */
    public static function is_optional(): bool {
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
     * Called when an instance is added/updated.
     *
     * @param \stdClass $instancedata
     * @param bool $isnewinstance
     */
    public function update_instance(\stdClass $instancedata, bool $isnewinstance) {
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

    /**
     * Get configuration data for exporting.
     *
     * @return \stdClass|null
     */
    public function get_for_export(): ?\stdClass {
        return null;
    }

    /**
     * Import configuration from an object.
     *
     * @param \stdClass $data
     */
    public function set_from_import(\stdClass $data) {
    }

    /**
     * Returns a cache hash, representing the data stored for the datasource.
     *
     * @return string
     */
    public function get_cache_hash(): string {
        return '';
    }
}
