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
                        get_string('maximumsubmissionsize', 'assignsubmission_file'),
                        get_string('configmaxbytes', 'assignsubmission_file'), 1048576, get_max_upload_sizes($CFG->maxbytes)));
}

$maxfiles = array();
for ($i=1; $i <= ASSIGNSUBMISSION_MAILSIMULATOR_MAXFILES; $i++) {
    $maxfiles[$i] = $i;
}
$settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/maxfilesubmissions',
                                               get_string('defaultmaxfilessubmission', 'assignsubmission_mailsimulator'),
                                               get_string('configmaxfiles', 'assignsubmission_mailsimulator'), 8, $maxfiles));
