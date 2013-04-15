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

$settings->add(new admin_setting_configcheckbox('assignsubmission_mailsimulator/default',
        new lang_string('default', 'assignsubmission_mailsimulator'),
        new lang_string('default_help', 'assignsubmission_mailsimulator'), 0));

if (isset($CFG->maxbytes)) {
    $settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/maxbytes',
                        get_string('maxattachments', 'assignsubmission_mailsimulator'),
                        get_string('maxattachments_help', 'assignsubmission_mailsimulator'), 1048576,
                        get_max_upload_sizes($CFG->maxbytes)));
}

$settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/filesubmissions',
                        get_string('filesubmissions', 'assignsubmission_mailsimulator'),
                        get_string('filesubmissions_help', 'assignsubmission_mailsimulator'), 1, array(0=>'No', 1=>'Yes')));

$maxweight = array();
for ($i=1; $i <= ASSIGNSUBMISSION_MAILSIMULATOR_MAXWEIGHT; $i++) {
    $maxweight[$i] = $i;
}
$settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/maxweight',
                                               get_string('maxweight', 'assignsubmission_mailsimulator'),
                                               get_string('maxweight_help', 'assignsubmission_mailsimulator'), 5, $maxweight));

$maxfiles = array();
for ($i=1; $i <= ASSIGNSUBMISSION_MAILSIMULATOR_MAXMAILS; $i++) {
    $maxfiles[$i] = $i;
}
$settings->add(new admin_setting_configselect('assignsubmission_mailsimulator/mailnumber',
                                               get_string('defaultnumbermails', 'assignsubmission_mailsimulator'),
                                               get_string('defaultnumbermails_help', 'assignsubmission_mailsimulator'), 8,
                                               $maxfiles));
