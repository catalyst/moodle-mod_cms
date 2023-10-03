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

use core\output\notification;
use mod_cms\form\cms_types_form;
use mod_cms\form\cms_types_import_form;
use mod_cms\local\datasource\base as dsbase;
use mod_cms\local\lib;
use mod_cms\local\model\cms_types;
use mod_cms\local\table\content_types_list;

/**
 * Class for managing the custom content types.
 *
 * TODO: This class needs some cleaning up. Most functions can be made static.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_content_types {

    /** @var \renderer_base Locally cached $OUTPUT object. */
    protected $output;

    /** @var cms_types|null A locally stored instance. */
    protected $_instance = null;

    /**
     * Constructor.
     */
    public function __construct() {
        global $OUTPUT;

        $this->output = $OUTPUT;
    }

    /**
     * Execute the required action.
     *
     * @param string $action
     *
     * @return void
     */
    public function execute(string $action): void {
        $this->set_external_page();
        $this->add_breadcrumb($action);
        switch($action) {
            case 'add':
            case 'edit':
                $this->edit($action, optional_param('id', null, PARAM_INT));
                break;
            case 'import':
                $this->import();
                break;
            case 'export':
                $this->export(
                    required_param('id', PARAM_INT),
                    optional_param('format', 'yaml', PARAM_ALPHA)
                );
                break;
            case 'delete':
                require_sesskey();
                if ($this->delete(required_param('id', PARAM_INT))) {
                    redirect(
                        new \moodle_url(self::get_base_url()),
                        get_string('deleted'),
                        null,
                        notification::NOTIFY_SUCCESS
                    );
                } else {
                    redirect(
                        new \moodle_url(self::get_base_url()),
                        get_string('error:cant_delete_content_type', 'mod_cms'),
                        null,
                        notification::NOTIFY_WARNING
                    );
                }
                break;
            case 'show':
                $this->set_visibility(required_param('id', PARAM_INT), true);
                redirect(
                    new \moodle_url(self::get_base_url()),
                    get_string('visibility_updated', 'mod_cms'),
                    null,
                    notification::NOTIFY_SUCCESS
                );
            case 'hide':
                $this->set_visibility(required_param('id', PARAM_INT), false);
                redirect(
                    new \moodle_url(self::get_base_url()),
                    get_string('visibility_updated', 'mod_cms'),
                    null,
                    notification::NOTIFY_SUCCESS
                );
            case 'view':
            default:
                $this->view();
                break;
        }
    }

    /**
     * Set external page.
     *
     * @return void
     */
    protected function set_external_page(): void {
        admin_externalpage_setup('mod_cms/managetypes');
    }

    /**
     * Adds breadcrumbs depending on the action being taken.
     *
     * @param string $action
     */
    protected function add_breadcrumb(string $action) {
        global $PAGE;

        switch($action) {
            case 'add':
                $PAGE->navbar->add($this->get_new_heading());
                break;
            case 'edit':
                // Need to add extra breadcrumbs when URL does not exactly match admin setting page URL.
                foreach (['modcmsfolder', 'mod_cms/managetypes'] as $label) {
                    if ($node = $PAGE->settingsnav->find($label, \navigation_node::TYPE_SETTING)) {
                        $PAGE->navbar->add($node->get_content(), $node->action());
                    }
                }
                $PAGE->navbar->add($this->get_edit_heading());
                break;
            default:
        }
    }

    /**
     * Return record instance.
     *
     * @param int      $id
     *
     * @return cms_types
     */
    protected function get_instance($id = 0): cms_types {
        if (!isset($this->_instance)) {
            $this->_instance = new cms_types($id);
        }
        return $this->_instance;
    }

    /**
     * Print out all records in the table.
     *
     * @return void
     */
    protected function display_all_records(): void {
        $records = cms_types::get_records([], 'name');

        $table = new content_types_list();
        $table->display($records);
    }

    /**
     * Returns a text for create new record button.
     *
     * @return string
     */
    protected function get_create_button_text(): string {
        return get_string('addnewtype', 'mod_cms');
    }

    /**
     * Returns a form for the record.
     *
     * @param cms_types|null $type
     * @return cms_types_form
     */
    protected function get_form(?cms_types $type = null): cms_types_form {
        global $PAGE;

        return new cms_types_form($PAGE->url->out(false), ['persistent' => $type]);
    }

    /**
     * View page heading string.
     *
     * @return string
     */
    protected function get_view_heading(): string {
        return get_string('managetypes', 'mod_cms');
    }

    /**
     * New content type heading string.
     *
     * @return string
     */
    protected function get_new_heading(): string {
        return get_string('newcontenttype', 'mod_cms');
    }

    /**
     * Edit content type heading string.
     *
     * @return string
     */
    protected function get_edit_heading(): string {
        return get_string('editcontenttype', 'mod_cms');
    }

    /**
     * Return the base URL.
     *
     * @return string
     */
    public static function get_base_url(): string {
        return '/mod/cms/managetypes.php';
    }

    /**
     * Execute edit action.
     *
     * @param string $action Could be edit or create.
     * @param int|null $id   If no ID is provided, that means we are creating a new one
     */
    protected function edit(string $action, ?int $id = null): void {
        global $PAGE;

        $PAGE->set_url(new \moodle_url(self::get_base_url(), ['action' => $action, 'id' => $id]));
        $instance = null;

        if (!empty($id)) {
            $instance = $this->get_instance($id);
        }

        $form = $this->get_form($instance);

        if ($form->is_cancelled()) {
            redirect(new \moodle_url(self::get_base_url()));
        } else if ($data = $form->get_data()) {
            $stayonpage = isset($data->saveanddisplay);
            unset($data->saveandreturn);
            unset($data->saveanddisplay);
            try {
                // Create new.
                if (empty($data->id)) {
                    $instance = $this->create($data);
                } else { // Update existing.
                    $this->update($id, $data);
                }
                \core\notification::success(get_string('changessaved'));
            } catch (\Throwable $e) {
                \core\notification::error($e->getMessage());
            }
            $redirecturl = new \moodle_url(self::get_base_url());
            if ($stayonpage) {
                $redirecturl->param('action', 'edit');
                $redirecturl->param('id', $instance->get('id'));
            }
            redirect($redirecturl);
        } else {
            if (empty($instance)) {
                $this->header($this->get_new_heading());
            } else {
                $this->header($this->get_edit_heading());
            }
        }

        $form->display();
        $this->footer();
    }

    /**
     * Export action. Will either crate a file for download for display in the browser.
     *
     * @param int $id CMS type ID
     * @param string $format Either 'yaml', 'txt' or 'preview'.
     */
    protected function export(int $id, string $format = 'yaml') {
        $instance = $this->get_instance($id);
        $instance->export($format);
    }

    /**
     * Import action.
     */
    protected function import() {
        global $PAGE;

        $PAGE->set_url(new \moodle_url(self::get_base_url(), ['action' => 'import']));

        $form = new cms_types_import_form($PAGE->url->out(false));
        if ($form->is_cancelled()) {
            redirect(new \moodle_url(self::get_base_url()));
        } else if ($data = $form->get_data()) {
            $filecontent = $form->get_file_content('importfile');
            $contenttype = $this->get_instance(0);
            $contenttype->import($filecontent);
            redirect(new \moodle_url(self::get_base_url()));
        } else {
            $this->header(get_string('import_cms_type', 'mod_cms'));
        }

        $form->display();
        $this->footer();
    }

    /**
     * Create a new CMS type.
     *
     * @param \stdClass $data Form compatible data
     * @return cms_types
     */
    public function create(\stdClass $data) {
        $instance = $this->get_instance();
        $cleandata = cms_types::clean_record($data);
        $instance->from_record($cleandata);
        $instance->create();

        // Do post create actions for data sources.
        foreach (dsbase::get_datasources($instance) as $ds) {
            $ds->config_on_update($data);
        }
        return $instance;
    }

    /**
     * Update a CMS type.
     *
     * @param int $id
     * @param \stdClass $data Form compatible data
     */
    public function update(int $id, \stdClass $data) {
        $instance = $this->get_instance($id);
        $cleandata = cms_types::clean_record($data);
        $instance->from_record($cleandata);
        $instance->update();
        lib::reset_cms_names($id);

        // Do post update actions for data sources.
        foreach (dsbase::get_datasources($instance) as $ds) {
            $ds->config_on_update($data);
        }
    }

    /**
     * Gets the config data for the CMS type.
     *
     * @param int $id
     * @return \stdClass Form compatible data
     */
    public function get_config(int $id): \stdClass {
        $instance = $this->get_instance($id);
        $data = $instance->to_record();
        foreach (dsbase::get_datasources($instance) as $ds) {
            $ds->config_form_default_data($data);
        }
        return $data;
    }

    /**
     * Deletes a cms_type.
     *
     * @param int $id
     *
     * @return bool
     */
    public function delete(int $id): bool {
        $instance = $this->get_instance($id);

        if (!$instance->can_delete()) {
            return false;
        }

        foreach (dsbase::get_datasources($instance, false) as $ds) {
            $ds->config_on_delete();
        }
        $instance->delete();
        return true;
    }

    /**
     * Set the visibility of the CMS type.
     *
     * @param int $id
     * @param bool $show
     * @throws \coding_exception
     */
    public function set_visibility(int $id, bool $show = true) {
        $instance = $this->get_instance($id);
        $instance->set('isvisible', $show);
        $instance->save();
    }

    /**
     * Execute view action.
     *
     * @return void
     */
    protected function view(): void {
        global $PAGE;

        $PAGE->set_url(self::get_base_url());
        $this->header($this->get_view_heading());
        $this->print_add_button();
        $this->print_import_button();
        $this->display_all_records();

        $PAGE->requires->js_call_amd('mod_cms/managecontenttypes', 'setup');
        $this->footer();
    }

    /**
     * Print out add button.
     */
    protected function print_add_button(): void {
        echo $this->output->single_button(
            new \moodle_url(self::get_base_url(), ['action' => 'add']),
            $this->get_create_button_text()
        );
    }

    /**
     * Print out add button.
     */
    protected function print_import_button(): void {
        echo $this->output->single_button(
            new \moodle_url(self::get_base_url(), ['action' => 'import']),
            get_string('import_cms_type', 'mod_cms')
        );
    }

    /**
     * Print out page header.
     *
     * @param string $title
     *
     * @return void
     */
    protected function header(string $title): void {
        echo $this->output->header();
        echo $this->output->heading($title);
    }

    /**
     * Print out the page footer.
     *
     * @return void
     */
    protected function footer(): void {
        echo $this->output->footer();
    }
}
