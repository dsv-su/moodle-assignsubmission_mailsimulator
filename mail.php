<?php

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG, $DB, $PAGE, $COURSE;

$id   = required_param('id', PARAM_INT);
$pid = optional_param('pid', 0, PARAM_INT);       // Parent ID
$gid = optional_param('gid', 0, PARAM_INT);       // Group ID
$re = optional_param('re', 0, PARAM_INT);         // Reply 1=one, 2=all
$mid = optional_param('mid', 0, PARAM_INT);

$cm     = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_module::instance($cm->id);

require_login($course);

echo $OUTPUT->header();

//$PAGE->set_url('/mod/assign/submission/mailsimulator/mail.php');
//$PAGE->set_title('New Mail');
//$PAGE->set_pagelayout('standard');

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
$mailboxinstance = new mailbox($context, $cm, $course);

$teacher = has_capability('mod/assign:grade', context_module::instance($cm->id));

$mailboxinstance->print_tabs('addmail');

$customdata = $mailboxinstance->prepare_mail();

$titlestr = get_string('newmail', 'assignsubmission_mailsimulator');
$mailstr = '';

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

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mail_form.php');
//Instantiate simplehtml_form 
$mailform = new mail_form('?gid=' . $gid, $customdata);

//Form processing and displaying is done here
if ($mailform->is_cancelled()) {
  var_dump('Cancel');
    //Handle form cancel operation, if cancel button is present on form
} else if ($fromform = $mailform->get_data()) {
  $fromform->message=serialize($fromform->message);
  $fromform->attachment=0;
  //In this case you process validated data. $mform->get_data() returns data posted in form.
    if ($mailform->is_validated()) {
        if ($DB->record_exists('assignsubmission_mail_mail', array('id' => $fromform->mailid))) {
           // $assignmentinstance->update_mail($fromform);
        } else {
          $mailboxinstance->insert_mail($fromform, $gid);
        }

        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id, '', 0);
    }
} else {
  // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
  // or on the first display of the form.
  //Set default data (if any)
    $mailform->set_data($toform);
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
