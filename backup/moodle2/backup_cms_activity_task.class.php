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

/**
 * Backup task for mod_cms.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/cms/backup/moodle2/backup_cms_stepslib.php');

/**
 * Backup task for mod_cms.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_cms_activity_task extends backup_activity_task {
    /**
     * Defines activity specific settings to be added to the common ones
     */
    public function define_my_settings() {
        // TODO: Implement define_my_settings() method.
    }

    /**
     * Defines activity specific steps for this task
     */
    public function define_my_steps() {
        $this->add_step(new backup_cms_activity_structure_step('cms_structure', 'cms.xml'));
    }

    /**
     * Encodes URLs to the activity instance's scripts into a site-independent form
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the index.
        $search = "/(".$base."\/mod\/cms\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@CMSINDEX*$2@$', $content);

        // Link to cms view by moduleid.
        $search = "/(".$base."\/mod\/cms\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@CMSVIEWBYID*$2@$', $content);

        return $content;
    }
}
