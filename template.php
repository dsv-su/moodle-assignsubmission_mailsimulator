<?php

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG, $DB, $PAGE, $COURSE;

// Get course ID and mail ID
$id = optional_param('id', 0, PARAM_INT);         // Course module ID
$mid = optional_param('mid', 0, PARAM_INT);       // Mail ID
$gid = optional_param('gid', 0, PARAM_INT);       // Group ID

$cm     = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_course::instance($course->id);

require_login($course);

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
$mailboxinstance = new mailbox($context, $cm, $course);

$mailstr = 'NO MAIL IN URL<br>';

if ($mid) {
    // Check if mail exists. A template must have a mail.
    if (!$mailobj = $DB->get_record('assignsubmission_mail_mail', array('id' => $mid))) {
        echo $OUTPUT->error_text("Mail ID is incorrect");
    }

    $mail = $mailboxinstance->get_nested_reply_object($mailobj);

    if (!$mail) {
        $mail = $mailobj;
        $mail->message = unserialize($mail->message)["text"];
    }

    $mailstr = '<div style="background-color:#ffffff; margin:auto; margin-bottom: 20px; padding:5px; border:1px; border-style:solid; border-color:#999999; width:80%">' . format_text(($mail->message), FORMAT_MOODLE) . '</div>';
    $customdata = $mailboxinstance->prepare_parent($mid, $gid);
} else {
    $customdata = $mailboxinstance->prepare_parent();
}

if ($existingparent = $DB->get_record("assignsubmission_mail_tmplt", array("mailid" => $mid))) {
    $existingparent->maxweight = $customdata->maxweight;
    $existingparent->randgroup = $customdata->randgroup;
    $customdata = $existingparent;
}

echo $OUTPUT->header();

echo $mailstr;

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/template_form.php');
//Instantiate simplehtml_form 
$tform = new template_form(null, $customdata);

if ($tform->is_cancelled()){
    echo "CANCELLED";
} else if ($fromform=$tform->get_data()){
    if ($tform->is_validated()) {
        $tstatus = 'Template ';

        if (isset($fromform->templateid) && $DB->record_exists('assignsubmission_mail_tmplt', array('id' => $fromform->templateid))) {
            $fromform->id = $fromform->templateid;
            $DB->update_record('assignsubmission_mail_tmplt', $fromform);
            $tstatus .= $fromform->id . ' UPDATED';
            echo $tstatus;
        } else {
            $tid = $DB->insert_record('assignsubmission_mail_tmplt', $fromform);
            $tstatus .= $tid . ' ADDED';
            echo $tstatus;
        }
        //redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, $pstatus, 0);
    }
} else {
    $tform->set_data($toform);
    $tform->display();
}

echo $OUTPUT->footer();
