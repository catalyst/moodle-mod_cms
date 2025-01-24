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

use mod_cms\local\datasource\fields as dsfields;
use mod_cms\local\model\cms;
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
 * @coversDefaultClass \mod_cms\search\activity
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

        $this->cmsareaid = \core_search\manager::generate_areaid('mod_cms', 'activity');

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        $search = \testable_core_search::instance();

        // Name for cms activity is using from "title_mustache".
        $cmstype = new cms_types();
        $cmstype->set('name', 'Overview')
            ->set('idnumber', 'overview')
            ->set('mustache', 'Template doc {{fields.overview}}')
            ->set('datasources', ['fields'])
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
            'configdata' => json_encode(['defaultvalue' => 'Default Text Overview']),
        ]);
        $this->cmstype = $cmstype;
        $this->fieldcategory = $fieldcategory;
        $this->field = $field;
    }

    /**
     * Test search enabled.
     *
     * @return void
     * @covers \core_search\manager::get_search_area
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
     * @covers ::get_document_recordset
     * @covers ::get_document
     */
    public function test_get_document_recordset(): void {
        global $DB;

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->cmsareaid);
        $this->assertInstanceOf('\mod_cms\search\activity', $searcharea);

        $course = self::getDataGenerator()->create_course();
        $overviews = [];

        // The name of cms activity is from cms_type, so we do not set when creating the activity.
        $generator = self::getDataGenerator()->get_plugin_generator('mod_cms');
        $overview1 = 'Test overview text 1';
        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = $overview1;
        $record->typeid = $this->cmstype->get('id');
        $cms1 = $generator->create_instance_with_data($record);
        $overviews[$cms1->id] = $overview1;

        $overview2 = 'Test overview text 2';
        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = $overview2;
        $record->typeid = $this->cmstype->get('id');
        $cms2 = $generator->create_instance_with_data($record);
        $overviews[$cms2->id] = $overview2;

        // All records.
        $recordset = $searcharea->get_document_recordset();
        $this->assertTrue($recordset->valid());
        $this->assertEquals(2, iterator_count($recordset));
        $recordset->close();

        // Search again by current time + 2 sec.
        $recordset = $searcharea->get_document_recordset(time() + 2);
        // No new records.
        $this->assertFalse($recordset->valid());
        $recordset->close();

        // Wait 1 sec to have new search string.
        sleep(1);
        $time = time();
        $overview3 = 'Test overview text 3';
        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = $overview3;
        $record->typeid = $this->cmstype->get('id');
        $cms3 = $generator->create_instance_with_data($record);
        $context = \context_module::instance($cms3->cmid);
        $overviews[$cms3->id] = $overview3;

        // Return only new search.
        $recordset = $searcharea->get_document_recordset($time);
        $count = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);
            $this->assertEquals('mod_cms-activity-' . $record->id, $doc->get('id'));
            $this->assertEquals($record->id, $doc->get('itemid'));
            $this->assertEquals($course->id, $doc->get('courseid'));
            $this->assertEquals($context->id, $doc->get('contextid'));
            $this->assertEquals($this->field->get('name'), $doc->get('title'));
            $this->assertStringContainsString($overviews[$doc->get('itemid')], $doc->get('content'));
            $count++;
        }
        $this->assertEquals(1, $count);
        $recordset->close();

        // Update existing data.
        $cms = new cms($cms1->id);
        $ds = new dsfields($cms);
        $ds->update_instance((object) ['id' => $cms1->id, 'customfield_overview' => 'Update test 1'], false);

        // Return 2 records.
        $recordset = $searcharea->get_document_recordset($time);
        $this->assertTrue($recordset->valid());
        $this->assertEquals(2, iterator_count($recordset));
        $recordset->close();
    }

    /**
     * Test default value from cms content type
     *
     * @return void
     * @covers ::get_document_recordset
     * @covers ::get_document
     */
    public function test_default_content(): void {
        $searcharea = \core_search\manager::get_search_area($this->cmsareaid);
        $this->assertInstanceOf('\mod_cms\search\activity', $searcharea);

        $course = self::getDataGenerator()->create_course();

        // Create cms activity without customfield.
        $generator = self::getDataGenerator()->get_plugin_generator('mod_cms');
        $record = new \stdClass();
        $record->course = $course->id;
        $record->typeid = $this->cmstype->get('id');
        $cms1 = $generator->create_instance_with_data($record);

        $recordset = $searcharea->get_document_recordset();
        $count = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);
            // Confirm the content is from defaultvalue from cms fieldtype.
            $this->assertStringContainsString('Default Text Overview', $doc->get('content'));
            $count++;
        }
        $this->assertEquals(1, $count);
        $recordset->close();

        // Add custom data for the cms activity.
        $cms = new cms($cms1->id);
        $ds = new dsfields($cms);
        $ds->update_instance((object) ['id' => $cms1->id, 'customfield_overview' => 'Update test 1'], false);
        $recordset = $searcharea->get_document_recordset();
        $count = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertStringContainsString('Update test 1', $doc->get('content'));
            $count++;
        }
        $this->assertEquals(1, $count);
        $recordset->close();
    }

    /**
     * Test multiple contents in one cms activity
     *
     * @return void
     * @covers ::get_document_recordset
     * @covers ::get_document
     */
    public function test_multiple_contents(): void {
        global $DB;

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->cmsareaid);
        $this->assertInstanceOf('\mod_cms\search\activity', $searcharea);

        $cmstype = new cms_types();
        $cmstype->set('name', 'Multiple content')
            ->set('idnumber', 'multiplecontent')
            ->set('mustache', 'Overview: {{fields.overview}} Details: {{fields.details}}')
            ->set('datasources', ['fields'])
            ->set('title_mustache', 'Multiple content');
        $cmstype->save();
        $fieldcategory = self::getDataGenerator()->create_custom_field_category([
            'name' => 'Multiple fields',
            'component' => 'mod_cms',
            'area' => 'cmsfield',
            'itemid' => $cmstype->get('id'),
        ]);
        $field1 = self::getDataGenerator()->create_custom_field([
            'name' => 'Overview',
            'shortname' => 'overview',
            'type' => 'text',
            'categoryid' => $fieldcategory->get('id'),
            'configdata' => json_encode(['defaultvalue' => 'Default Text Overview']),
        ]);
        $field2 = self::getDataGenerator()->create_custom_field([
            'name' => 'Details',
            'shortname' => 'details',
            'type' => 'text',
            'categoryid' => $fieldcategory->get('id'),
            'configdata' => json_encode(['defaultvalue' => 'Default Text Details']),
        ]);
        $course = self::getDataGenerator()->create_course();

        $generator = self::getDataGenerator()->get_plugin_generator('mod_cms');
        $record = new \stdClass();
        $record->course = $course->id;
        $record->typeid = $cmstype->get('id');
        $cms1 = $generator->create_instance_with_data($record);

        $recordset = $searcharea->get_document_recordset();
        $count = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);
            $this->assertStringContainsString('Default Text Overview', $doc->get('content'));
            $this->assertStringContainsString('Default Text Details', $doc->get('content'));
            $count++;
        }
        $this->assertEquals(1, $count);
        $recordset->close();

        // Add data for the cms activity.
        $cms = new cms($cms1->id);
        $ds = new dsfields($cms);
        $cmsfields = new \stdClass();
        $cmsfields->id = $cms1->id;
        $cmsfields->customfield_overview = 'Overview test 1';
        $cmsfields->customfield_details = 'Details test 1';
        $ds->update_instance($cmsfields, false);
        $recordset = $searcharea->get_document_recordset();
        $count = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            // Both strings are contained in the cms activity.
            $this->assertStringContainsString('Overview test 1', $doc->get('content'));
            $this->assertStringContainsString('Details test 1', $doc->get('content'));
            $count++;
        }
        $this->assertEquals(1, $count);
        $recordset->close();
    }

    /**
     * Test check_access.
     *
     * @return void
     * @covers ::check_access
     */
    public function test_check_access(): void {
        global $DB;

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->cmsareaid);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');

        $generator = self::getDataGenerator()->get_plugin_generator('mod_cms');
        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = 'Test overview text 1';
        $record->typeid = $this->cmstype->get('id');
        $cms = $generator->create_instance_with_data($record);

        $records = $DB->get_records('customfield_data', ['fieldid' => $this->field->get('id')]);
        $this->assertCount(1, $records);

        $this->setAdminUser();
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($cms->id));

        $this->setUser($user1);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($cms->id));

        $this->setUser($user2);
        $this->assertEquals(\core_search\manager::ACCESS_DENIED, $searcharea->check_access($cms->id));
    }

    /**
     * Test getting document catches errors.
     *
     * @return void
     * @covers ::get_document
     */
    public function test_getting_document_catch_errors(): void {
        global $DB;

        $searcharea = \core_search\manager::get_search_area($this->cmsareaid);
        $course = self::getDataGenerator()->create_course();

        $generator = self::getDataGenerator()->get_plugin_generator('mod_cms');
        $record = new \stdClass();
        $record->course = $course->id;
        $record->customfield_overview = 'Test overview text 1';
        $record->typeid = $this->cmstype->get('id');
        $cms = $generator->create_instance_with_data($record);

        // Let's break cms record so getting a document would throw an exception.
        $cms->typeid = 8888;
        $DB->update_record('cms', $cms);

        // Test that an exception is not thrown, but debugging is triggered instead.
        $this->assertEmpty($searcharea->get_document($cms));
        $debuggingmessages = $this->getDebuggingMessages();
        $this->assertDebuggingCalled();

        $this->assertStringContainsString(
            'Error getting mod_cms document for global search. cmid: ' . $cms->cmid . '  courseid: '. $course->id,
            reset($debuggingmessages)->message
        );
    }
}
