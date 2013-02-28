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
    }

    /**
     * Save the settings for the plugin
     * 
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
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
