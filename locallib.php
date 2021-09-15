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
 * Library class for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *					Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');
require_once($CFG->dirroot . '/mod/assign/submission/mailsimulator/lib.php');

class assign_submission_mailsimulator extends assign_submission_plugin {

    /**
     * Get the name of the submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('mailsimulator', 'assignsubmission_mailsimulator');
    }

    /**
     * Get the default settings for the blog submission plugin.
     *
     * @param MoodleQuickForm $mform The form to append the elements to.
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $DB, $COURSE, $OUTPUT;

        $cmid = optional_param('update', 0, PARAM_INT);

        $filesubmissionsdefault = $this->get_config('filesubmissions');
        if ($filesubmissionsdefault === false) {
            $filesubmissionsdefault = get_config('assignsubmission_mailsimulator', 'filesubmissions');
        }
        $mailnumberdefault = $this->get_config('mailnumber');
        if ($mailnumberdefault === false) {
            $mailnumberdefault = get_config('assignsubmission_mailsimulator', 'mailnumber');
        }
        $maxweightdefault = $this->get_config('maxweight');
        if ($maxweightdefault === false) {
            $maxweightdefault = get_config('assignsubmission_mailsimulator', 'maxweight');
        }
        $maxbytesdefault = $this->get_config('maxbytes');
        if ($maxbytesdefault === false) {
            $maxbytesdefault = get_config('assignsubmission_mailsimulator', 'maxbytes');
        }
        $teacherdefault = $this->get_config('teacherid');
        if ($teacherdefault === false) {
            $teacherdefault = get_config('assignsubmission_mailsimulator', 'teacherid');
        }

        /*
        $mform->setDefault('assignsubmission_file_enabled', 0);
        $mform->setDefault('assignsubmission_blog_enabled', 0);
        $mform->setDefault('assignsubmission_online_enabled', 0);
        $mform->disabledIf('assignsubmission_file_enabled', 'assignsubmission_mailsimulator_enabled', 'eq', 1);
        $mform->disabledIf('assignsubmission_blog_enabled', 'assignsubmission_mailsimulator_enabled', 'eq', 1);
        $mform->disabledIf('assignsubmission_onlinetext_enabled', 'assignsubmission_mailsimulator_enabled', 'eq', 1);
        $mform->setDefault('submissiondrafts', 1);
        $mform->setDefault('teamsubmission', 0);
        $mform->disabledIf('teamsubmission', 'assignsubmission_mailsimulator_enabled', 'eq', 1);
        */

        // Select whether attachments enabled.
        $mform->addElement('select', 'assignsubmission_mailsimulator_filesubmissions',
            get_string('filesubmissions', 'assignsubmission_mailsimulator'),
            array(0=>'No', 1=>'Yes'));
        $mform->setDefault('assignsubmission_mailsimulator_filesubmissions', $filesubmissionsdefault);
        $mform->addHelpButton('assignsubmission_mailsimulator_filesubmissions', 'filesubmissions',
            'assignsubmission_mailsimulator');
        $mform->disabledIf('assignsubmission_mailsimulator_filesubmissions', 'assignsubmission_mailsimulator_enabled', 'notchecked');
        $mform->hideIf('assignsubmission_mailsimulator_filesubmissions', 'assignsubmission_mailsimulator_enabled', 'notchecked');

        // Set up max weight per mail.
        $maxweightoptions = array();
        for ($i=1; $i <= ASSIGNSUBMISSION_MAILSIMULATOR_MAXWEIGHT; $i++) {
            $maxweightoptions[$i] = $i;
        }
        $mform->addElement('select', 'assignsubmission_mailsimulator_maxweight',
            get_string('maxweight', 'assignsubmission_mailsimulator'),
            $maxweightoptions);
        $mform->setDefault('assignsubmission_mailsimulator_maxweight', $maxweightdefault);
        $mform->addHelpButton('assignsubmission_mailsimulator_maxweight', 'maxweight', 'assignsubmission_mailsimulator');
        $mform->disabledIf('assignsubmission_mailsimulator_maxweight', 'assignsubmission_mailsimulator_enabled', 'notchecked');
        $mform->hideIf('assignsubmission_mailsimulator_maxweight', 'assignsubmission_mailsimulator_enabled', 'notchecked');

        // Set up number of mails for this assignment.
        $mailsoptions = array();
        for ($i=1; $i <= ASSIGNSUBMISSION_MAILSIMULATOR_MAXMAILS; $i++) {
            $mailsoptions[$i] = $i;
        }

