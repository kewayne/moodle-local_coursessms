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
 * Moodle form for sending an SMS.
 *
 * @package   local_coursessms
 * @copyright 2025 Kewayne Davidson <admin.kewayne.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_coursessms\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Provider for the Course SMS plugin.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course} co ON co.id = c.instanceid
            INNER JOIN {local_coursessms_log} l ON l.courseid = co.id
                 WHERE l.senderid = :userid
                   AND c.contextlevel = :contextlevel";
        $params = [
            'userid' => $userid,
            'contextlevel' => CONTEXT_COURSE,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $courseid = $context->instanceid;
            $logs = $DB->get_records('local_coursessms_log', ['senderid' => $userid, 'courseid' => $courseid]);
            foreach ($logs as $log) {
                \core_privacy\local\request\writer::with_context($context)->export_data([], (object) [
                    'messagecontent' => $log->messagecontent,
                    'targettype' => $log->targettype,
                    'targetid' => $log->targetid,
                    'success_userids' => $log->success_userids,
                    'failed_userids' => $log->failed_userids,
                    'timecreated' => $log->timecreated,
                ]);
            }
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param \context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $DB->delete_records('local_coursessms_log', ['courseid' => $context->instanceid]);
    }

    /**
     * Delete all personal data for a single user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('local_coursessms_log', ['senderid' => $userid, 'courseid' => $context->instanceid]);
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $sql = "SELECT l.senderid
                  FROM {local_coursessms_log} l
                 WHERE l.courseid = :courseid";
        $params = ['courseid' => $context->instanceid];
        $userlist->add_from_sql('senderid', $sql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $context->instanceid], $userinparams);
        $sql = "courseid = :courseid AND senderid {$userinsql}";
        $DB->delete_records_select('local_coursessms_log', $sql, $params);
    }

    /**
     * Get the description of the data that will be exported.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_coursessms_log',
            [
                'senderid' => 'privacy:metadata:local_coursessms_log:senderid',
                'messagecontent' => 'privacy:metadata:local_coursessms_log:messagecontent',
                'targettype' => 'privacy:metadata:local_coursessms_log:targettype',
                'targetid' => 'privacy:metadata:local_coursessms_log:targetid',
                'success_userids' => 'privacy:metadata:local_coursessms_log:success_userids',
                'failed_userids' => 'privacy:metadata:local_coursessms_log:failed_userids',
                'timecreated' => 'privacy:metadata:local_coursessms_log:timecreated',
            ],
            'privacy:metadata:local_coursessms_log'
        );
        return $collection;
    }
}
