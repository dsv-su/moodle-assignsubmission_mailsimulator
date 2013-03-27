<?php

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->libdir.'/filelib.php');

global $CFG, $DB, $PAGE, $COURSE;

$id   = required_param('id', PARAM_INT);
$tid = optional_param('tid', 0, PARAM_INT);       // Template ID
$gid = optional_param('gid', 0, PARAM_INT);       // Group ID
$re = optional_param('re', 0, PARAM_INT);         // Reply 1=one, 2=all
$mid = optional_param('mid', 0, PARAM_INT);

$cm     = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_module::instance($cm->id);

require_login($course);

//$PAGE->set_url('/mod/assign/submission/mailsimulator/mail.php');
$PAGE->set_title('New Mail');
//$PAGE->set_pagelayout('standard');

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
$mailboxinstance = new mailbox($context, $cm, $course);

echo $OUTPUT->header();

$teacher = has_capability('mod/assign:grade', context_module::instance($cm->id));

$mailboxinstance->print_tabs('addmail');

if ($mid) {
    if (!$teacher) {echo 'Go away!';}
    
    $customdata = $DB->get_record('assignsubmission_mail_mail', array('id' => $mid));
    if (!$customdata) {
        //error("Mail ID was incorrect", $CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id);
    }
    if (!$customdata->userid == 0) { // Only edit teacher mail
        //error("You are not allowed to edit this mail.", $CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id);
    }

    //$teacherid = $DB->get_field('assignment', 'var3', 'id', $assignmentinstance->assignment->id);
    $contacts = $DB->get_records('assignsubmission_mail_cntct', array('assignment' => $cm->instance));
    $senttoobjarr = $DB->get_records('assignsubmission_mail_to', array('mailid' => $mid), 'contactid');
    $sendtoarr = array();

    foreach ($senttoobjarr as $key => $value) {
        $sendtoarr[$value->contactid] = $value->contactid;
    }

    //$teacherobj = $DB->get_record('user', array('id' => $teacherid), 'firstname, lastname, email');

    if ($contacts) {
        foreach ($contacts as $key => $con) {
            $contacts[$key] = $con->firstname . ' ' . $con->lastname . ' &lt;' . $con->email . '&gt;';
        }
    }

    //$contacts[0] = $teacherobj->firstname . ' ' . $teacherobj->lastname . ' &lt;' . $teacherobj->email . '&gt;';
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

if ($tid) { // If a student replies to or forward a mail

    if (!$mailobj = $DB->get_record('assignsubmission_mail_mail', array('id' => $tid))) {
        //error("Mail ID is incorrect");
    }
    $message = $mailboxinstance->get_nested_from_child($mailobj);
    $mailstr = '<div style="background-color:#ffffff; margin:10px; padding:5px; border:1px; border-style:solid; border-color:#999999;">' . $message . '</div>';

    if ($re == 3) {
        $customdata->subject = get_string('fwd', 'assignsubmission_mailsimulator') . $mailobj->subject;
        $titlestr = get_string('fwd', 'assignsubmission_mailsimulator') . ' ' . $mailobj->subject;
    } else {
        $customdata->subject = get_string('re', 'assignsubmission_mailsimulator') . $mailobj->subject;
        $titlestr = get_string('re', 'assignsubmission_mailsimulator') . ' ' . $mailobj->subject;
    }
}

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

$customdata->reply = $re;

$fileoptions = array('subdirs' => 0, 'maxbytes' => get_config('assignsubmission_mailsimulator')->maxbytes, 'maxfiles' => get_config('assignsubmission_mailsimulator')->maxfilesubmissions, 'accepted_types' => '*' );
$customdata->fileoptions = $fileoptions;

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mail_form.php');
//Instantiate simplehtml_form 
$mailform = new mail_form('?gid=' . $gid, $customdata);

// Get existing drafts
if ($customdata->attachment>0) {
    $draftitemid = $customdata->attachment;
} else {
    $draftitemid = file_get_submitted_draft_itemid('attachment');
    file_prepare_draft_area($draftitemid, $context->id, 'assignsubmission_mailsimulator', 'attachment', empty($fromform->id)?null:$customdata->id, $fileoptions);
}

//Form processing and displaying is done here
if ($mailform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id, '<center>Return to the mailbox view</center>', 0);
    //Handle form cancel operation, if cancel button is present on form

} else if ($fromform = $mailform->get_data()) {
    if (!$teacher) { // We need to simulate the same structure as it would be a teacher's mail
        $objmessage  = array();
        $objmessage['text'] = $fromform->message;
        $fromform->message = $objmessage;
    }
    $fromform->message=serialize($fromform->message);

  //In this case you process validated data. $mailform->get_data() returns data posted in form.
    if ($mailform->is_validated()) {

        //Check if attachments exist in draft area, if not, set 'attachment=0'
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'assignsubmission_mailsimulator', 'attachment', $fromform->mailid);
        if ($fromform->attachment>0 && (!$files)) {
            $fromform->attachment = 0;
        } 

        if ($DB->record_exists('assignsubmission_mail_mail', array('id' => $fromform->mailid))) {
          $mailboxinstance->update_mail($fromform);
        } else {
          $currentmailid = $mailboxinstance->insert_mail($fromform, $gid);
        }

        file_save_draft_area_files($fromform->attachment, $context->id, 'assignsubmission_mailsimulator', 'attachment',
                   $currentmailid, array('subdirs' => 0, 'maxbytes' => $maxbytes, 'maxfiles' => 5));

        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id, '', 0);
    }

} else {
  //Set default data (if any)
    $mailform->set_data($toform);
    $mailform->set_data(array('attachment'=>$draftitemid));
  //displays the form
    $mailform->display();
}
 
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
