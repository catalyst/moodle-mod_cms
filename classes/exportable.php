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

namespace mod_cms;

use Symfony\Component\Yaml\Yaml;

/**
 * Exportability
 *
 * @package    mod_cms
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait exportable {
    /** @var string Mime type for the downloaded file. */
    private $mimetype = 'application/x-yaml';

    /**
     * Exports this data in YAML format.
     *
     * @param string $format Either 'yaml', 'txt' or 'preview'.
     */
    public function export(string $format = 'yaml') {
        if ($format == 'preview') {
            // Preview in the browser.
            header("Content-Type: text/plain\n");
        } else if ($format == 'txt') {
            $this->send_header($this->get_filename() . '.txt');
        } else {
            $this->send_header($this->get_filename());
        }
        echo Yaml::dump(
            $this->get_for_export(),
            helper::YAML_DUMP_INLINE_LEVEL,
            helper::YAML_DUMP_INDENT_LEVEL,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | YAML::DUMP_OBJECT_AS_MAP
        );
    }

    /**
     * Output file headers to initialise the download of the file.
     *
     * @param string $filename The name of the file.
     */
    private function send_header(string $filename) {
        if (defined('BEHAT_SITE_RUNNING') || PHPUNIT_TEST) {
            // For text based formats - we cannot test the output with behat if we force a file download.
            return;
        }
        if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
            header('Cache-Control: max-age=10');
            header('Pragma: ');
        } else { // Normal http - prevent caching at all cost.
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
            header('Pragma: no-cache');
        }
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header("Content-Type: $this->mimetype\n");
        header("Content-Disposition: attachment; filename=\"$filename\"");
    }

    /**
     * Get the filename for the exported file.
     *
     * @param   int|null $timestamp The Unix timestamp to use in the file name.
     * @return  string $filename
     */
    private function get_filename(?int $timestamp = null): string {
        $now = $now ?? time();

        $filename = str_replace(' ', '_', $this->get('name'));
        $filename = clean_filename($filename);
        $filename .= clean_filename('_' . gmdate('Ymd_Hi', $now));
        $filename .= '.yml';

        return $filename;
    }
}
