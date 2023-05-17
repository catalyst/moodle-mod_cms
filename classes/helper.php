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
 * Helper methods for mod_cms
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_cms;

use pix_icon;

/**
 * Helper methods for mod_cms
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Get a filler icon for display in the actions column of a table.
     *
     * @param string $url
     * @param string $icon
     * @param string $alt
     * @param string $iconcomponent
     * @param array  $options
     *
     * @return string
     */
    public static function format_icon_link(string $url, string $icon, string $alt,
            ?string $iconcomponent = 'moodle', array $options = []): string {
        global $OUTPUT;

        return $OUTPUT->action_icon(
            $url,
            new pix_icon($icon, $alt, $iconcomponent, ['title' => $alt]),
            null,
            $options
        );
    }
}
