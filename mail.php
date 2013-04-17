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
 * Mail editing view for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->libdir.'/filelib.php');

global $CFG, $DB, $PAGE, $COURSE, $USER;

$id      = required_param('id', PARAM_INT);
$tid     = optional_param('tid', 0, PARAM_INT);       // Template ID
$gid     = optional_param('gid', 0, PARAM_INT);       // Group ID.
$re      = optional_param('re', 0, PARAM_INT);        // Reply 1=one, 2=all, 3=forward.
$mid     = optional_param('mid', 0, PARAM_INT);
$cm      = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course);

$PAGE->set_url('/mod/assign/submission/mailsimulator/mail.php', array('id' => $id));
$PAGE->set_title('Edit mail');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
$mailboxinstance = new mailbox($context, $cm, $course);

$teacher = has_capability('mod/assign:grade', $context);

if ($mid) {
    if (!$teacher) {
        error('Unauthorized access');
    }

    $customdata = $DB->get_record('assignsubmission_mail_mail', array('id' => $mid));
    if (!$customdata) {
        error("Mail ID was incorrect", $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id='
            . $cm->id);
    }
    if (!$customdata->userid == 0) { // Only edit teacher mail.
        error("You are not allowed to edit this mail.", $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id='
            . $cm->id);
    }

    // Fill the form with mail's data, if we edit a mail.
    $teacherid = $mailboxinstance->get_config('teacherid');
    $contacts = $DB->get_records('assignsubmission_mail_cntct', array('assignment' => $cm->instance));
    $senttoobjarr = $DB->get_records('assignsubmission_mail_to', array('mailid' => $mid), 'contactid');
    $sendtoarr = array();

    foreach ($senttoobjarr as $key => $value) {
        $sendtoarr[$value->contactid] = $value->contactid;
    }

    $teacherobj = $DB->get_record('user', array('id' => $teacherid), 'firstname, lastname, email');

    if ($contacts) {
        foreach ($contacts as $key => $con) {
            $contacts[$key] = $con->firstname . ' ' . $con->lastname . ' &lt;' . $con->email . '&gt;';
        }
    }

    $contacts[0] = $teacherobj->firstname . ' ' . $teacherobj->lastname . ' &lt;' . $teacherobj->email . '&gt;';
    $contacts[TO_STUDENT_ID] = get_string('mailtostudent', 'assignsubmission_mailsimulator');
    asort($contacts);

    $customdata->message = unserialize($customdata->message);
    $customdata->to = $contacts;
    $customdata->sentto = $sendtoarr;
    $customdata->mailid = $customdata->id;
    $customdata->teacher = true;

    $top = $mailboxinstance->get_top_parent_id($mid);
    $inactive = !$mailboxinstance->get_signed_out_status($top);
    $customdata->inactive = $inactive;

    unset($customdata->id);
} else {
    $customdata = $mailboxinstance->prepare_mail($tid);
}

$titlestr = get_string('newmail', 'assignsubmission_mailsimulator');
$mailstr = '';

if ($tid) { // If a student replies to or forward a mail.

    if (!$mailobj = $DB->get_record('assignsubmission_mail_mail', array('id' => $tid))) {
        error("Mail ID is incorrect");
    }
    $message = $mailboxinstance->get_nested_from_child($mailobj);
    $mailstr = '<div style="background-color:#ffffff; margin:10px; padding:5px; border:1px;
        border-style:solid; border-color:#999999;">' . $message . '</div>';

    if ($re == 3) {
        $customdata->subject = get_string('fwd', 'assignsubmission_mailsimulator') . $mailobj->subject;
        $titlestr = get_string('fwd', 'assignsubmission_mailsimulator') . ' ' . $mailobj->subject;
    } else {
        $customdata->subject = get_string('re', 'assignsubmission_mailsimulator') . $mailobj->subject;
        $titlestr = get_string('re', 'assignsubmission_mailsimulator') . ' ' . $mailobj->subject;
    }
}

$customdata->reply = $re;

// Temp value here (5)!
$fileoptions = array(
    'subdirs' => 0,
    'maxbytes' => $mailboxinstance->get_config('maxbytes'),
    'maxfiles' => 5,
    'accepted_types' => '*'
    );
$customdata->fileoptions = $fileoptions;
$attachmentenabled = $mailboxinstance->get_config('filesubmissions');
$customdata->attachmentenabled = $attachmentenabled;

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mail_form.php');
$mailform = new mail_form('?gid=' . $gid, $customdata);

$draftitemid = file_get_submitted_draft_itemid('attachment');
file_prepare_draft_area($draftitemid, $context->id, 'assignsubmission_mailsimulator', 'attachment',
    empty($customdata->mailid)?null:$customdata->mailid, $fileoptions);

