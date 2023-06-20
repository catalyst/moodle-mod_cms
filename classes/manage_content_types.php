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
 * Class for managing the custom content types.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_cms;

use stdClass;
use moodle_url;
use context_system;
use core\notification;
use mod_cms\form\cms_types_form;
use mod_cms\local\model\cms_types;
use mod_cms\local\table\content_types_list;

/**
 * Class for managing the custom content types.
 *
 * @package     mod_cms
 * @author      Marcus Boon<marcus@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_content_types {

    /**
     * @var Locally cached $OUTPUT object
     */
    protected $output;

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
            case 'delete':
                $this->delete(required_param('id', PARAM_INT));
                break;
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
        global $PAGE, $FULLME;

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
     * @param stdClass $data
     *
     * @return cms_types
     */
    protected function get_instance($id = 0, ?stdClass $data = null): cms_types {
        return new cms_types($id, $data);
    }

    /**
     * Print out all records in the table.
     *
     * @return void
     */
    protected function display_all_records(): void {
        $records = cms_types::get_records([], 'id');

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
     * @param cms_types $type
     *
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
     * @param int    $id     If no ID is provided, that means we are creating a new one
     *
     * @return void
     */
    protected function edit(string $action, ?int $id = null): void {
        global $PAGE;

        $PAGE->set_url(new moodle_url(self::get_base_url(), ['action' => $action, 'id' => $id]));
        $instance = null;

        if (!empty($id)) {
            $instance = $this->get_instance($id);
        }

        $form = $this->get_form($instance);

        if ($form->is_cancelled()) {
            redirect(new moodle_url(self::get_base_url()));
        } else if ($data = $form->get_data()) {
            $stayonpage = isset($data->saveanddisplay);
            unset($data->saveandreturn);
            unset($data->saveanddisplay);
            try {
                // Create new.
                if (empty($data->id)) {
                    $contenttype = $this->get_instance(0, $data);
                    $instance = $contenttype->create();
                } else { // Update existing.
                    $instance->from_record($data);
                    $instance->update();
                }
                file_save_draft_area_files(
                    $data->images,
                    context_system::instance()->id,
                    'mod_cms',
                    'cms_type_images',
                    $instance->get('id')
                );
                notification::success(get_string('changessaved'));
            } catch (\Exception $e) {
                notification::error($e->getMessage());
            }
            $redirecturl = new moodle_url(self::get_base_url());
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
     * Execute delete action.
     *
     * @param int $id
     *
     * @return void
     */
    protected function delete(int $id): void {
        require_sesskey();
        $instance = $this->get_instance($id);

        if ($instance->can_delete()) {
            $instance->delete();
            notification::success(get_string('deleted'));
        } else {
            notification::warning(get_string('cantdelete', 'mod_cms'));
        }
        redirect(new moodle_url(self::get_base_url()));
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
        $this->display_all_records();

        $PAGE->requires->js_call_amd('mod_cms/managecontenttypes', 'setup');
        $this->footer();
    }

    /**
     * Print out add button.
     */
    protected function print_add_button(): void {
        echo $this->output->single_button(
            new moodle_url(self::get_base_url(), ['action' => 'add']),
            $this->get_create_button_text()
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
