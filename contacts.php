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
 * Contacts editing view for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG, $DB, $PAGE, $COURSE;

$id     = required_param('id', PARAM_INT);
$cm     = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course);

$PAGE->set_url('/mod/assign/submission/mailsimulator/contacts.php', array('id' => $id));
$PAGE->set_title('Contacts');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
$mailboxinstance = new mailbox($context, $cm, $course);
require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/contacts_form.php');
$mform = new contacts_form(null, array("moduleID"=>$id));

// Form processing and displaying is done here.
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $cm->id,
        get_string('returnmailbox', 'assignsubmission_mailsimulator') , 1);
} else if ($fromform = $mform->get_data()) {
    // In this case you process validated data. $mform->get_data() returns data posted in form.
    if ($mform->is_validated()) {
        for ($i = 0; $i < $fromform->option_repeats; $i++) {
            $contact = new stdClass;
            $contact->assignment = $cm->instance;
            $contact->firstname = $fromform->firstname[$i];
            $contact->lastname = $fromform->lastname[$i];
            $contact->email = $fromform->email[$i];
            // Insert/update record in database.
            if ($existingrecord = $DB->get_record('assignsubmission_mail_cntct', array('id' => $fromform->contactid[$i]))) {
                $contact->id = $existingrecord->id;
                if (strlen($contact->firstname.$contact->lastname.$contact->email) == 0) {
                    $mailboxinstance->delete_contact($contact->id);
                } else {
                    $DB->update_record('assignsubmission_mail_cntct', $contact);
                }
            } else {
                if (!strlen($contact->firstname.$contact->lastname.$contact->email) == 0) {
                     $DB->insert_record('assignsubmission_mail_cntct', $contact);
                }
            }
        }
        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/contacts.php?id=' . $cm->id);
    }

} else {
    // This branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
    // or on the first display of the form.

    // Get criteria from database.
    if ($contactlist = $DB->get_records_list('assignsubmission_mail_cntct', 'assignment', array($cm->instance))) {
        // Fill form with data.
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
    }
    $mailboxinstance->print_tabs('addcontacts');
    if (!$DB->record_exists('assignsubmission_mail_cntct', array('assignment' => $cm->instance))) {
        echo $OUTPUT->notification(get_string('addonecontact', 'assignsubmission_mailsimulator'));
    }
    echo $OUTPUT->notification(get_string('deletecontact', 'assignsubmission_mailsimulator'));
    // Display the form.
    if (isset($toform)) {
        $mform->set_data($toform);
    }
    $mform->display();
}

echo $OUTPUT->footer();
