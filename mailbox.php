<?php

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG, $DB, $COURSE, $PAGE;
require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/mailbox_class.php');

$cmid   = required_param('id', PARAM_INT);
$cm     = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course);

$PAGE->set_url('/mod/assign/submission/mailsimulator/mailbox.php', array('id' => $cmid));
$PAGE->set_title('Mailbox');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

$context = context_module::instance($cm->id);

require_capability('mod/assign:view', $context);

$mailbox = new mailbox($context, $cm, $course);
$mailbox->view();

echo $OUTPUT->footer();
