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

namespace mod_cms;

use mod_cms\local\model\cms_types;
use mod_cms\local\datasource\userlist;

/**
 * A trait providing support methods for using the test import file.
 *
 * @package   mod_cms
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait test_import1_trait {
    public $importfile = __DIR__ . '/test_import_1.yml';

    /**
     * Import a file defining a cms type.
     *
     * @param string $filename
     * @return cms_types
     */
    public function import(string $filename = ''): cms_types {
        if ($filename === '') {
            $filename = $this->importfile;
        }
        $importdata = file_get_contents($filename);
        $cmstype = new cms_types();
        $cmstype->import($importdata);
        return $cmstype;
    }

    /**
     * Create a course for testing.
     *
     * @return object
     */
    public function create_course() {
        $data = (object) [
            'fullname' => 'Fullname',
            'shortname' => 'Shortname',
            'category' => 1,
        ];
        return create_course($data);
    }

    /**
     * Creates a module for a course.
     *
     * @param int $typeid
     * @param int $courseid
     * @return object|\stdClass
     */
    public function create_module(int $typeid, int $courseid) {
        $moduleinfo = (object) [
            'modulename' => 'cms',
            'course' => $courseid,
            'section' => 0,
            'visible' => true,
            'typeid' => $typeid,
            'name' => 'Some module',
            'customfield_afield' => 'Field A',
            'userlist_name' => ['John', 'Jane'],
            'userlist_age' => ['23', '21'],
            'userlist_fav_hobby' => ['Poodle juggling', 'Water sculpting'],
            userlist::FORM_REPEATHIDDENNAME => 2,
        ];
        return create_module($moduleinfo);
    }
}
