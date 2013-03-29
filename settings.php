<?php
/**
 * This file defines the admin settings for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *        Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/lib.php');

if (isset($CFG->maxbytes)) {
    $settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/maxbytes',
                        get_string('maxattachments', 'assignsubmission_mailsimulator'),
                        get_string('maxattachments_help', 'assignsubmission_mailsimulator'), 1048576, get_max_upload_sizes($CFG->maxbytes)));
}

$settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/filesubmissions',
                        get_string('filesubmissions', 'assignsubmission_mailsimulator'),
                        get_string('filesubmissions_help', 'assignsubmission_mailsimulator'), 1, array(0=>'No', 1=>'Yes')));

$maxweight = array();
for ($i=1; $i <= 10; $i++) {
    $maxweight[$i] = $i;
}
$settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/maxweight',
                                               get_string('maxweight', 'assignsubmission_mailsimulator'),
                                               get_string('maxweight_help', 'assignsubmission_mailsimulator'), 5, $maxweight));

$maxfiles = array();
for ($i=1; $i <= ASSIGNSUBMISSION_MAILSIMULATOR_MAXFILES; $i++) {
    $maxfiles[$i] = $i;
}
$settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/mailnumber',
                                               get_string('defaultnumbermails', 'assignsubmission_mailsimulator'),
                                               get_string('defaultnumbermails_help', 'assignsubmission_mailsimulator'), 8, $maxfiles));
