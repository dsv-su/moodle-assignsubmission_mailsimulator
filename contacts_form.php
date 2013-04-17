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
 * Contact editing form class for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/validateurlsyntax.php');

class contacts_form extends moodleform {

    function definition() {
        global $CFG, $DB;

        $id = optional_param('id', 0, PARAM_INT);
        $cm = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
        $mform =& $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['moduleID']);
        $mform->setType('id', PARAM_INT);

        $repeatarray[] = $mform->createElement('header', '', get_string('contact', 'assignsubmission_mailsimulator') . ' {no}');
        $repeatarray[] = $mform->createElement('hidden', 'contactid', 0);
        $repeatarray[] = $mform->createElement('text', 'firstname', get_string('firstname'));
        $repeatarray[] = $mform->createElement('text', 'lastname', get_string('lastname'));
        $repeatarray[] = $mform->createElement('text', 'email', get_string('email'));

        $mform->setType('contactid', PARAM_INT);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->setType('email', PARAM_EMAIL);

        $repeatno = $DB->count_records('assignsubmission_mail_cntct', array("assignment"=>$cm->instance));
        $repeatno = $repeatno == 0 ? 1 : $repeatno;
        $this->repeat_elements($repeatarray, $repeatno, array(), 'option_repeats',
            'option_add_contact_fields', 1, get_string('addnewcontact', 'assignsubmission_mailsimulator'));

        $this->add_action_buttons(true, 'Submit');
    }

        // Form validation for errors is done here.
    function validation($data, $files) {
        $errors = array();

        foreach ($data as $key => $value) {

            if (is_array($value)) {
                for ($i = 0; $i < $data['option_repeats']; $i++) {
                    $inputname = $key . '[' . $i . ']';

                    if (!isset($errcount[$i])) {
                        $errcount[$i] = 0;
                    }

                    if (strlen(ltrim($value[$i])) < 1) {
                        $errors[$inputname] = get_string('err_required', 'form');
                        $errcount[$i] = $errcount[$i] + 1;
                    }
                    if ($key == 'email') {
                        if (!validateEmailSyntax($value[$i])) {
                            $errors[$inputname] =  get_string('err_email', 'form');
                        }
                    }
                }
            }
        }

        // For deletion of a contact.
        foreach ($errcount as $key => $value) {
            if ($value == 3) {
                unset($errors['firstname[' . $key . ']']);
                unset($errors['lastname[' . $key . ']']);
                unset($errors['email[' . $key . ']']);
            }
        }

        return $errors;
    }
}
