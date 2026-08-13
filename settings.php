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
 * Admin settings for local_coursessms.
 *
 * @package   local_coursessms
 * @copyright 2025 Kewayne Davidson <admin.kewayne.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new admin_settingpage('local_coursessms', get_string('pluginname', 'local_coursessms'));

    // Gateway Selection Setting.
    $options = [0 => get_string('system_default_gateway', 'local_coursessms')];
    try {
        $smsmanager = \core\di::get(\core_sms\manager::class);
        $gateways = $smsmanager->get_enabled_gateway_instances();
        $stringmanager = get_string_manager();

        foreach ($gateways as $gw) {
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

            $options[$gw->id] = $label;
        }
    } catch (\Throwable $e) {
        // Fallback if SMS manager is not ready.
    }

    $settings->add(new admin_setting_configselect(
        'local_coursessms/default_gateway',
        get_string('setting_default_gateway', 'local_coursessms'),
        get_string('setting_default_gateway_desc', 'local_coursessms'),
        0,
        $options
    ));

    // Allow user to select gateway setting.
    $settings->add(new admin_setting_configcheckbox(
        'local_coursessms/allow_select_gateway',
        get_string('setting_allow_select_gateway', 'local_coursessms'),
        get_string('setting_allow_select_gateway_desc', 'local_coursessms'),
        1
    ));

    $ADMIN->add('localplugins', $settings);
}
