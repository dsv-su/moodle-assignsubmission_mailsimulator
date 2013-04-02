<?php

require_once(dirname(__FILE__).'/../../../../config.php');

global $CFG, $DB, $PAGE;

$id = required_param('id', PARAM_INT);          // Course Module ID
$userid = required_param('userid', PARAM_INT);  // User ID

$cm     = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course);

$PAGE->set_title('Submission');

/*
if (!$cm = get_coursemodule_from_id('assignment', $id)) {
    error("Course Module ID was incorrect");
}

if (!$assignment = get_record("assignment", "id", $cm->instance)) {
    error("Assignment ID was incorrect");
}

if (!$course = get_record("course", "id", $assignment->course)) {
    error("Course is misconfigured");
}

if (!$user = get_record("user", "id", $userid)) {
    error("User is misconfigured");
}
*/

$teacher = has_capability('mod/assign:grade', $context);

/*
if (!$teacher && ($USER->id != $user->id)) {
    error("You can not view this assignment");
}

if ($assignment->assignmenttype != 'mailsimulator') {
    error("Incorrect assignment type");
}
*/

echo $OUTPUT->header();

require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');
$mailboxinstance = new mailbox($context, $cm, $course);
$assignmentinstanceid = $cm->instance + 0;
$submission = $mailboxinstance->user_have_registered_submission($userid, $assignmentinstanceid);

if ($submission) {
    $mailboxinstance->view_grading_feedback($userid);
}

/*
$assignmentinstance = new assignment_mailsimulator($cm->id, $assignment, $cm, $course);
print_header(fullname($user, true) . ': ' . $assignmentinstance->assignment->name);
$assignmentinstance->view_grading_feedback($userid);
close_window_button();
print_footer('none');
*/

echo html_writer::tag('div', html_writer::link('../../view.php?id=' . $cm->id . '&action=grading', 'Back to grading page'), array('align'=>'center'));

echo $OUTPUT->footer();
