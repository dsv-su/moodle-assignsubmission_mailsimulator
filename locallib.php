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

        var_dump($filesubmissionsdefault);
        var_dump($mailnumberdefault);
        var_dump($maxweightdefault);
        var_dump($maxbytesdefault);
        var_dump($teacherdefault);

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
        //var_dump($this);
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

        return 'Here submission goes';

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

        $divclass = 'submissionstatussubmitted';

        $result = html_writer::start_tag('div', array('class' => $divclass));
        $result .= 'Submission summary';
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

}
