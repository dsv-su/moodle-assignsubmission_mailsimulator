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

$PAGE->set_url('/mod/assign/submission/mailsimulator/attachment_import.php', array('id' => $id));
$PAGE->set_title('Attachments Import');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_course($course);

require_capability('moodle/course:changefullname', $context);

$fileoptions = array(
    'subdirs' => 0,
    'maxbytes' => 9999999999,
    'maxfiles' => 9999999999,
    'accepted_types' => '*'
    );
$customdata = new stdClass();
$customdata->fileoptions = $fileoptions;
$customdata->id = $id;

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/attachment_form.php');
$form = new attachment_form(null, $customdata);

$draftitemid = file_get_submitted_draft_itemid('attachment');
file_prepare_draft_area($draftitemid, $context->id, 'assignsubmission_mailsimulator', 'attachment',
    1, $fileoptions);

if ($form->is_cancelled()) {
    // cancelled
} else if ($fromform = $form->get_data()) {
    // In this case you process validated data. $mform->get_data() returns data posted in form.
    if ($form->is_validated()) {
    
        if (isset($fromform->attachment)) {
            $info = file_get_draft_area_info($fromform->attachment);
            $present = ($info['filecount']>0) ? '1' : '0';
            file_save_draft_area_files($fromform->attachment, $context->id, 'assignsubmission_mailsimulator', 'attachment',
                1, $fileoptions);
            //$DB->set_field('assignsubmission_mail_mail', 'attachment', $present, array('id'=>$currentmailid));
        } else {
            //$DB->set_field('assignsubmission_mail_mail', 'attachment', $existingattachment, array('id'=>$currentmailid));
        }
        $status = $info['filecount'] . ' files have been uploaded.';
        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id, $status, 5);
    }
} else {
    // Display form.
    echo $OUTPUT->header();
    $form->display();
}

echo $OUTPUT->footer();
