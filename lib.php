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
 * Library of functions for the Course SMS plugin.
 *
 * @package   local_coursessms
 * @copyright 2025 Kewayne Davidson <admin.kewayne.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the course navigation with a link to the Course SMS page.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_coursessms_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (has_capability('local/coursessms:sendsms', $context) || has_capability('local/coursessms:viewlog', $context)) {
        $url = new moodle_url('/local/coursessms/index.php', ['id' => $course->id]);

        $navigation->add(
            get_string('coursessms_nav', 'local_coursessms'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'coursessms',
            new pix_icon('i/sms', get_string('coursessms_nav', 'local_coursessms'))
        );
    }
}
