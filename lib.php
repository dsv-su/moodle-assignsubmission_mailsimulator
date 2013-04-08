<?php

defined('MOODLE_INTERNAL') || die();

define('ASSIGNSUBMISSION_MAILSIMULATOR_MAXFILES', 10);

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
/*
    if ($assign->get_instance()->teamsubmission &&
        !$assign->can_view_group_submission($groupid)) {
        return false;
    }

    if (!$assign->get_instance()->teamsubmission &&
        !$assign->can_view_submission($userid)) {
        return false;
    }
*/
    $relativepath = implode('/', $args);

    $fullpath = "/{$context->id}/assignsubmission_mailsimulator/$filearea/$itemid/$relativepath";
    $fs = get_file_storage();

    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true); // download MUST be forced - security!
}

/*
function assign_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
       $link = new moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
       $node = $navref->add(get_string('viewgradebook', 'assign'), $link, navigation_node::TYPE_SETTING);  
}*/
