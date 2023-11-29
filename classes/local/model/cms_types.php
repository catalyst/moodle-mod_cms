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
 * A persistent for the mdl_cms_types table
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_cms\local\model;

use core\persistent;
use mod_cms\event\cms_type_created;
use mod_cms\event\cms_type_updated;
use mod_cms\event\cms_type_deleted;
use mod_cms\exportable;
use mod_cms\importable;
use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\lib;
use mod_cms\local\renderer;

/**
 * A persistent for the mdl_cms_types table
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cms_types extends persistent {
    use exportable, importable, persistent_extras;

    /**
     * Table name.
     */
    const TABLE = 'cms_types';

    /** File area name used for storing images. */
    const ICON_FILE_AREA = 'cms_types_icon';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'idnumber' => [
                'type' => PARAM_TEXT,
            ],
            'description' => [
                'type' => PARAM_RAW,
                'default' => ''
            ],
            'descriptionformat' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'title_mustache' => [
                'type' => PARAM_TEXT,
                'default' => ''
            ],
            'mustache' => [
                'type' => PARAM_RAW,
                'default' => '{{{debug}}}'
            ],
            'datasources' => [
                'type' => PARAM_TEXT,
                'default' => ''
            ],
            'customdata' => [
                'type' => PARAM_TEXT,
                'default' => '{}',
            ],
            'isvisible' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
        ];
    }

    /**
     * Getter for datasources.
     *
     * @return array
     */
    protected function get_datasources(): array {
        return explode(',', $this->raw_get('datasources'));
    }

    /**
     * Setter for datasources.
     *
     * @param array $value
     */
    protected function set_datasources(array $value) {
        $this->raw_set('datasources', implode(',', $value));
    }

    /**
     * Sets an arbitrary value.
     *
     * @param string $name
     * @param mixed $value
     */
    public function set_custom_data(string $name, $value) {
        $cdata = json_decode($this->raw_get('customdata'), false);
        if (is_null($cdata)) {
            $cdata = new \stdClass();
        }
        // If the value is null, then let's remove the value entirely.
        if (is_null($value)) {
            unset($cdata->$name);
        } else {
            $cdata->$name = $value;
        }
        $this->raw_set('customdata', json_encode($cdata));
    }

    /**
     * Retrieves an arbitrary value.
     *
     * @param string $name
     * @return mixed
     */
    public function get_custom_data(string $name) {
        $cdata = json_decode($this->raw_get('customdata'), false);
        return $cdata->$name ?? null;
    }

    /**
     * Get the metadata for the icon, or null if no icon is stored.
     *
     * @return \stored_file|null
     */
    public function get_icon_metadata(): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            'mod_cms',
            self::ICON_FILE_AREA,
            $this->get('id')
        );
        // There should be 0 or 1 files. We ignore the directory (.) entry.
        foreach ($files as $file) {
            if ($file->get_filename() !== '.') {
                return $file;
            }
        }
        return null;
    }

    /**
     * Gets the URL for the type icon.
     *
     * @return \moodle_url|null
     */
    public function get_type_icon(): ?\moodle_url {
        $file = $this->get_icon_metadata();

        $url = null;
        // There should only be at most one entry in the array.
        if (!is_null($file)) {
            $filename = $file->get_filename();
            $url = \moodle_url::make_pluginfile_url(
                \context_system::instance()->id,
                'mod_cms',
                self::ICON_FILE_AREA,
                $this->get('id'),
                '/',
                $filename
            );
        }
        return $url;
    }

    /**
     * Validates idnumber parameter.
     *
     * @param string $value
     * @return bool|\lang_string
     */
    protected function validate_idnumber(string $value) {
        // Test requirement (null values are already tested).
        if ($value === '') {
            return new \lang_string('requiredelement', 'form');
        }
        // Test for duplicates.
        if (self::record_exists_select("idnumber = ? AND id != ?", [$value, $this->get('id')])) {
            return new \lang_string('idnumber_exists', 'mod_cms', $value);
        }
        return true;
    }

    /**
     * Validates title_mustache
     *
     * @param string $value
     * @return bool|\lang_string
     */
    protected function validate_title_mustache(string $value) {
        $result = renderer::validate_template($value);
        if ($result !== true) {
            return new \lang_string('error:invalid', 'mod_cms', $result);
        }
        return true;
    }

    /**
     * Validates mustache
     *
     * @param string $value
     * @return bool|\lang_string
     */
    protected function validate_mustache(string $value) {
        $result = renderer::validate_template($value);
        if ($result !== true) {
            return new \lang_string('error:invalid', 'mod_cms', $result);
        }
        return true;
    }

    /**
     * Hook to execute after a create to trigger an event.
     *
     * @return void
     */
    protected function after_create(): void {
        $event = cms_type_created::create([
            'objectid' => $this->raw_get('id'),
            'userid' => $this->raw_get('usermodified'),
            'contextid' => \context_system::instance()->id,
        ]);
        $event->trigger();
    }

    /**
     * Hook to execute after an update to trigger an event.
     *
     * @param bool $result
     *
     * @return void
     */
    protected function after_update($result): void {
        if ($result) {
            $event = cms_type_updated::create([
                'objectid' => $this->raw_get('id'),
                'userid' => $this->raw_get('usermodified'),
                'contextid' => \context_system::instance()->id,
            ]);
            $event->trigger();
        }
    }

    /**
     * Hook to execute after a delete to trigger an event.
     *
     * @param bool $result
     *
     * @return void
     */
    protected function after_delete($result): void {
        if ($result) {
            $event = cms_type_deleted::create([
                'objectid' => $this->raw_get('id'),
                'userid' => $this->raw_get('usermodified'),
                'contextid' => \context_system::instance()->id,
            ]);
            $event->trigger();
        }
    }

    /**
     * Whether or not we can delete this instance.
     *
     * @return bool
     */
    public function can_delete(): bool {
        // We cannot delete if there is at least one instance of this configuration.
        return cms::count_records(['typeid' => $this->get('id')]) === 0;
    }

    /**
     * Get configuration data for exporting.
     *
     * @return \stdClass
     */
    public function get_for_export(): \stdClass {
        $export = new \stdClass();
        foreach (self::define_properties() as $name => $def) {
            $export->$name = $this->get($name);
        }
        unset($export->customdata);

        $file = $this->get_icon_metadata();

        // There should be at most one file.
        if (!is_null($file)) {
            $filename = $file->get_filename();
            $obj = new \stdClass();
            $obj->filename = $filename;
            $obj->filesize = $file->get_filesize();
            $obj->mimetype = $file->get_mimetype();
            $obj->content = base64_encode($file->get_content());
            $export->icon = $obj;
        }

        $export->datasourcedefs = new \stdClass();
        foreach (dsbase::get_datasources($this) as $ds) {
            $name = $ds::get_shortname();
            $data = $ds->get_for_export();
            if (!is_null($data)) {
                $export->datasourcedefs->$name = $data;
            }
        }

        return $export;
    }

    /**
     * Import configuration from an object.
     *
     * @param \stdClass $data
     */
    public function set_from_import(\stdClass $data) {
        foreach (self::define_properties() as $name => $def) {
            if (isset($data->$name)) {
                $this->set($name, $data->$name);
            }
        }

        $this->create();

        if (isset($data->icon)) {
            $fs = get_file_storage();
            $filerecord = [
                'component' => 'mod_cms',
                'filearea' => self::ICON_FILE_AREA,
                'itemid' => $this->get('id'),
                'filename' => $data->icon->filename,
                'filepath' => '/',
                'contextid' => \context_system::instance()->id,
                'mimetype' => $data->icon->mimetype,
            ];
            $fs->create_file_from_string($filerecord, base64_decode($data->icon->content));
        }

        foreach ($data->datasourcedefs as $name => $data) {
            $ds = dsbase::get_datasource($name, $this);
            $ds->set_from_import($data);
        }
        // Update this object with whatever was added to the database.
        $this->read();
    }

    /**
     * Returns a cache key representing the contents of the CMS type.
     *
     * @return string|null
     */
    public function get_cache_key(): ?string {
        $data = $this->to_record();

        $file = $this->get_icon_metadata();
        if (!is_null($file)) {
            $data->filename = $file->get_filename();
            $data->filesize = $file->get_filesize();
            $data->mimetype = $file->get_mimetype();
        }

        return hash(lib::HASH_ALGO, serialize($data));
    }

    /**
     * Get a sample cms of this type, filled with arbitrary data.
     *
     * @return cms
     */
    public function get_sample_cms(): cms {
        $cms = new cms();
        $cms->set('typeid', $this->get('id'));

        $cms->set('name', get_string('some_name', 'mod_cms'));
        $cms->issample = true;

        return $cms;
    }
}
