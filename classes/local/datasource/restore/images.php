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

namespace mod_cms\local\datasource\restore;

use mod_cms\local\datasource\images as dsimages;

/**
 * Class to handle the restoring of images datasource backups.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class images {
    /** @var \restore_cms_activity_structure_step The stepslib controlling this process. */
    protected $stepslib;

    /** @var int/null Old ID of CMS type. */
    protected $oldtypeid = null;

    /**
     * Constructs the processor.
     *
     * @param \restore_cms_activity_structure_step $stepslib
     */
    public function __construct(\restore_cms_activity_structure_step $stepslib) {
        $this->stepslib = $stepslib;
        $stepslib->add_to_after_party($this);
    }

    /**
     * Restore image data
     *
     * @param array $data
     */
    public function process_restore_ds_images(array $data) {
        $this->oldtypeid = $this->stepslib->get_old_parentid('cms_types');
        $newtypeid = $this->stepslib->get_new_parentid('cms_types');

        // Set up a duplicate mapping specifically to set the context ID to satisfy restoration requirements.
        $this->stepslib->set_mapping('cms_types_images', $this->oldtypeid, $newtypeid, true, \context_system::instance()->id);
    }

    /**
     * Restore the images.
     */
    public function after_execute() {
        $this->stepslib->add_related_files(
            'mod_cms',
            dsimages::FILE_AREA,
            'cms_types_images',
            \context_system::instance()->id,
            $this->oldtypeid
        );
    }
}
