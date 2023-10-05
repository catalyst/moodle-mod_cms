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

/**
 * Helper methods for mod_cms
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /** The level where you switch to inline YAML. */
    public const YAML_DUMP_INLINE_LEVEL = 5;

    /** The amount of spaces to use for indentation of nested nodes. */
    public const YAML_DUMP_INDENT_LEVEL = 2;

    /**
     * Get a filler icon for display in the actions column of a table.
     *
     * @param string $url
     * @param string $icon
     * @param string $alt
     * @param string|null $iconcomponent
     * @param array  $options
     * @return string
     */
    public static function format_icon_link(string $url, string $icon, string $alt,
            ?string $iconcomponent = 'moodle', array $options = []): string {
        global $OUTPUT;

        return $OUTPUT->action_icon(
            $url,
            new \pix_icon($icon, $alt, $iconcomponent, ['title' => $alt]),
            null,
            $options
        );
    }

    /**
     * Get a link icon for deleting. Includes a confirm dialog.
     *
     * @param \moodle_url $url
     * @param string $confirmstring
     *
     * @return string
     */
    public static function format_delete_link(\moodle_url $url, string $confirmstring): string {
        global $OUTPUT;
        $confirmaction = new \confirm_action($confirmstring);
        $deleteicon = new \pix_icon('t/delete', get_string('delete'));
        $link = new \action_link($url, '', $confirmaction, null,  $deleteicon);
        return $OUTPUT->render($link);

    }
}
