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
 * Template editing form class for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/formslib.php');

class template_form extends moodleform {

    function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;

        $id = optional_param('id', 0, PARAM_INT);
        $userid = optional_param('userid', 0, PARAM_INT);
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        if (isset($this->_customdata->id) && $this->_customdata->id) {
            $mform->addElement('header', '', get_string('updatecorrectiontemplate', 'assignsubmission_mailsimulator'));
        } else {
            $mform->addElement('header', '', get_string('newcorrectiontemplate', 'assignsubmission_mailsimulator'));
        }

        $mform->addElement('hidden', 'templateid', $this->_customdata->id);
        $mform->setType('templateid', PARAM_INT);

        $mform->addElement('hidden', 'mailid', $this->_customdata->mailid);
        $mform->setType('mailid', PARAM_INT);

        $mform->addElement('hidden', 'randgroup', $this->_customdata->randgroup);
        $mform->setType('randgroup', PARAM_INT);

        $weights = array();

        for ($i = 1; $i <= $this->_customdata->maxweight; $i++) {
            $weights[$i] = $i;
        }

        if (isset($this->_customdata->weight) && $this->_customdata->weight != 0) {
            $select = $mform->addElement('select', 'weight', get_string('weight', 'assignsubmission_mailsimulator'), array());

            foreach ($weights as $key => $value) {
                if ($key == $this->_customdata->weight) {
                    $select->addOption($value, $key, array('selected' => 'selected'));
                } else {
                    $select->addOption($value, $key);
                }
            }
        } else {
            $mform->addElement('select', 'weight', get_string('weight', 'assignsubmission_mailsimulator'), $weights);
        }

        $mform->addHelpButton('weight', 'weight', 'assignsubmission_mailsimulator');

        $mform->addElement('hidden', 'deleted', $this->_customdata->deleted);
        $mform->setType('deleted', PARAM_INT);

        $mform->addElement('textarea', 'correctiontemplate', get_string('correctiontemplate', 'assignsubmission_mailsimulator') .
            ':', array('rows' => 4, 'cols' => 60));
        $mform->setType('correctiontemplate', PARAM_TEXT);
        $mform->setDefault('correctiontemplate', $this->_customdata->correctiontemplate);

        $this->add_action_buttons(true, 'Submit');

    }

    function validation($data, $files) {
        $errors = array();

        if (strlen(ltrim($data['correctiontemplate'])) < 1) {
            $errors['correctiontemplate'] = get_string('err_template', 'assignsubmission_mailsimulator');
        }

        return $errors;
    }

}
