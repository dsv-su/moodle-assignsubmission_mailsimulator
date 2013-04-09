<?php
/**
 * Library class for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *					Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('ASSIGNSUBMISSION_MAILSIMULATOR_MAXWEIGHT', 10);
define('ASSIGNSUBMISSION_MAILSIMULATOR_MAXMAILS', 10);

require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

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
        global $CFG, $DB, $COURSE;

        $cmid = optional_param('update', 0, PARAM_INT);

        //$filesubmissionsdefault = get_config('assignsubmission_mailsimulator')->filesubmissions;
        //$mailnumberdefault = get_config('assignsubmission_mailsimulator')->mailnumber;
        //$maxweightdefault = get_config('assignsubmission_mailsimulator')->maxweight;
        //$maxbytesdefault = get_config('assignsubmission_mailsimulator')->maxbytes;

        $filesubmissionsdefault = $this->get_config('filesubmissions');
        $mailnumberdefault = $this->get_config('mailnumber');
        $maxweightdefault = $this->get_config('maxweight');
        $maxbytesdefault = $this->get_config('maxbytes');
        $teacherdefault = $this->get_config('teacherid');

        $mform->setDefault('assignsubmission_file_enabled', 0);
        $mform->setDefault('assignsubmission_blog_enabled', 0);
        $mform->setDefault('assignsubmission_online_enabled', 0);
        $mform->disabledIf('assignsubmission_file_enabled', 'assignsubmission_mailsimulator_enabled', 'eq', 1);
        $mform->disabledIf('assignsubmission_blog_enabled', 'assignsubmission_mailsimulator_enabled', 'eq', 1);
        $mform->disabledIf('assignsubmission_onlinetext_enabled', 'assignsubmission_mailsimulator_enabled', 'eq', 1);
        $mform->setDefault('submissiondrafts', 1);
        $mform->disabledIf('submissiondrafts', 'assignsubmission_mailsimulator_enabled', 'eq', 1);
        $mform->setDefault('teamsubmission', 0);
        $mform->disabledIf('teamsubmission', 'assignsubmission_mailsimulator_enabled', 'eq', 1);  


        // Attachments enabled/disabled
        $mform->addElement('select', 'assignsubmission_mailsimulator_filesubmissions', get_string('filesubmissions', 'assignsubmission_mailsimulator'), array(0=>'No', 1=>'Yes'));
        $mform->setDefault('assignsubmission_mailsimulator_filesubmissions', $filesubmissionsdefault);
        $mform->addHelpButton('assignsubmission_mailsimulator_filesubmissions', 'filesubmissions', 'assignsubmission_mailsimulator');
        $mform->disabledIf('assignsubmission_mailsimulator_filesubmissions', 'assignsubmission_mailsimulator_enabled', 'eq', 0);

        // Max weight per mail
        $maxweightoptions = array();
        for ($i=1; $i <= ASSIGNSUBMISSION_MAILSIMULATOR_MAXWEIGHT; $i++) {
            $maxweightoptions[$i] = $i;
        }
        $mform->addElement('select', 'assignsubmission_mailsimulator_maxweight', get_string('maxweight', 'assignsubmission_mailsimulator'), $maxweightoptions);
        $mform->setDefault('assignsubmission_mailsimulator_maxweight', $maxweightdefault);
        $mform->addHelpButton('assignsubmission_mailsimulator_maxweight', 'maxweight', 'assignsubmission_mailsimulator');
        $mform->disabledIf('assignsubmission_mailsimulator_maxweight', 'assignsubmission_mailsimulator_enabled', 'eq', 0);

        // Number of mails for this assignment
        $mailsoptions = array();
        for ($i=1; $i <= ASSIGNSUBMISSION_MAILSIMULATOR_MAXMAILS; $i++) {
            $mailsoptions[$i] = $i;
        }

        $mform->addElement('select', 'assignsubmission_mailsimulator_mailnumber', get_string('defaultnumbermails', 'assignsubmission_mailsimulator'), $mailsoptions);
        $mform->setDefault('assignsubmission_mailsimulator_mailnumber', $mailnumberdefault);
        $mform->addHelpButton('assignsubmission_mailsimulator_mailnumber', 'defaultnumbermails', 'assignsubmission_mailsimulator');
        $mform->disabledIf('assignsubmission_mailsimulator_mailnumber', 'assignsubmission_mailsimulator_enabled', 'eq', 0);

        //Max attachment size
        if (isset($CFG->maxbytes)) {
            $mform->addElement('select', 'assignsubmission_mailsimulator_maxbytes', get_string('maxattachments', 'assignsubmission_mailsimulator'), get_max_upload_sizes($CFG->maxbytes));
            $mform->setDefault('assignsubmission_mailsimulator_maxbytes', $maxbytesdefault);
            $mform->addHelpButton('assignsubmission_mailsimulator_maxbytes', 'maxattachments', 'assignsubmission_mailsimulator');
            $mform->disabledIf('assignsubmission_mailsimulator_maxbytes', 'assignsubmission_mailsimulator_enabled', 'eq', 0);
        }

        //Teacher mail
        $sql = 'SELECT distinct u.id AS uid, u.firstname, u.lastname, u.email ' .
            'FROM {course} as c, {role_assignments} AS ra, {user} AS u, {context} AS ct ' .
            'WHERE c.id = ct.instanceid AND ra.roleid =3 AND ra.userid = u.id ' .
            #       'AND ct.id = ra.contextid '.
            'AND c.id = ' . $COURSE->id;
        $records = $DB->get_records_sql($sql);
        if (!$records) {
            //error('This course does not have any teachers.');
        }

        foreach ($records as $teacher) {
            $teachers[$teacher->uid] = $teacher->firstname . ' ' . $teacher->lastname . ' &lt;' . $teacher->email . '&gt;';
        }

        $mform->addElement('select', 'assignsubmission_mailsimulator_teacherid', get_string('teacherid', 'assignsubmission_mailsimulator'), $teachers);
        $mform->setDefault('assignsubmission_mailsimulator_teacherid', $teacherdefault);
        $mform->addHelpButton('assignsubmission_mailsimulator_teacherid', 'teacherid', 'assignsubmission_mailsimulator');
        $mform->disabledIf('assignsubmission_mailsimulator_teacherid', 'assignsubmission_mailsimulator_enabled', 'eq', 0);

        //$mailadminlink = html_writer::link(new moodle_url('/mod/assign/submission/mailsimulator/mailadmin.php', array('id'=>$cmid)), 
        //    get_string('mailadmin','assignsubmission_mailsimulator'), array('target' => '_blank'));
        
        //$mform->addElement('static', 'assignsubmission_mailsimulator_mailadmin', '', $mailadminlink); 
        //$mform->disabledIf('assignsubmission_mailsimulator_mailadmin', 'assignsubmission_mailsimulator_enabled', 'eq', 0);
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
     * Here the submission is to be displayed.
     * 
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        $cmid = required_param('id', PARAM_INT);
        $mailboxurl = new moodle_url('/mod/assign/submission/mailsimulator/mailbox.php', array("id"=>$cmid));
        redirect($mailboxurl);
        return true;
    }


    /**
     * Displays all submitted items for this assignment from a specified student.
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        global $CFG, $DB, $OUTPUT;

        $id         = required_param('id', PARAM_INT);          // Course Module ID
        $sid        = optional_param('sid', $submission->id, PARAM_INT);
        $gid        = optional_param('gid', 0, PARAM_INT);
        $userid     = $DB->get_field('assign_submission', 'userid', array("id" => $sid));
        $cm         = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $context    = context_module::instance($cm->id);
        require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
        $mailboxinstance = new mailbox($context, $cm, $course);

        ob_start();
        if ($submission) {
            $mailboxinstance->view_grading_feedback($userid);
        } else {
            error("User doesn't have any active submission");
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
        $cmid = required_param('id', PARAM_INT);
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);

        //$divclass = 'submissionstatusdraft';
        $userid = $submission->userid+0;
        $mailssent = $DB->count_records('assignsubmission_mail_mail', array('userid' => $userid));
    
        $sql = 'SELECT sm.id, sm.mailid, t.weight, sm.gainedweight, sm.feedback, m.sender, m.subject, m.message, t.correctiontemplate, m.timesent, m.priority, m.attachment, m.userid
        FROM {assignsubmission_mail_sgndml} AS sm
        LEFT JOIN {assignsubmission_mail_tmplt} AS t ON sm.mailid = t.mailid
        LEFT JOIN {assignsubmission_mail_mail} AS m ON m.id = sm.mailid
        WHERE sm.userid = ' . $userid . '
        AND m.assignment = ' . $cm->instance;

        $usermails = $DB->get_records_sql($sql);

        foreach ($usermails as $mailobj) {
            $maxweight += $mailobj->weight;
            $weightgiven += ( $mailobj->gainedweight * $mailobj->weight);
        }

        $result = html_writer::start_tag('div', array('class' => $divclass));
        $result .= 'Mails sent: '. $mailssent .' <br> Weight given: ' . $weightgiven;
        $result .= html_writer::end_tag('div');

        return $result;

    }

    /**
     * Produce a list of files suitable for export that represents this submission
     * 
     * @param stdClass $submission
     * @return array an array of files indexed by filename
     */
    public function get_files(stdClass $submission) {
        global $DB, $CFG;
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
