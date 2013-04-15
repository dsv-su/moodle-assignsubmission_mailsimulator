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
 * Mail editing form class for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');

class mail_form extends moodleform {

    function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;

        $id = optional_param('id', 0, PARAM_INT);
        $userid = optional_param('userid', 0, PARAM_INT);

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'mailid', $this->_customdata->mailid);
        $mform->setType('mailid', PARAM_INT);

        $mform->addElement('hidden', 'assignment', $this->_customdata->assignment);
        $mform->setType('assignment', PARAM_INT);

        $mform->addElement('hidden', 'parent', $this->_customdata->parent);
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('hidden', 'userid', $this->_customdata->userid);
        $mform->setType('userid', PARAM_INT);

        $prio = array(0 => '- ' . get_string('low', 'assignsubmission_mailsimulator'), 1 => '! ' .
            get_string('medium', 'assignsubmission_mailsimulator'), 2 => '!! ' .
            get_string('high', 'assignsubmission_mailsimulator'));
        $mform->addElement('select', 'priority', get_string('priority', 'assignsubmission_mailsimulator'), $prio);

        $to = $this->_customdata->to;

        // Student Reply mail.
        if ($this->_customdata->parent != 0 && !$this->_customdata->teacher) {

            $replyobj = $DB->get_record('assignsubmission_mail_mail', array('id' => $this->_customdata->parent));

            // Reply To All --------- or forward.
            if ($this->_customdata->reply > 1) {
                $toarr = $DB->get_records('assignsubmission_mail_to', array('mailid' => $this->_customdata->parent));
                foreach ($toarr as $value) {
                    $replyto[$value->contactid] = $value->contactid;
                }
            }

            // Reply To Sender.
            if ($replyobj->userid != 0) {
                $replyto[TO_STUDENT_ID] = TO_STUDENT_ID;
            } else {
                $replyto[$replyobj->sender] = $replyobj->sender;
            }

            if ($this->_customdata->reply <= 2) {
                $select = $mform->addElement('select', 'to', get_string('to'), array());

                if ($this->_customdata->reply == 2) {
                    $select->setMultiple(true);
                }

                foreach ($to as $key => $value) {
                    if (key_exists($key, $replyto)) {
                        $select->addOption($value, $key, array('selected' => 'selected'));
                    }
                }
            } else if ($this->_customdata->reply == 3) {
                $select = $mform->addElement('select', 'to', get_string('to'), $to);
                $select->setMultiple(true);
            }

        } else { // Teacher's new mail.
            if ($this->_customdata->reply) {

                $replyobj = $DB->get_record('assignsubmission_mail_mail', array('id' => $this->_customdata->parent));

                if ($this->_customdata->reply == 2) {
                    $toarr = $DB->get_records('assignsubmission_mail_to', array('mailid' => $this->_customdata->parent));
                    foreach ($toarr as $value) {
                        $replyto[$value->contactid] = $value->contactid;
                    }
                }

                // Reply To Sender.
                if ($replyobj->userid != 0) {
                    $replyto[TO_STUDENT_ID] = TO_STUDENT_ID;
                } else {
                    $replyto[$replyobj->sender] = $replyobj->sender;
                }

                $select = $mform->addElement('select', 'to', get_string('to'), array());
                $select->setMultiple(true);

                foreach ($to as $key => $value) {
                    if (key_exists($key, $replyto)) {
                        $select->addOption($value, $key, array('selected' => 'selected'));
                    } else {
                        $select->addOption($value, $key);
                    }
                }

            } else {
                if (isset($this->_customdata->sentto)) {
                    $select = $mform->addElement('select', 'to', get_string('to'), array());
                    $select->setMultiple(true);

                    foreach ($to as $key => $value) {
                        if (key_exists($key, $this->_customdata->sentto)) {
                            $select->addOption($value, $key, array('selected' => 'selected'));
                        } else {
                            $select->addOption($value, $key);
                        }
                    }

                } else {
                    $select = $mform->addElement('select', 'to', get_string('to'), $to, array('size' => count($to)));
                    $select->setMultiple(true);
                }
            }
        }

        if (!$this->_customdata->teacher) {
            $mform->addElement('hidden', 'sender', 0);
            $mform->setType('sender', PARAM_INT);
            $mform->addElement('hidden', 'timesent', time());
            $mform->setType('timesent', PARAM_INT);
        } else {
            $from = $DB->get_field('assignsubmission_mail_to', 'contactid', array('mailid' => $this->_customdata->parent));
            unset($to[9999999]);

            if ($from) {
                $select = $mform->addElement('select', 'sender', get_string('from'), array());

                foreach ($to as $key => $value) {
                    if ($key == $from) {
                        $select->addOption($value, $key, array('selected' => 'selected'));
                    } else {
                        $select->addOption($value, $key);
                    }
                }
            } else {
                $mform->addElement('select', 'sender', get_string('from'), $to);
            }

            $mform->addElement('date_time_selector', 'timesent', get_string('timesent', 'assignsubmission_mailsimulator'));
            $mform->setType('timesent', PARAM_TEXT);
            $mform->setDefault('timesent', $this->_customdata->timesent);
        }

        $mform->addElement('text', 'subject', get_string('subject', 'assignsubmission_mailsimulator'), array('size' => '83'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->setDefault('subject', $this->_customdata->subject);

        if ($this->_customdata->teacher) {
            $mform->addElement('editor', 'message', get_string('message', 'assignsubmission_mailsimulator'),
                array('cols' => 83, 'rows' => 20));
            $mform->setType('message', PARAM_RAW); // To be cleaned before display.
        } else {
            $mform->addElement('textarea', 'message', get_string('message', 'assignsubmission_mailsimulator'),
                array('cols' => 83, 'rows' => 20));
            $mform->setType('message', PARAM_TEXT);
        }

        $mform->setDefault('message', $this->_customdata->message);
        // Here upload of the files goes!
        if (((!isset($this->_customdata->inactive) || $this->_customdata->inactive) && ($this->_customdata->attachmentenabled))) {
            $mform->addElement('filemanager', 'attachment', get_string('attachment', 'forum'), null,
                $this->_customdata->fileoptions);
        }

        $this->add_action_buttons(true, 'Submit');
    }

    // Custom validation is done here.
    function validation($data, $files) {
        $errors = array();

        if (strlen(ltrim($data['subject'])) < 1) {
            $errors['subject'] = get_string('err_emptysubject', 'assignsubmission_mailsimulator');
        }
        if (is_array($data['message'])) {
            if (strlen(ltrim($data['message']['text'])) < 1) {
                $errors['message'] = get_string('err_emptymessage', 'assignsubmission_mailsimulator');
            }
        } else {
            if (strlen(ltrim($data['message'])) < 1) {
                $errors['message'] = get_string('err_emptymessage', 'assignsubmission_mailsimulator');
            }
        }
        if ($data['timesent'] > time()) {
            $errors['timesent'] = get_string('err_date', 'assignsubmission_mailsimulator');
        }
        if (!isset($data['to'])) {
            $errors['to'] = get_string('err_reciever', 'assignsubmission_mailsimulator');
        }

        return $errors;
    }
}