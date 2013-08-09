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
 * Attachment import script for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../../config.php');

global $CFG, $DB, $COURSE, $PAGE;

$id             = required_param('id', PARAM_INT); // Course ID
$course         = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context        = context_course::instance($course->id);

require_login($course);

$PAGE->set_url('/mod/assign/submission/mailsimulator/attachment_assign.php', array('id' => $id));
$PAGE->set_title('MailSimulator Attachment Reassigning');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_course($course);

require_capability('moodle/course:changefullname', $context);

echo $OUTPUT->header();

$filestoedit = $DB->get_records('files', array('component' => 'assignsubmission_mailsimulator', 'filearea' => 'attachment', 'itemid' => '1'));

foreach ($filestoedit as $file) {
    $arr = explode('_', $file->filename, 2); // Break the filename into 2 parts separated by '_' character
    $mailid = $arr[0];
    if (is_numeric($mailid)) {

// WE HAVE TO ADDRESS DUPLICATED MAILS PROBLEMS!!!11рас рас

        // Get old mail
        $oldmail = $DB->get_record('old_assignment_mailsimulation_mail', array('id' => $mailid));
        
        if ($oldmail) {
            // Get new mailid
            $newmail = $DB->get_record('assignsubmission_mail_mail', array('timesent' => $oldmail->timesent));
            $olduser = $DB->get_record('old_user', array('id' => $oldmail->userid));
            $newuser = $DB->get_record('user', array('id' => $newmail->userid));

            if (($olduser->username)<>($newuser->username)) {
                print_error('Collision between mails/usernames: ' . ' olduser: ' . $olduser->id . ' newuser ' . $newuser->id .
                    ' oldmail ' . $oldmail->id . ' newmail ' . $newmail->id);
            }

            if ($newmail->attachment<1) {
                print_error('Attepmting to assign attachment to mail '.$newmail->id.' that does not have it');
            }

            $file->itemid = $newmail->id;
            $cm = get_coursemodule_from_instance('assign', $newmail->assignment, 0, false, MUST_EXIST);
            $context = context_module::instance($cm->id);
            $file->contextid = $context->id;
            $file->filename = $arr[1];
            $file->author = fullname($newuser);
            $fullpath = "/{$context->id}/assignsubmission_mailsimulator/attachment/{$file->itemid}/{$file->filename}";
            $file->pathnamehash = sha1($fullpath);
            $DB->update_record('files', $file);
            ob_start();
            echo $OUTPUT->notification('File '.$file->filename.' is attached to mail #'.$newmail->id.' sent by user #'.$newuser->id);
            ob_flush();flush();
        }
    }
}

echo $OUTPUT->notification('<br>Files reassigning complete');

echo $OUTPUT->footer();
