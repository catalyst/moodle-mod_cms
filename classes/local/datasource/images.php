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
use stdClass;
use context_system;

/**
 * Data source for images defined in the template.
 *
 * Image datasources do not contain any instance specific data. Therefore the content is the same for all instances of a type.
 * Therefore the data would use the same cache. Because of this the instance cache key fragment is constant, and updating does
 * nothing.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class images extends base_mod_cms {
    /** The maximum amount of files allowed. */
    const MAX_FILES = 50;

    /** File area name used for storing images. */
    const FILE_AREA = 'cms_type_images';

    /**
     * Get the display name.
     *
     * @return string
     */
    public static function get_displayname(): string {
        return get_string('images:images', 'mod_cms');
    }

    /**
     * Get the metadata for the images stored with this datasource.
     *
     * @return array
     */
    public function get_file_metadata(): array {
        $fs = get_file_storage();
        return $fs->get_area_files(
            context_system::instance()->id,
            'mod_cms',
            self::FILE_AREA,
            $this->cms->get('typeid')
        );
    }

    /**
     * Pulls data from the datasource.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        $files = $this->get_file_metadata();

        $imagedata = new stdClass();
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $shortfilename = pathinfo($filename, PATHINFO_FILENAME);
            if ($filename == '.') {
                continue;
            }
            $url = \moodle_url::make_pluginfile_url(
                context_system::instance()->id,
                'mod_cms',
                self::FILE_AREA,
                $this->cms->get('typeid'),
                '/',
                $filename
            );
            $imagedata->$shortfilename = $url->out();
        }
        return $imagedata;
    }

    /**
     * Add fields to the CMS type config form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        global $CFG;

        // Add a heading.
        $mform->addElement('header', 'images_heading', get_string('images:config:header', 'cms'));

        // Images file manager.
        $mform->addElement('filemanager', 'images', get_string('images:images', 'cms'),
            null,
            [
                'subdirs' => 0,
                'maxbytes' => $CFG->maxbytes,
                'maxfiles' => self::MAX_FILES,
                'accepted_types' => ['web_image'],
            ]
        );
    }

    /**
     * Add data for use in config forms.
     *
     * @param mixed $data
     */
    public function config_form_default_data($data) {
        global $CFG;

        // Get an unused draft itemid which will be used for this form.
        $draftitemid = file_get_submitted_draft_itemid(self::FILE_AREA);

        // Copy the existing files which were previously uploaded
        // into the draft area used by this form.
        file_prepare_draft_area(
            $draftitemid,
            context_system::instance()->id,
            'mod_cms',
            self::FILE_AREA,
            $this->cms->get('typeid'),
            [
                'subdirs' => 0,
                'maxbytes' => $CFG->maxbytes,
                'maxfiles' => self::MAX_FILES,
            ]
        );
        $data->images = $draftitemid;
    }

    /**
     * Called after updating cms type to perform any extra saving required by datasource.
     *
     * @param mixed $data
     */
    public function config_on_update($data) {
        if (isset($data->images)) {
            file_save_draft_area_files(
                $data->images,
                context_system::instance()->id,
                'mod_cms',
                self::FILE_AREA,
                $this->cms->get('typeid')
            );
        }
        $this->update_config_cache_key();
    }

    /**
     * Get configuration data for exporting.
     *
     * @return \stdClass
     */
    public function get_for_export(): \stdClass {
        $files = $this->get_file_metadata();
        $data = new \stdClass();
        $data->files = [];

        foreach ($files as $file) {
            $filename = $file->get_filename();
            if ($filename === '.') {
                continue;
            }
            $obj = new \stdClass();
            $obj->filename = $filename;
            $obj->filesize = $file->get_filesize();
            $obj->mimetype = $file->get_mimetype();
            $obj->content = base64_encode($file->get_content());
            $data->files[] = $obj;
        }

        return $data;
    }

    /**
     * Import configuration from an object.
     *
     * @param \stdClass $data
     */
    public function set_from_import(\stdClass $data) {
        $fs = get_file_storage();

        if (!empty($data->files)) {
            foreach ($data->files as $filedata) {
                $filerecord = [
                    'component' => 'mod_cms',
                    'filearea' => self::FILE_AREA,
                    'itemid' => $this->cms->get('typeid'),
                    'filename' => $filedata->filename,
                    'filepath' => '/',
                    'contextid' => context_system::instance()->id,
                    'mimetype' => $filedata->mimetype,
                ];
                $fs->create_file_from_string($filerecord, base64_decode($filedata->content));
            }
        }
        $this->update_config_cache_key();
    }

    /**
     * Returns the cache key for the instance data.
     *
     * There is no instance specific data, so this key fragment is constant.
     *
     * @return string
     */
    public function get_instance_cache_key(): string {
        // There is no instance specific data, so this key fragment is constant.
        return '';
    }

    /**
     * Update the cache key fragment for the instance.
     *
     * There is no instance specific data, so this key fragment is constant.
     */
    public function update_instance_cache_key() {
        // There is no instance specific data, so this key fragment is constant.
    }

    /**
     * Called when deleting a CMS type.
     */
    public function config_on_delete() {
        $fs = get_file_storage();
        $fs->delete_area_files(context_system::instance()->id, 'mod_cms', self::FILE_AREA, $this->cms->get('typeid'));
    }

    /**
     * Create a structure of the config for backup.
     *
     * @param \backup_nested_element $parent
     */
    public function config_backup_define_structure(\backup_nested_element $parent) {
        $element = new \backup_nested_element('images', [], ['dummy']);
        $parent->add_child($element);
        $element->annotate_files('mod_cms', self::FILE_AREA, \backup::VAR_PARENTID, context_system::instance()->id);
        // We place some dummy information into the backup to ensure that the restore method gets called.
        $element->set_source_array([['dummy' => 'dummy']]);
    }

    /**
     * Add restore path elements to the restore activity.
     *
     * @param array $paths
     * @param \restore_cms_activity_structure_step $stepslib
     * @return array
     */
    public static function restore_define_structure(array $paths, \restore_cms_activity_structure_step $stepslib): array {
        $processor = new restore\images($stepslib);

        $element = new \restore_path_element('restore_ds_images', '/activity/cms/cms_types/config_datasources/images');
        $element->set_processing_object($processor);
        $paths[] = $element;

        return $paths;
    }
}
