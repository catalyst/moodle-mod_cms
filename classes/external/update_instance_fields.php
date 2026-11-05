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

namespace mod_cms\external;

use context_module;
use mod_cms\local\datasource\fields as dsfields;
use mod_cms\local\model\cms;
use mod_cms\local\renderer;

defined('MOODLE_INTERNAL') || die();

// Moodle 4.1 has the external API classes in the global namespace, later versions in core_external.
if (!class_exists('core_external\\external_api')) {
    global $CFG;
    require_once($CFG->libdir . '/externallib.php');
    $externalclasses = [
        'external_api',
        'external_function_parameters',
        'external_multiple_structure',
        'external_single_structure',
        'external_value',
    ];
    foreach ($externalclasses as $class) {
        class_alias($class, 'core_external\\' . $class);
    }
}

/**
 * Sets custom field values on a CMS activity instance.
 *
 * @package   mod_cms
 * @author    Brendan Heywood <brendan@catalyst-au.net>
 * @copyright 2026, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_instance_fields extends \core_external\external_api {
    /**
     * Describes the parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'cmid' => new \core_external\external_value(PARAM_INT, 'The course module id of the CMS activity'),
            'fields' => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'shortname' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'The short name of the custom field'),
                    'value' => new \core_external\external_value(PARAM_RAW, 'The new value of the custom field'),
                ]),
                'The custom field values to set. Fields that are not listed are left unchanged.'
            ),
        ]);
    }

    /**
     * Sets the custom field values of a CMS activity instance.
     *
     * @param int $cmid
     * @param array $fields Array of ['shortname' => string, 'value' => string].
     * @return array ['updated' => string[]] The short names of the fields that were set.
     */
    public static function execute(int $cmid, array $fields): array {
        ['cmid' => $cmid, 'fields' => $fields] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'fields' => $fields]
        );

        $cm = get_coursemodule_from_id('cms', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $cms = new cms($cm->instance);
        $ds = new dsfields($cms);
        if (!$ds->is_enabled()) {
            throw new \moodle_exception('Custom fields are not enabled for this CMS type');
        }

        $data = (object) ['id' => $cms->get('id')];
        $updated = [];
        foreach ($fields as $field) {
            $data->{'customfield_' . $field['shortname']} = $field['value'];
            $updated[] = $field['shortname'];
        }

        // Updates the data, and the cache key for the fields datasource.
        $ds->update_instance($data, false);

        // The name of the instance may be derived from the custom fields.
        $renderer = new renderer($cms);
        $name = $renderer->get_name();
        if ($name !== $cms->get('name')) {
            $cms->set('name', $name);
            $cms->save();
            rebuild_course_cache($cm->course, true);
        }

        return ['updated' => $updated];
    }

    /**
     * Describes the return value.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'updated' => new \core_external\external_multiple_structure(
                new \core_external\external_value(PARAM_ALPHANUMEXT, 'The short name of a custom field that was set')
            ),
        ]);
    }
}
