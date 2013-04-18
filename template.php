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
 * Template editing view for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG, $DB, $PAGE, $COURSE;

$id      = optional_param('id', 0, PARAM_INT);
$mid     = optional_param('mid', 0, PARAM_INT);
$gid     = optional_param('gid', 0, PARAM_INT);
$cm      = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course);

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
$mailboxinstance = new mailbox($context, $cm, $course);

$PAGE->set_url('/mod/assign/submission/mailsimulator/template.php', array('id' => $id));
$PAGE->set_title('Edit template');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);

if ($mid) {
    // Check if mail exists. A template must have a mail to refer to.
    if (!$mailobj = $DB->get_record('assignsubmission_mail_mail', array('id' => $mid))) {
        echo $OUTPUT->error_text("Mail ID is incorrect");
    }

    $mail = $mailboxinstance->get_nested_reply_object($mailobj);
    if (!$mail) {
        $mail = $mailobj;
        $mail->message = unserialize($mail->message)["text"];
    }

    $mailstr = html_writer::tag('div', format_text(($mail->message), FORMAT_MOODLE) , array('style' => 'background-color:#ffffff;
        margin:auto; margin-bottom: 20px; padding:5px; border:1px; border-style:solid; border-color:#999999; width:80%;'));

    $customdata = $mailboxinstance->prepare_parent($mid, $gid);
} else {
    $customdata = $mailboxinstance->prepare_parent();
}

if ($existingparent = $DB->get_record("assignsubmission_mail_tmplt", array("mailid" => $mid))) {
    $existingparent->maxweight = $customdata->maxweight;
    $existingparent->randgroup = $customdata->randgroup;
    $customdata = $existingparent;
}

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/template_form.php');
$tform = new template_form(null, $customdata);

if ($tform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id,
        get_string('returnmailbox', 'assignsubmission_mailsimulator') , 1);
} else if ($fromform=$tform->get_data()) {
    // In this case you process validated data. $mform->get_data() returns data posted in form.
    if ($tform->is_validated()) {
        $templateexist = $DB->record_exists('assignsubmission_mail_tmplt', array('id' => $fromform->templateid));
        if (isset($fromform->templateid) && $templateexist) {
            $fromform->id = $fromform->templateid;
            $DB->update_record('assignsubmission_mail_tmplt', $fromform);
            $tstatus = get_string('correctiontemplateupdated', 'assignsubmission_mailsimulator', $fromform->id);
        } else {
            $tid = $DB->insert_record('assignsubmission_mail_tmplt', $fromform);
            $tstatus = get_string('correctiontemplateadded', 'assignsubmission_mailsimulator', $tid);
        }
        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id, $tstatus, 1);
    }
} else {
    // Display template form.
    echo $OUTPUT->header();
    if (isset($mailstr)) {
        echo $mailstr;
    }
    $tform->display();
}

echo $OUTPUT->footer();
