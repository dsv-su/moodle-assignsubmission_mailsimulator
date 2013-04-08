<?php

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG, $DB, $COURSE, $PAGE;
require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');

$id   = required_param('id', PARAM_INT);
$cm     = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course);

$PAGE->set_url('/mod/assign/submission/mailsimulator/mailbox.php', array('id' => $id));
$PAGE->set_title('Mailbox');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);

echo $OUTPUT->header();

require_capability('mod/assign:view', $context);

$mailbox = new mailbox($context, $cm, $course);
$mailbox->view();

echo $OUTPUT->footer();
