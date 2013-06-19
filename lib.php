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
 * This file contains the event hooks for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *          Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('ASSIGNSUBMISSION_MAILSIMULATOR_MAXWEIGHT', 10);
define('ASSIGNSUBMISSION_MAILSIMULATOR_MAXMAILS', 50);

/**
 * Serves assignment submissions and other files.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - just send the file
 */
function assignsubmission_mailsimulator_pluginfile($course,
                                          $cm,
                                          context $context,
                                          $filearea,
                                          $args,
                                          $forcedownload) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);
    $itemid = (int)array_shift($args);
    $record = $DB->get_record('assignsubmission_mail_mail',
                              array('id'=>$itemid),
                              'userid, assignment',
                              MUST_EXIST);
    $userid = $record->userid;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $assign = new assign($context, $cm, $course);

    if ($assign->get_instance()->id != $record->assignment) {
        return false;
    }

    $relativepath = implode('/', $args);

    $fullpath = "/{$context->id}/assignsubmission_mailsimulator/$filearea/$itemid/$relativepath";
    $fs = get_file_storage();

    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
}

/** 
* Adds a link to navigation settings block.
*
* @param settings_navigation $settings
* @param navigation_node $navref
* @return void
*/
function assignsubmission_mailsimulator_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
    global $PAGE, $DB, $CFG;
    require_once($CFG->dirroot.'/mod/assign/locallib.php');
    $id = optional_param('id', 0, PARAM_INT);
    $cm = $PAGE->cm;
    $course  = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
    $assigninstance = new assign($context, $cm, $course);
    if (!$cm) {
        return;
    }
    
    $pluginenabled = $DB->get_field('assign_plugin_config', 'value', array(
      'plugin'=>'mailsimulator',
      'name'=>'enabled',
      'assignment'=>$cm->instance));
    if (has_capability('mod/assign:grade', $context) && $pluginenabled) {
      $link = new moodle_url('/mod/assign/submission/mailsimulator/mailbox.php', array('id' => $id));
      $node = $navref->add(get_string('mailadmin', 'assignsubmission_mailsimulator'), $link, navigation_node::TYPE_SETTING);
    }
} 