        $mform->addElement('select', 'assignsubmission_mailsimulator_mailnumber',
            get_string('defaultnumbermails', 'assignsubmission_mailsimulator'),
            $mailsoptions);
        $mform->setDefault('assignsubmission_mailsimulator_mailnumber', $mailnumberdefault);
        $mform->addHelpButton('assignsubmission_mailsimulator_mailnumber', 'defaultnumbermails', 'assignsubmission_mailsimulator');
        $mform->disabledIf('assignsubmission_mailsimulator_mailnumber', 'assignsubmission_mailsimulator_enabled', 'notchecked');
        $mform->hideIf('assignsubmission_mailsimulator_mailnumber', 'assignsubmission_mailsimulator_enabled', 'notchecked');

        // Set up max attachment size.
        if (isset($CFG->maxbytes)) {
            $mform->addElement('select', 'assignsubmission_mailsimulator_maxbytes',
                get_string('maxattachments', 'assignsubmission_mailsimulator'),
                get_max_upload_sizes($CFG->maxbytes));
            $mform->setDefault('assignsubmission_mailsimulator_maxbytes', $maxbytesdefault);
            $mform->addHelpButton('assignsubmission_mailsimulator_maxbytes', 'maxattachments', 'assignsubmission_mailsimulator');
            $mform->disabledIf('assignsubmission_mailsimulator_maxbytes', 'assignsubmission_mailsimulator_enabled', 'notchecked');
            $mform->hideIf('assignsubmission_mailsimulator_maxbytes', 'assignsubmission_mailsimulator_enabled', 'notchecked');
        }

        // Set up teacher contact id.
        $sql = 'SELECT distinct u.id AS uid, u.firstname, u.lastname, u.email ' .
            'FROM {course} as c, {role_assignments} AS ra, {user} AS u, {context} AS ct ' .
            'WHERE c.id = ct.instanceid AND ra.roleid =3 AND ra.userid = u.id ' .
            // AND ct.id = ra.contextid '.
            'AND c.id = ' . $COURSE->id;
        $records = $DB->get_records_sql($sql);

        foreach ($records as $teacher) {
            $teachers[$teacher->uid] = $teacher->firstname . ' ' . $teacher->lastname . ' &lt;' . $teacher->email . '&gt;';
        }

