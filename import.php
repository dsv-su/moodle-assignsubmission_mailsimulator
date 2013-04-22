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
 * Data import script for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');

global $CFG, $DB, $COURSE, $PAGE;

$id             = required_param('id', PARAM_INT);
$assignmentid   = required_param('aid', PARAM_INT);
$cm             = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context        = context_module::instance($cm->id);

require_login($course);

$PAGE->set_url('/mod/assign/submission/mailsimulator/import.php', array('id' => $id));
$PAGE->set_title('Import');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);

require_capability('mod/assign:view', $context);

$mailbox = new mailbox($context, $cm, $course);

echo $OUTPUT->header();

// Update contacts table and save old contactids.
$sql = 'SELECT DISTINCT userid FROM {assignment_mailsimulation_mail} WHERE assignment = '. $assignmentid .' AND userid<>0';
$assignuserids = $DB->get_records_sql($sql);
$contacts = $DB->get_records('assignment_mailsimulation_contact', array('assignment' => $assignmentid));
$contactids = array();
foreach ($contacts as $contact) {
    $contact->assignment = $cm->instance;
    $contactidnew = $DB->insert_record('assignsubmission_mail_cntct', $contact);
    $contactids[$contact->id] = $contactidnew;
}
unset($contacts);
ob_start();
echo $OUTPUT->notification('contacts complete');
ob_flush();flush();

// Update user table and save old userids.
$select = 'id IN (SELECT DISTINCT userid FROM {assignment_mailsimulation_mail} WHERE assignment = '. $assignmentid .' AND userid<>0)';
$users = $DB->get_records_select('user_old', $select);
$userids = array();
foreach ($users as $user) {
    unset($user->descriptionformat);
    unset($user->ajax);
    unset($user->screenreader);
    $useridold = $user->id;

    if ($DB->record_exists('user', array('username' => $user->username))) {
        $useridnew = $DB->get_field('user', 'id', array('username' => $user->username));
        $user->id = $useridnew;
        $DB->update_record('user', $user);
    } else {
        $user->timecreated = time();
        $user->suspended = 0;
        $useridnew = $DB->insert_record('user', $user);
    }

    // We need to add a submission for each user.
    $mailbox->update_user_submission($useridnew);

    $userids[$useridold] = $useridnew;
}
unset($users);
ob_start();
echo $OUTPUT->notification('users complete');
ob_flush();flush();


// Update mail table and save old ids.
$mails = $DB->get_records('assignment_mailsimulation_mail', array('assignment' => $assignmentid));
$ids = array();
foreach ($mails as $mail) {
    if (isset($userids[$mail->userid]) || $mail->userid == 0) {
        // We need to simulate the same structure as it would be a teacher's mail.
        $objmessage  = array();
        $objmessage['text'] = $mail->message;
        $mail->message = serialize($objmessage);

        if (isset($mail->userid) && ($mail->userid<>0)) {
            $mail->userid = $userids[$mail->userid];
        } else {
            $mail->userid = 0;
        }

        if (isset($mail->parent) && ($mail->parent<>0)) {
            $mail->parent = $ids[$mail->parent];
        } else {
            $mail->parent = 0;
        }

        $mail->assignment = $cm->instance;
        if (isset($mail->sender) && ($mail->sender<>0)) {
            $mail->sender = $contactids[$mail->sender];
        } else {
            $mail->sender = 0;
        }

        $idnew = $DB->insert_record('assignsubmission_mail_mail', $mail);
        $ids[$mail->id] = $idnew;
    }
}
unset($mails);
ob_start();
echo $OUTPUT->notification('mails complete');
ob_flush();flush();


// Update templates and save old ids.
$where = 'randgroup > 0 AND weight > 0';
$templates = $DB->get_records_select('assignment_mailsimulation_parent_mail', $where);
$templateids = array();
foreach ($templates as $template) {
    if (isset($ids[$template->mailid])) {
        $template->mailid = $ids[$template->mailid];
        $templateidnew = $DB->insert_record('assignsubmission_mail_tmplt', $template);
        $templateids[$template->id] = $templateidnew;
    }
}
unset($templates);
ob_start();
echo $OUTPUT->notification('templates complete');
ob_flush();flush();


// Update signed mails and save old ids.
$where = 'status > 0';
$signedmails = $DB->get_records_select('assignment_mailsimulation_signed_out_mail', $where);
$signedmailids = array();
foreach ($signedmails as $signedmail) {
    if (isset($userids[$signedmail->userid]) && isset($ids[$signedmail->mailid])) {
        $signedmail->mailid = $ids[$signedmail->mailid];
        $signedmail->userid = $userids[$signedmail->userid];
        $signedmail->feedback = $signedmail->comment;
        unset($signedmail->comment);
        $signedmailidnew = $DB->insert_record('assignsubmission_mail_sgndml', $signedmail);
        $signedmailids[$signedmail->id] = $signedmailidnew;
    }
}
unset($signedmails);
ob_start();
echo $OUTPUT->notification('signedmails complete');
ob_flush();flush();


// Update tos and save old ids.
$tos = $DB->get_records('assignment_mailsimulation_to');
$toids = array();
foreach ($tos as $to) {
    if (isset($ids[$to->mailid])) {
        $to->mailid = $ids[$to->mailid];
        if ($to->contactid <> 9999999 && $to->contactid<>0) {
            $to->contactid = $contactids[$to->contactid];
        }
        $toidnew = $DB->insert_record('assignsubmission_mail_to', $to);
        $toids[$to->mailid] = $toidnew;
    }
}
unset($tos);
ob_start();
echo $OUTPUT->notification('tos complete');
ob_flush();flush();


echo $OUTPUT->footer();