// Form processing and displaying is done here.
if ($mailform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id,
        get_string('returnmailbox', 'assignsubmission_mailsimulator'), 1);
} else if ($fromform = $mailform->get_data()) {
    if (!$teacher) { // We need to simulate the same structure as it would be a teacher's mail.
        $objmessage  = array();
        $objmessage['text'] = $fromform->message;
        $fromform->message = $objmessage;
    }
    $fromform->message=serialize($fromform->message);

    // In this case you process validated data. $mailform->get_data() returns data posted in form.
    if ($mailform->is_validated()) {
        if (!$attachmentenabled) {
            $fromform->attachment = 0; // Prevents error when writing to DB when attachments are disabled.
        }
        
        if ($DB->record_exists('assignsubmission_mail_mail', array('id' => $fromform->mailid))) {
            $existingattachment = $DB->get_field('assignsubmission_mail_mail', 'attachment', array('id' => $fromform->mailid));
            $mailboxinstance->update_mail($fromform);
            $currentmailid = $fromform->id; // We went back to original naming: id stands for mailid.
        } else {
            $currentmailid = $mailboxinstance->insert_mail($fromform, $gid);
        }

        if ($attachmentenabled) {
            // Check if attachments exist in draft area, if yes, set 'attachment=1' and save them.
            // If attachment is enabled, but filemanager has been disabled, then we save existing value from DB.
            if (isset($fromform->attachment)) {
                $info = file_get_draft_area_info($fromform->attachment);
                $present = ($info['filecount']>0) ? '1' : '';
                file_save_draft_area_files($fromform->attachment, $context->id, 'assignsubmission_mailsimulator', 'attachment',
                       $currentmailid, $fileoptions);
                $DB->set_field('assignsubmission_mail_mail', 'attachment', $present, array('id'=>$currentmailid));
            } else {
                $DB->set_field('assignsubmission_mail_mail', 'attachment', $existingattachment, array('id'=>$currentmailid));
            }
        }

        // Here add_template used to be called.
        if (!$teacher) {
            $obj = $mailboxinstance->get_mail_status($fromform->parent);
            $mailboxinstance->set_mail_status($obj->mailid, 2);
        }

        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id,
            get_string('returnmailbox', 'assignsubmission_mailsimulator'), 1);
    }

} else {
    // Set default data (if any).
    $mailform->set_data(array('attachment'=>$draftitemid));

    if ($teacher) {
        $mailboxinstance->print_tabs('addmail');
    } else {
        echo $OUTPUT->header();
    }

    // Display the mail composing form.
    $imgurl = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/';

    echo '<div style="width:80%; margin: auto">';
    echo '  <!-- Start Window Top Table -->';
    echo '  <table border="0" width="100%"  style="margin-bottom: -4px;">';
    echo '      <tr>';
    echo '          <td width="32px"><img src="' . $imgurl . 'shadow-top-left.png"></td>';
    echo '          <td width="8"><img src="' . $imgurl . 'window-top-left.png"></td>';
    echo '          <td class="window-top-bg"><div class="mailtoptitle">' . $titlestr . '</div></td>';
    echo '          <td width="8"><img src="' . $imgurl . 'window-top-right.png"></td>';
    echo '          <td width="32px"><img src="' . $imgurl . 'shadow-top-right.png"></td>';
    echo '      </tr>';
    echo '  </table>';
    echo '  <!-- End Window Top Table -->';

    echo '  <!-- Start Window Content Table -->';
    echo '  <table border="0" width="100%" style="margin-bottom:0em;">';
    echo '      <tr>';
    echo '          <td width="32px" class="shadow-left-bg"></td>';
    echo '          <td >';
    echo '              <table class="mailmidletable" width="100%" style="margin-bottom:0em;">';
    echo '                  <tr>';
    echo '                      <td style="background-color:lightgray;">' . $mailstr;


    // Display the form.
    $mailform->display();
}

// Display the end of mail composing form.
echo '                      </td>';
echo '                  </tr>';
echo '              </table>';
echo '          </td>';
echo '          <td width="32px" class="shadow-right-bg"></td>';
echo '      </tr>';
echo '  </table>';
echo '  <!-- End Window Content Table -->';

echo '  <!-- Start Bottom Shadow Table -->';
echo '  <table border="0"  width="100%">';
echo '      <tr>';
echo '          <td width="32px"><img src="' . $imgurl . 'shadow-bottom-left.png"></td>';
echo '          <td>';
echo '              <table border="0"  width="100%">';
echo '                  <tr>';
echo '                      <td width="32px"><img src="' . $imgurl . 'shadow-bottom-center-left.png"></td>';
echo '                      <td class="shadow-bottom-bg">&nbsp;</td>';
echo '                      <td width="32px"><img src="' . $imgurl . 'shadow-bottom-center-right.png"></td>';
echo '                  </tr>';
echo '              </table>';
echo '          </td>';
echo '          <td width="32px"><img src="' . $imgurl . 'shadow-bottom-right.png"></td>';
echo '      </tr>';
echo '  </table>';
echo '  <!-- End Bottom Shadow Table -->';
echo '</div>';

echo $OUTPUT->footer();
