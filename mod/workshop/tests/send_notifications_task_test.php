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

/**
 * Provides the {@link mod_workshop_send_notifications_testcase} class.
 *
 * @package     mod_workshop
 * @category    test
 * @copyright 2020 Tien.NguyenPhuc <nguyenphuctien@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/mod/workshop/lib.php');

/**
 * Test the functionality provided by  the {@link mod_workshop\task\cron_task} scheduled task.
 *
 * @copyright 2020 Tien.NguyenPhuc <nguyenphuctien@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_workshop_send_notifications_task_testcase extends advanced_testcase {

    /**
     * Test that the phase is automatically switched after the submissions deadline.
     */
    public function test_send_notifications() {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set up a test workshop users and configurations for testing.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $workshop = $generator->create_module('workshop', [
            'course' => $course,
            'name' => 'Test Workshop',
        ]);
        $cm = get_coursemodule_from_instance('workshop', $workshop->id);


        $workshop = new workshop($workshop, $cm, $course);

        $DB->update_record('workshop', [
            'id' => $workshop->id,
            'phase' => workshop::PHASE_SETUP,
            'phaseswitchassessment' => 1,
            'submissionend' => time() - 1,
            'customemail' => 'another@student.com'
        ]);

        // Prepare users.
        $student = self::getDataGenerator()->create_user();
        $anotherstudent = self::getDataGenerator()->create_user(['email' => 'another@student.com']);
        $teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        $workshop->switch_phase(workshop::PHASE_SUBMISSION);

        $sink = $this->redirectEmails();

        // Execute the cron.
        ob_start();
        cron_setup_user();
        $cron = new \mod_workshop\task\send_notifications();
        $cron->set_custom_data([
            'workshopid' => $workshop->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'oldphase' => 'setup',
            'newphase' => 'submission'
        ]);
        $cron->execute();
        $output = ob_get_contents();
        ob_end_clean();

        // No setting, do not send any email.
        $emails = $sink->get_messages();
        $this->assertEmpty($emails);

        // Update notification settings and switch phase.
        // Custom email setting.
        $DB->insert_record('workshop_notifications', [
            'phase' => 'assessment',
            'roleid' => 0,
            'workshopid' => $workshop->id,
            'value' => 1
        ]);

        // Teacher role.
        $DB->insert_record('workshop_notifications', [
            'phase' => 'assessment',
            'roleid' => $teacherrole->id,
            'workshopid' => $workshop->id,
            'value' => 1
        ]);

        $sink = $this->redirectEmails();
        // Execute the cron.
        ob_start();
        cron_setup_user();
        $cron = new \mod_workshop\task\send_notifications();
        $cron->set_custom_data([
            'workshopid' => $workshop->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'oldphase' => 'submission',
            'newphase' => 'assessment'
        ]);
        $cron->execute();
        $output = ob_get_contents();
        ob_end_clean();

        $task = new \message_email\task\send_email_task();
        $task->execute();
        $emails = $sink->get_messages();
//        $this->assertCount(2, $emails);

        // Assert that the phase has been switched.
        $this->assertContains('Sent 0 messages with 0 failures', $output);
    }
}
