<?php

require_once(dirname(__FILE__).'/../../../../config.php');
//require_once($CFG->dirroot.'/mod/assign/locallib.php');
global $CFG, $DB, $PAGE, $COURSE;

$id   = required_param('id', PARAM_INT);
$cm     = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course);

echo $OUTPUT->header();

$PAGE->set_url('/mod/assign/submission/mailsimulator/contacts.php');
$PAGE->set_title('Contacts');
$PAGE->set_pagelayout('standard');

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/contacts_form.php');

//Instantiate simplehtml_form 
$mform = new contacts_form(null, array("moduleID"=>$id));
 
//Form processing and displaying is done here
if ($mform->is_cancelled()) {
    var_dump("cancel");
    //Handle form cancel operation, if cancel button is present on form
} else if ($fromform = $mform->get_data()) {
  //In this case you process validated data. $mform->get_data() returns data posted in form.
    if ($mform->is_validated()) {
        for ($i = 0; $i < $fromform->option_repeats; $i++) {
            $contact = new stdClass;
            $contact->assignment = $id;
            $contact->firstname = $fromform->firstname[$i];
            $contact->lastname = $fromform->lastname[$i];
            $contact->email = $fromform->email[$i];
            // Insert/Update record in database
            if ($existingRecord = $DB->get_record('assignsubmission_mail_cntct', array('id' => $fromform->contactid[$i]))) {
                $contact->id = $existingRecord->id;

                if(strlen($contact->firstname.$contact->lastname.$contact->email) == 0) {
                  //  $assignmentinstance->delete_contact($contact->id);
                } else {
                    $DB->update_record('assignsubmission_mail_cntct', $contact);
                }
            } else {
                if(!strlen($contact->firstname.$contact->lastname.$contact->email) == 0) {
                     $DB->insert_record('assignsubmission_mail_cntct', $contact);
                }
            }
        }
        //redirect($CFG->wwwroot . '/mod/assignment/view.php?id=' . $cm->id, 'Add contact', 0);
    }

} else {
  // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
  // or on the first display of the form.

    // Get criteria from database
    if ($contactlist = $DB->get_records_list('assignsubmission_mail_cntct', 'assignment', array($id))) {
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
