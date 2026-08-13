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
 * Upgrade logic for the Course SMS plugin.
 *
 * @package   local_coursessms
 * @copyright 2025 Kewayne Davidson <admin.kewayne.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs the upgrade for this plugin.
 *
 * @param int $oldversion The currently installed version of the plugin.
 * @return bool
 */
function xmldb_local_coursessms_upgrade($oldversion) {
    global $DB;

    // Example upgrade step: mark new version as installed.
    if ($oldversion < 2025071100) {
        // No schema change, just savepoint.
        upgrade_plugin_savepoint(true, 2025071100, 'local', 'coursessms');
    }

    return true;
}
