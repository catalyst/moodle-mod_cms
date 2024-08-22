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
 * CMS search unit tests.
 *
 * @package     mod_cms
 * @category    test
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.com>
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_cms\search;

use mod_cms\local\model\cms_types;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');

/**
 * Test class for cmsfield search.
 *
 * @package     mod_cms
 * @category    test
 * @author      Tomo Tsuyuki <tomotsuyuki@catalyst-au.com>
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_cms\search\cmsfield
 */
class search_test extends \advanced_testcase {

    /**
     * @var string Area id
     */
    protected $cmsareaid = null;

    /**
     * @var cms_types CMS type object
     */
    protected $cmstype = null;

    /**
     * @var \core_customfield\category_controller Custom field category object
     */
    protected $fieldcategory = null;

    /**
     * @var \core_customfield\field_controller Custom field object
     */
    protected $field = null;

    /**
     * Set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        set_config('enableglobalsearch', true);

        $this->cmsareaid = \core_search\manager::generate_areaid('mod_cms', 'cmsfield');

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        $search = \testable_core_search::instance();

        $cmstype = new cms_types();
        $cmstype->set('name', 'Overview')
            ->set('idnumber', 'overview')
            ->set('title_mustache', 'Overview');
        $cmstype->save();
        $fieldcategory = self::getDataGenerator()->create_custom_field_category([
            'name' => 'Other fields',
            'component' => 'mod_cms',
            'area' => 'cmsfield',
            'itemid' => $cmstype->get('id'),
        ]);
        $field = self::getDataGenerator()->create_custom_field([
            'name' => 'Overview',
            'shortname' => 'overview',
            'type' => 'text',
            'categoryid' => $fieldcategory->get('id'),
        ]);
        $this->cmstype = $cmstype;
        $this->fieldcategory = $fieldcategory;
        $this->field = $field;
    }

    /**
     * Test search enabled.
     *
     * @return void
     */
    public function test_search_enabled(): void {
        $searcharea = \core_search\manager::get_search_area($this->cmsareaid);
        list($componentname, $varname) = $searcharea->get_config_var_name();

        // Enabled by default once global search is enabled.
        $this->assertTrue($searcharea->is_enabled());

        set_config($varname . '_enabled', 0, $componentname);
        $this->assertFalse($searcharea->is_enabled());

        set_config($varname . '_enabled', 1, $componentname);
        $this->assertTrue($searcharea->is_enabled());
    }

    /**
     * Indexing mod cms contents.
     *
     * @return void
     */
    public function test_get_document_recordset(): void {
        global $DB;

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->cmsareaid);
        $this->assertInstanceOf('\mod_cms\search\cmsfield', $searcharea);

        $course = self::getDataGenerator()->create_course();

        // Name for cms is coming from "title_mustache" in cms_type.
        $generator = self::getDataGenerator()->get_plugin_generator('mod_cms');
        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = 'Test overview text 1';
        $record->typeid = $this->cmstype->get('id');
        $generator->create_instance_with_data($record);

        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = 'Test overview text 2';
        $record->typeid = $this->cmstype->get('id');
        $generator->create_instance_with_data($record);

        // All records.
        $recordset = $searcharea->get_document_recordset();
        $this->assertTrue($recordset->valid());
        $this->assertEquals(2, iterator_count($recordset));
        $recordset->close();

        // The +2 is to prevent race conditions.
        $recordset = $searcharea->get_document_recordset(time() + 2);

        // No new records.
        $this->assertFalse($recordset->valid());
        $recordset->close();

        // Wait 1 sec to have new search string.
        sleep(1);
        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = 'Test overview text 3';
        $record->typeid = $this->cmstype->get('id');
        $generator->create_instance_with_data($record);

        // Return only new search.
        $recordset = $searcharea->get_document_recordset(time());
        $count = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $data = $DB->get_record('customfield_data', ['id' => $record->id]);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);
            $this->assertEquals('mod_cms-cmsfield-' . $data->id, $doc->get('id'));
            $this->assertEquals($data->id, $doc->get('itemid'));
            $this->assertEquals($course->id, $doc->get('courseid'));
            $this->assertEquals($data->contextid, $doc->get('contextid'));
            $this->assertEquals($this->field->get('name'), $doc->get('title'));
            $this->assertEquals($data->value, $doc->get('content'));

            // Static caches are working.
            $dbreads = $DB->perf_get_reads();
            $doc = $searcharea->get_document($record);
            $this->assertEquals($dbreads, $DB->perf_get_reads());
            $this->assertInstanceOf('\core_search\document', $doc);
            $count++;
        }
        $this->assertEquals(1, $count);
        $recordset->close();
    }

    /**
     * Document contents.
     *
     * @return void
     */
    public function test_check_access(): void {
        global $DB;

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->cmsareaid);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');

        // Name for cms is coming from "title_mustache" in cms_type.
        $generator = self::getDataGenerator()->get_plugin_generator('mod_cms');
        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = 'Test overview text 1';
        $record->typeid = $this->cmstype->get('id');
        $generator->create_instance_with_data($record);

        $records = $DB->get_records('customfield_data', ['fieldid' => $this->field->get('id')]);
        $this->assertCount(1, $records);
        $data = current($records);

        $this->setAdminUser();
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($data->id));

        $this->setUser($user1);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($data->id));

        $this->setUser($user2);
        $this->assertEquals(\core_search\manager::ACCESS_DENIED, $searcharea->check_access($data->id));
    }
}
