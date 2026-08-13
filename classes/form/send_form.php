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

namespace local_coursessms\form;

defined('MOODLE_INTERNAL') || die();

use moodleform;
use html_writer;

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/accesslib.php');

/**
 * Form for sending SMS messages in a course.
 *
 * @package   local_coursessms
 * @copyright 2025 Kewayne Davidson <admin@kewayne.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_form extends moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        global $DB, $USER;
        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'];
        $context = \context_course::instance($courseid);

        // Gateway Selection (if enabled in admin settings).
        $allowSelectGateway = (bool)get_config('local_coursessms', 'allow_select_gateway');
        $defaultGateway = (int)get_config('local_coursessms', 'default_gateway');

        if ($allowSelectGateway) {
            $mform->addElement('header', 'gateway_header', get_string('gateway_heading', 'local_coursessms'));

            $gatewayOptions = [0 => get_string('system_default_gateway', 'local_coursessms')];
            try {
                $smsmanager = \core\di::get(\core_sms\manager::class);
                $enabledGateways = $smsmanager->get_enabled_gateway_instances();
                $stringmanager = get_string_manager();

                foreach ($enabledGateways as $gw) {
                    $class = get_class($gw);
                    $parts = explode('\\', $class);
                    $component = $parts[0] ?? '';

                    $pluginname = '';
                    if (!empty($component) && $stringmanager->string_exists('pluginname', $component)) {
                        $pluginname = get_string('pluginname', $component);
                    }

                    $label = $gw->name;
                    if (!empty($pluginname) && strcasecmp($gw->name, $pluginname) !== 0) {
                        $label .= ' (' . $pluginname . ')';
                    }

                    $gatewayOptions[$gw->id] = $label;
                }
            } catch (\Throwable $e) {
                // Keep default if error.
            }

            $mform->addElement('select', 'gatewayid', get_string('select_gateway', 'local_coursessms'), $gatewayOptions);
            $mform->setDefault('gatewayid', $defaultGateway);
            $mform->addHelpButton('gatewayid', 'select_gateway', 'local_coursessms');
        } else {
            $mform->addElement('hidden', 'gatewayid', $defaultGateway);
            $mform->setType('gatewayid', PARAM_INT);
        }

        // Recipient Selection.
        $mform->addElement('header', 'target_header', get_string('target_heading', 'local_coursessms'));

        $targetoptions = [
            'all'   => get_string('target_all', 'local_coursessms'),
            'role'  => get_string('target_role', 'local_coursessms'),
            'group' => get_string('target_group', 'local_coursessms'),
        ];
        $mform->addElement('select', 'targettype', get_string('target_type', 'local_coursessms'), $targetoptions);
        $mform->addHelpButton('targettype', 'target_heading', 'local_coursessms');

        // Role selector.
        $roleoptions = \get_assignable_roles($context);
        $mform->addElement('select', 'roleid', get_string('select_role', 'local_coursessms'), $roleoptions);
        $mform->hideIf('roleid', 'targettype', 'neq', 'role');
        $mform->disabledIf('roleid', 'targettype', 'neq', 'role');

        // Group selector.
        $groups = \groups_get_all_groups($courseid);
        $groupoptions = [];
        if (!empty($groups)) {
            foreach ($groups as $group) {
                $groupoptions[$group->id] = $group->name;
            }
        }
        $mform->addElement('select', 'groupid', get_string('select_group', 'local_coursessms'), $groupoptions);
        $mform->hideIf('groupid', 'targettype', 'neq', 'group');
        $mform->disabledIf('groupid', 'targettype', 'neq', 'group');

        // Message Content.
        $mform->addElement('header', 'message_header', get_string('message_heading', 'local_coursessms'));

        // Prepare default message with sender signature and course shortname.
        $courseshortname = $DB->get_field('course', 'shortname', ['id' => $courseid]);
        $sendername = fullname($USER);
        $defaultmessage = "\n--\n{$sendername}\n{$courseshortname}";

        // Placeholder info text for the textarea.
        $placeholderinfo = get_string('placeholder_info', 'local_coursessms');

        $mform->addElement('textarea', 'messagecontent', get_string('message_content', 'local_coursessms'),
            ['wrap' => 'virtual', 'rows' => 7, 'cols' => 50, 'placeholder' => $placeholderinfo]
        );

        $mform->setDefault('messagecontent', $defaultmessage);
        $mform->setType('messagecontent', PARAM_TEXT);
        $mform->addRule('messagecontent', null, 'required', null, 'client');
        $mform->addHelpButton('messagecontent', 'message_content', 'local_coursessms');

        // Help text below textarea informing about placeholders.
        $mform->addElement('static', 'placeholder_help', '',
            html_writer::tag('div', $placeholderinfo, [
                'class' => 'placeholder-help',
                'style' => 'font-style: italic; font-size: 0.9em; margin-top: 0.5em;',
            ])
        );

        // Hidden field for course ID.
        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('send_button', 'local_coursessms'));
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['targettype'] === 'role' && empty($data['roleid'])) {
            $errors['roleid'] = get_string('error_no_target', 'local_coursessms');
        }
        if ($data['targettype'] === 'group' && empty($data['groupid'])) {
            $errors['groupid'] = get_string('error_no_target', 'local_coursessms');
        }

        return $errors;
    }
}
