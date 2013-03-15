<?php

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/validateurlsyntax.php');

class contacts_form extends moodleform {
 
    function definition() {
        global $CFG, $DB;
 
        $id = optional_param('id', 0, PARAM_INT);
        $cm = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);

        $mform =& $this->_form; // Don't forget the underscore! 

        $mform->addElement('hidden', 'id', $this->_customdata['moduleID']);
        $mform->setType('id', PARAM_INT);

        $repeatarray[] = &MoodleQuickForm::createElement('header', '', 'Contact' . ' {no}'   );
        $repeatarray[] = &MoodleQuickForm::createElement('hidden', 'contactid', 0);
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'firstname', get_string('firstname'));
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'lastname', get_string('lastname'));
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'email', get_string('email'));

        $repeatno = $DB->count_records('assignsubmission_mail_cntct', array("assignment"=>$cm->instance));
        $repeatno = $repeatno == 0 ? 1 : $repeatno;
        $this->repeat_elements($repeatarray, $repeatno, array(), 'option_repeats', 'option_add_contact_fields', 1, 'Add a new contact');

        $this->add_action_buttons(true, 'Submit');
    }

        //Custom validation should be added here
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

        // For deletion of contact
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