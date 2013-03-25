<?php

defined('MOODLE_INTERNAL') || die();

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
    $itemid=29;
/*
    require_login($course, false, $cm);
    $itemid = (int)array_shift($args);
    $record = $DB->get_record('assign_submission',
                              array('id'=>$itemid),
                              'userid, assignment, groupid',
                              MUST_EXIST);
    $userid = $record->userid;
    $groupid = $record->groupid;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
*/
  //  $assign = new assign($context, $cm, $course);
/*
    if ($assign->get_instance()->id != $record->assignment) {
        return false;
    }

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
   // var_dump($relativepath);

    $fullpath = "/{$context->id}/assignsubmission_mailsimulator/$filearea/$relativepath";
//var_dump($fullpath);
    $fs = get_file_storage();
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true); // download MUST be forced - security!
}
