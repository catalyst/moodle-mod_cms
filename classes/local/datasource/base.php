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

use mod_cms\local\lib;
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
        'userlist',
        'roles',
    ];

    /** @var array List of datasource class names in use. */
    protected static $datasourceclasses = [];

    /**
     * Create the list of datasources to be used.
     */
    final public static function register_datasources() {
        // Only need to do this once.
        if (!empty(self::$datasourceclasses)) {
            return;
        }

        // Register the datasources that are defines in this plugin.
        foreach (self::BUILTIN_DATASOURCES as $classname) {
            self::add_datasource_class(__NAMESPACE__ . '\\' . $classname);
        }

        if (!PHPUNIT_TEST) {
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
    }

    /**
     * Register a datasource.
     *
     * @param string $classname
     * @throws \moodle_exception
     */
    final public static function add_datasource_class(string $classname) {
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
    final public static function get_datasources($cms, bool $enabledonly = true) {
        self::register_datasources();

        if ($cms === null) {
            $cms = new cms_types(0);
        }

        if ($cms instanceof cms_types) {
            $cms = $cms->get_sample_cms();
        }

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
    final public static function get_datasource(string $name, $cms) {
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
    final public static function get_datasource_labels(bool $optionalonly = true): array {
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
     * Constructs the data structure to act as the data source.
     *
     * @return \stdClass
     */
    abstract public function get_data(): \stdClass;

    /**
     * Constructs the data structure to act as the data source. Uses a cache.
     *
     * @return \stdClass
     * @throws \coding_exception
     */
    public function get_cached_data(): \stdClass {
        $key = $this->get_full_cache_key();

        if (is_null($key)) {
            return $this->get_data();
        }

        $cache = $this->get_cache();
        $data = $cache->get($key);
        if ($data === false) {
            $data = $this->get_data();
            $cache->set($key, $data);
        }
        return $data;
    }

    /**
     * Get the cache used by the datasource. This is required for caching the data returned by get_data(),
     * and needs to be declared in the plugin of the datasource.
     *
     * If there is no caching done, have this return null.
     *
     * @return \cache|null
     */
    abstract public function get_cache(): ?\cache;

    /**
     * Add fields to the CMS instance form.
     *
     * @param \moodleform_mod $form
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\moodleform_mod $form, \MoodleQuickForm $mform) {
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
        $this->update_config_cache_key();
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
     * Returns a hash of the content, representing the data stored for the datasource.
     *
     * @deprecated
     * @return string|null
     * @throws \moodle_exception
     */
    public function get_content_hash(): ?string {
        throw new \moodle_exception('This method is deprecated. Use get_instance_cache_key() instead');
    }


    /**
     * Update the cache key fragment for the instance.
     *
     * Each instance has it's own strategy for caching and generating cache keys. Each instance will need to implement this
     * function (and get_instance_cache_key())
     */
    abstract public function update_instance_cache_key();

    /**
     * Returns the cache key fragment for the instance data.
     *
     * Each instance has it's own strategy for caching and generating cache keys. Each instance will need to implement this
     * function (and update_instance_cache_key())
     *
     * @return string|null
     */
    abstract public function get_instance_cache_key(): ?string;

    /**
     * Returns the cache key fragment for the config.
     * If null, then caching should be avoided, both here and for the overall instance.
     *
     * By default, a hash of the config data is used for the cache key fragment.
     *
     * @return string|null
     */
    public function get_config_cache_key(): ?string {
        $cmstype = $this->cms->get_type();
        // By default, all stored configs are expected to have a stored hash to be used as a key. If one is not stored, then an
        // exception will be thrown.
        // If a datasource doesn't use caching (either returning '' or null), then this method should be overridden.
        $key = $cmstype->get_custom_data(self::get_shortname() . 'confighash');
        // We expect there to be something, so false, null, '', and 0 are all illigit.
        if (empty($key)) {
            throw new \moodle_exception('error:no_config_hash', 'mod_cms', '', $this->cms->get('id'));
        }
        return $key;
    }

    /**
     * Updates the config cache key fragment.
     *
     * By default, a hash of the config data is used for the cache key fragment.
     */
    public function update_config_cache_key() {
        $hash = hash(lib::HASH_ALGO, serialize($this->get_for_export()));
        // The config hash is stored with the CMS type.
        $cmstype = $this->cms->get_type();
        $cmstype->set_custom_data(self::get_shortname() . 'confighash', $hash);
        $cmstype->save();
    }

    /**
     * Gets the current cache key used for this datasource for this instance. It concatenates the instance and config keys.
     * If either key is null, then this function returns null.
     *
     * @return string|null
     */
    public function get_full_cache_key(): ?string {
        $ikey = $this->get_instance_cache_key();
        $ckey = $this->get_config_cache_key();
        if (is_null($ikey) || is_null($ckey)) {
            return null;
        }
        return $ikey . $ckey;
    }

    /**
     * Update the config hash.
     *
     * @deprecated
     * @throws \moodle_exception
     */
    public function update_config_hash() {
        throw new \moodle_exception('This method is deprecated. Use update_config_cache_key() instead');
    }

    /**
     * Called when deleting a CMS type.
     */
    public function config_on_delete() {
    }

    /**
     * Called when deleting a CMS instance.
     */
    public function instance_on_delete() {
    }

    /**
     * Create a structure of the config for backup.
     *
     * @param \backup_nested_element $parent
     */
    public function config_backup_define_structure(\backup_nested_element $parent) {
    }

    /**
     * Create a structure of the instance for backup.
     *
     * @param \backup_nested_element $parent
     */
    public function instance_backup_define_structure(\backup_nested_element $parent) {
    }

    /**
     * Add restore path elements to the restore activity.
     *
     * @param array $paths
     * @param \restore_cms_activity_structure_step $stepslib
     * @return array
     */
    public static function restore_define_structure(array $paths, \restore_cms_activity_structure_step $stepslib): array {
        return $paths;
    }
}