        $mform->addElement('select', 'assignsubmission_mailsimulator_teacherid',
            get_string('teacherid', 'assignsubmission_mailsimulator'),
            $teachers);
        $mform->setDefault('assignsubmission_mailsimulator_teacherid', $teacherdefault);
        $mform->addHelpButton('assignsubmission_mailsimulator_teacherid', 'teacherid', 'assignsubmission_mailsimulator');
        $mform->disabledIf('assignsubmission_mailsimulator_teacherid', 'assignsubmission_mailsimulator_enabled', 'notchecked');
        $mform->hideIf('assignsubmission_mailsimulator_teacherid', 'assignsubmission_mailsimulator_enabled', 'notchecked');

    }

    /**
     * Save the settings for the plugin
     * 
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $this->set_config('filesubmissions', $data->assignsubmission_mailsimulator_filesubmissions);
        $this->set_config('mailnumber', $data->assignsubmission_mailsimulator_mailnumber);
        $this->set_config('maxweight', $data->assignsubmission_mailsimulator_maxweight);
        $this->set_config('maxbytes', $data->assignsubmission_mailsimulator_maxbytes);
        $this->set_config('teacherid', $data->assignsubmission_mailsimulator_teacherid);
        return true;
    }

    /**
     * Here the mailbox is to be displayed.
     * 
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        $cmid = $this->assignment->get_course_module()->id;
        $mailboxurl = new moodle_url('/mod/assign/submission/mailsimulator/mailbox.php', array("id" => $cmid));
        redirect($mailboxurl);
        return true;
    }


    /**
     * Displays all sent mails for this assignment from a specified student.
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        global $CFG, $DB, $OUTPUT;

        $cm         = $this->assignment->get_course_module();
        $id         = $cm->id;
        $sid        = optional_param('sid', $submission->id, PARAM_INT);
        $gid        = optional_param('gid', 0, PARAM_INT);
        $userid     = $DB->get_field('assign_submission', 'userid', array("id" => $sid));
        $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $context    = context_module::instance($cm->id);
        require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
        $mailboxinstance = new mailbox($context, $cm, $course);

        ob_start();
        if ($submission) {
            $mailboxinstance->view_grading_feedback($userid);
        } else {
            print_error(get_string('submissionstatus_', 'assign'));
        }
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Displays the summary of the submission
     *
     * @param stdClass $submission The submission to show a summary of
     * @param bool $showviewlink Will be set to true to enable the view link
     * @return string
     */

    public function view_summary(stdClass $submission, & $showviewlink) {
        global $DB;

        $showviewlink = true;
        $cm = $this->assignment->get_course_module();
        $cmid = $cm->id;
        $userid = $submission->userid+0;
        $mailssent = $DB->count_records('assignsubmission_mail_mail', array(
            'userid' => $userid,
            'assignment' => $submission->assignment
            ));

        $sql = 'SELECT sm.id, sm.mailid, t.weight, sm.gainedweight, sm.feedback, m.sender, m.subject, m.message,
        t.correctiontemplate, m.timesent, m.priority, m.attachment, m.userid
        FROM {assignsubmission_mail_sgndml} AS sm
        LEFT JOIN {assignsubmission_mail_tmplt} AS t ON sm.mailid = t.mailid
        LEFT JOIN {assignsubmission_mail_mail} AS m ON m.id = sm.mailid
        WHERE sm.userid = ' . $userid . '
        AND m.assignment = ' . $cm->instance;

        $usermails = $DB->get_records_sql($sql);
        $maxweight = 0;
        $weightgiven = 0;

        foreach ($usermails as $mailobj) {
            $maxweight += $mailobj->weight;
            $weightgiven += ( $mailobj->gainedweight * $mailobj->weight);
        }

        $result = html_writer::start_tag('div');
        $result .= get_string('mailssent', 'assignsubmission_mailsimulator') . $mailssent;
        if (!$submission) {
            $result .= html_writer::empty_tag('br');
            $result .= get_string('weightgiven', 'assignsubmission_mailsimulator') . $weightgiven;
        }
        $result .= html_writer::end_tag('div');

        return $result;

    }

    /**
     * Produce a list of files suitable for export that represents this submission
     * 
     * @param stdClass $submission
     * @param stdClass $user
     * @return array an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        global $DB, $CFG;

        $files = array();

        $cm         = $this->assignment->get_course_module();
        $id         = $cm->id;
        $sid        = optional_param('sid', $submission->id, PARAM_INT);
        $gid        = optional_param('gid', 0, PARAM_INT);
        $userid     = $submission->userid+0;
        $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $context    = context_module::instance($cm->id);
        
        require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
        $mailboxinstance = new mailbox($context, $cm, $course);
        $assigninstance = new assign($context, $cm, $course);

        $user = $DB->get_record('user', array('id' => $submission->userid), 'id, username, firstname, lastname', MUST_EXIST);
        $finaltext  = html_writer::start_tag('html');
        $finaltext .= html_writer::start_tag('head');
        $finaltext .= html_writer::start_tag('title');
        $finaltext .= get_string('mailssent', 'assignsubmission_mailsimulator') . ' by '. fullname($user) .
            ' on ' . $assigninstance->get_instance()->name;
        $finaltext .= html_writer::end_tag('title');
        $finaltext .= html_writer::empty_tag('meta', array(
            'http-equiv' => 'Content-Type',
            'content' => 'text/html; charset=utf-8'
        ));
        $finaltext .= html_writer::end_tag('head');
        $finaltext .= html_writer::start_tag('body');

        ob_start();
        echo $mailboxinstance->view_grading_feedback($userid, true, true);
        $finaltext .= ob_get_contents();
        ob_end_clean();

        $finaltext .= html_writer::end_tag('body');
        $finaltext .= html_writer::end_tag('html');
        $files[get_string('mailsimulatorfilename', 'assignsubmission_mailsimulator')] = array($finaltext);

        $mails = $DB->get_records('assignsubmission_mail_mail', array(
            'userid' => $userid,
            'assignment' => $cm->instance
        ));

        foreach ($mails as $mid => $mail) {

            $fs = get_file_storage();
            $attachments = $fs->get_area_files($this->assignment->get_context()->id,
                'assignsubmission_mailsimulator',
                'attachment',
                $mid,
                'timemodified',
                false);

            foreach ($attachments as $file) {
                // Do we return the full folder path or just the file name?
                if (isset($submission->exportfullpath) && $submission->exportfullpath == false) {
                    $files[$file->get_filename()] = $file;
                } else {
                    $files[$file->get_filepath().$file->get_filename()] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * No text is set for this plugin
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        global $DB;
        $usermails = $DB->record_exists('assignsubmission_mail_mail', array('userid' => $submission->userid+0));
        return empty($usermails);
    }  

}
