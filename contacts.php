<?php

require_once(dirname(__FILE__).'/../../../../config.php');
//require_once($CFG->dirroot.'/mod/assign/locallib.php');
global $CFG, $DB, $PAGE, $COURSE;

$id     = required_param('id', PARAM_INT);
$cm     = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_module::instance($cm->id);

require_login($course);

echo $OUTPUT->header();

$PAGE->set_url('/mod/assign/submission/mailsimulator/contacts.php');
$PAGE->set_title('Contacts');
$PAGE->set_pagelayout('standard');

require($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
$mailboxinstance = new mailbox($context, $cm, $course);

$mailboxinstance->print_tabs('addcontacts');

if(!$DB->record_exists('assignsubmission_mail_cntct', array('assignment' => $cm->instance))) {
    echo $OUTPUT->notification(get_string('addonecontact', 'assignsubmission_mailsimulator'));
}

echo $OUTPUT->notification(get_string('deletecontact', 'assignsubmission_mailsimulator'));

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/contacts_form.php');

//Instantiate simplehtml_form 
$mform = new contacts_form(null, array("moduleID"=>$id));
 
//Form processing and displaying is done here
if ($mform->is_cancelled()) {

    redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id, '<center>Return to the mailbox view</center>' , 0);

    //Handle form cancel operation, if cancel button is present on form
} else if ($fromform = $mform->get_data()) {
  //In this case you process validated data. $mform->get_data() returns data posted in form.
    if ($mform->is_validated()) {
        for ($i = 0; $i < $fromform->option_repeats; $i++) {
            $contact = new stdClass;
            $contact->assignment = $cm->instance;
            $contact->firstname = $fromform->firstname[$i];
            $contact->lastname = $fromform->lastname[$i];
            $contact->email = $fromform->email[$i];
            // Insert/Update record in database
            if ($existingRecord = $DB->get_record('assignsubmission_mail_cntct', array('id' => $fromform->contactid[$i]))) {
                $contact->id = $existingRecord->id;

                if(strlen($contact->firstname.$contact->lastname.$contact->email) == 0) {
                    $mailboxinstance->delete_contact($contact->id);
                } else {
                    $DB->update_record('assignsubmission_mail_cntct', $contact);
                }

            } else {
                if(!strlen($contact->firstname.$contact->lastname.$contact->email) == 0) {
                     $DB->insert_record('assignsubmission_mail_cntct', $contact);
                }
            }
        }
        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/contacts.php?id=' . $cm->id);
    }

} else {
  // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
  // or on the first display of the form.

    // Get criteria from database
    if ($contactlist = $DB->get_records_list('assignsubmission_mail_cntct', 'assignment', array($cm->instance))) {
        // Fill form with data
        $toform = new stdClass;
        $toform->contactid = array();
        $toform->firstname = array();
        $toform->lastname = array();
        $toform->email = array();
        foreach ($contactlist as $i => $contact) {
            $toform->contactid[] = (int) ($contact->id);
            $toform->firstname[] = $contact->firstname;
            $toform->lastname[] = $contact->lastname;
            $toform->email[] = $contact->email;
        }
        $mform->set_data($toform);
    }
    //displays the form
    $mform->display();
}

echo $OUTPUT->footer();
