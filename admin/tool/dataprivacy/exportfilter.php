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
 * Print the export filter form to privacy officer.
 *
 * @copyright 2020 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package tool_dataprivacy
 */

use core\notification;

require_once('../../../config.php');
require_once('lib.php');
require_once('exportfilter_form.php');

$requestid = required_param('requestid', PARAM_INT);

$url = new moodle_url('/admin/tool/dataprivacy/exportfilter.php', ['requestid' => $requestid]);

$PAGE->set_url($url);

require_login();
if (isguestuser()) {
    print_error('noguest');
}

$returnurl = new moodle_url($CFG->wwwroot . '/admin/tool/dataprivacy/datarequests.php');
$context = context_system::instance();

if (!get_config('tool_dataprivacy', 'allowfiltering')) {
    print_error('nopermissiontoviewpage', 'error', $returnurl);
}

// Make sure the user has the proper capability.
require_capability('tool/dataprivacy:managedatarequests', $context);
$PAGE->set_context($context);

if (!$DB->record_exists('tool_dataprivacy_request', ['id' => $requestid])) {
    print_error('invalidid');
}

$mform = new tool_dataprivacy_export_filter_form(null, ['requestid' => $requestid]);

// Data request cancelled.
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

// Data request submitted.
if ($data = $mform->get_data()) {
    // Ensure the request exists.
    $requestexists = \tool_dataprivacy\data_request::record_exists($requestid);

    $result = false;
    if ($requestexists) {
        $coursecontextids = [];
        if (!empty($data->coursecontextids)) {
            $coursecontextids = $data->coursecontextids;
        }
        $result = \tool_dataprivacy\api::approve_data_request($requestid, $coursecontextids);

        // Add notification in the session to be shown when the page is reloaded on the JS side.
        notification::success(get_string('requestapproved', 'tool_dataprivacy'));
    } else {
        $warnings[] = [
            'item' => $requestid,
            'warningcode' => 'errorrequestnotfound',
            'message' => get_string('errorrequestnotfound', 'tool_dataprivacy')
        ];
    }

    redirect($returnurl);
}

$title = get_string('filterexportdata', 'tool_dataprivacy');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($title);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

echo $OUTPUT->box_start('filterexportform');
$mform->display();
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
