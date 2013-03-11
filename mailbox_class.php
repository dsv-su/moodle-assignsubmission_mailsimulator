<?php

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

DEFINE('TO_STUDENT_ID', 9999999); // To identify when a mail is sent to a student

class mailbox {

    private $url;
    private $cmid;
    private $cm;
    private $context;
    private $course;

    public function __construct($context, $cm, $course) {
        $this->context  = $context;
        $this->cm       = $cm;
        $this->course   = $course;

        /*
        $this->context; // Is a course context
        context_module::instance($this->cm->id)); // Is a module context
        We have to check the latter.
        */
    }
    
    function view() {
        global $OUTPUT, $USER, $DB;

        $existingsubmission = $this->user_have_registered_submission($USER->id, $this->cm->instance);

        if ($existingsubmission->status<>'submitted') {
            $this->view_mailbox();
            $this->update_user_submission($USER->id);
        } else {
            echo $OUTPUT->notification('You cannot view the mailbox since you have already sent this assignment for grading');
            echo html_writer::empty_tag('br');
        }

        echo html_writer::tag('div', html_writer::link('../../view.php?id=' . $this->cm->id, 'Back to the assignment start page'), array('align'=>'center'));

    }

    /**
     * Check if a user have a registered submission to an assignment.
     *
     * @param mixed $userid
     * @param mixed $assignment_instance
     * @return mixed False if no submission, else the submission record.
     */
    function user_have_registered_submission($userid, $assignment_instance) {
        global $DB;

        $submission = $DB->get_record('assign_submission', array(
            'assignment' => $assignment_instance,
            'userid' => $userid
        ));

        return $submission;
    }

    function update_user_submission($userid) {
        global $DB;

        $existingsubmission = $this->user_have_registered_submission($userid, $this->cm->instance);

        $assign = new assign($this->context, $this->cm, null);
        $submission = $assign->get_user_submission($userid, true);        

        if ($existingsubmission) {
            $submission->timemodified = time();
            $DB->update_record('assign_submission', $submission);
        } 
    }

    function delete_contact($contactid) {
        global $CFG, $DB, $OUTPUT;

        // Check if contact is in use.
        if (!$active = $DB->record_exists('assignsubmission_mail_mail', array('receiver' => $contactid))) {
            $active = $DB->record_exists('assignsubmission_mail_mail', array('userid' => 0, 'sender' => $contactid));
        }

        if ($active) {
            $contact = $DB->get_record('assignsubmission_mail_cntct', array('id' => $contactid)); 
            $msg = get_string('contactinuse', 'assignsubmission_mailsimulator');
            $msg .= '<br />' . $contact->firstname . ' ' . $contact->lastname . ' &lt;' . $contact->email . '&gt<br />';
            echo $OUTPUT->error_text($msg);
        } else {
            $DB->delete_records('assignsubmission_mail_cntct', array('id' => $contactid));
        } 
    }

    function prepare_mail($parent=0, $from=0, $priority=0) {
        global $USER, $CFG, $DB;

        $teacher = has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id));

        $mail = new stdClass;
        $mail->userid = 0;              // 0 = assignment mail
        $mail->teacher = $teacher;

        if (!$teacher) {
            $mail->userid = $USER->id;  // !0 = student mail
        }
        $mail->mailid = 0;
        $mail->parent = $parent;        // 0 = new mail, 1 = reply
        $mail->assignment = (integer) $this->cm->id;
        $mail->priority = $priority;
        $mail->sender = $from;
        $mail->subject = '';
        $mail->message = '';
        $mail->timesent = '';

        $contacts = $DB->get_records('assignsubmission_mail_cntct', array('assignment' => $this->cm->id));

        if ($contacts) {
            foreach ($contacts as $key => $con) {
                $contacts[$key] = $con->firstname . ' ' . $con->lastname . ' &lt;' . $con->email . '&gt;';
            }
        }
        /* 

        Teacher used to be retrieved from Assignment entry. It has to be changed.

        $teacherid = $DB->get_field('assignment', 'var3', 'id', $this->assignment->id);
        $teacherobj = $DB->get_record_select('user', 'id=' . $teacherid, 'firstname, lastname, email');
        */

        $studentobj = $DB->get_record_select('user', 'id = :id', array('id'=> $USER->id), 'firstname, lastname, email');
        
        //$contacts[0] = $teacherobj->firstname . ' ' . $teacherobj->lastname . ' &lt;' . $teacherobj->email . '&gt;';

        if ($teacher) {
            $contacts[TO_STUDENT_ID] = get_string('mailtostudent', 'assignsubmission_mailsimulator');
        } else {
            $contacts[TO_STUDENT_ID] = $studentobj->firstname . ' ' . $studentobj->lastname . ' &lt;' . $studentobj->email . '&gt;';
        }

        asort($contacts);

        $mail->to = $contacts;

        return $mail;
    }

    // Creates a new mail and returns the id or false
    function insert_mail($mail, $gid=0) {
        global $CFG, $USER, $DB;

        $mailid = $DB->insert_record('assignsubmission_mail_mail', $mail);

        if ($mailid) {
            foreach ($mail->to as $to) {
                $obj = new stdClass();
                $obj->contactid = $to;
                $obj->mailid = $mailid;

                insert_record('assignment_mailsimulation_to', $obj);
            }

            if ($this->upload_attachment($mailid, $mail->userid)) {

                $fileobj = new stdClass();
                $fileobj->id = $mailid;
                $fileobj->attachment = 1;

                update_record('assignment_mailsimulation_mail', $fileobj);
            }

            if ($mail->parent == 0) {
                $this->add_parent($mailid, $gid);
            } else {
                if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {

                    $obj = $this->get_mail_status($mailid);
                    $this->set_mail_status($obj->mailid, 2);
                }
            }

            return $mailid;
        }

        return false;
    }

    function view_mailbox() {
        global $CFG, $USER;

        $route = optional_param('route', 0, PARAM_INT);

        if ($route==1) {
            $titlestr = "Sent";

        } else {
            $titlestr = "Inbox";

        }

        $imgurl = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/';
        $mailcontent = '&nbsp;';
        $mailheaders = '';

        // Mailbox printout
        ##########################
        echo '<div class="mailboxwrapper">';
        echo '  <!-- Start Window Top Table -->
                <table border="0" width="100%"  style="margin-bottom: -4px;">
                        <tr>
                                <td width="32px"><img src="' . $imgurl . 'shadow-top-left.png"></td>
                                <td width="8"><img src="' . $imgurl . 'window-top-left.png"></td>
                                <td class="window-top-bg">
                                <div class="mailtoptitle">' . $titlestr . '</div>';
        echo $this->topbar();

        echo '              </td>
                                <td width="8"><img src="' . $imgurl . 'window-top-right.png"></td>
                                <td width="32px"><img src="' . $imgurl . 'shadow-top-right.png"></td>
                        </tr>
                </table>
                <!-- End Window Top Table -->

                <!-- Start Window Content Table -->
                <table border="0"  width="100%">
                        <tr>
                                <td width="32px" class="shadow-left-bg"></td>
                                <td >
                                        <table class="mailmidletable"  width="100%">
                                                <tr>
                                                        <td class="mailboxes">' . $this->sidebar() . '</td>
                                                        <td class="mailheaders"><div class="scroll">' . $mailheaders . '</div></td>
                                                        <td>' . $mailcontent . '</td>
                                                </tr>
                                        </table>
                                </td>
                                <td width="32px" class="shadow-right-bg"></td>
                        </tr>
                </table>
                <!-- End Window Content Table -->';

        echo '  <!-- Start Bottom Shadow Table -->
                <table border="0"  width="100%">
                    <tr>
            <td width="32px"><img src="' . $imgurl . 'shadow-bottom-left.png"></td>
            <td>
                            <table border="0"  width="100%">
                                <tr>
                                    <td width="32px"><img src="' . $imgurl . 'shadow-bottom-center-left.png"></td>
                                    <td class="shadow-bottom-bg">&nbsp;</td>
                                    <td width="32px"><img src="' . $imgurl . 'shadow-bottom-center-right.png"></td>
                </tr>
                            </table>
            </td>
                        <td width="32px"><img src="' . $imgurl . 'shadow-bottom-right.png"></td>
                    </tr>
                </table>
                <!-- End Bottom Shadow Table -->
        </div>';
        ###########################
    }

    function sidebar() {
        global $CFG;

        $route = optional_param('route', 0, PARAM_INT);
        $imgurl = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/';

        $sidebarstr = '<div class="mailboxheader">' . get_string('mailboxes', 'assignsubmission_mailsimulator') . '</div>';
        $sidebarstr .= '<div class="' . ($route == 0 ? 'mailboxselect' : 'mailbox') . '"><img src="' . $imgurl . 'inbox.png"><a href="' . $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id='.$this->cm->id.'&route=0">' . get_string('inbox', 'assignsubmission_mailsimulator') . '</a></div>';
        $sidebarstr .= '<div class="' . ($route == 1 ? 'mailboxselect' : 'mailbox') . '"><img src="' . $imgurl . 'sent.png"><a href="' . $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id='.$this->cm->id.'&route=1">' . get_string('sent', 'assignsubmission_mailsimulator') . '</a></div>';

        return $sidebarstr;
    }

    function topbar() {
        global $CFG;

        //$submission = $this->get_submission();
        $mid = optional_param('mid', 0, PARAM_INT);       // Mail id
        //$link = $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id . '&add=1&re=';
        $imgurl = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/';

        if ($mid) {
            $topmenu = '<!-- Start Mail Top Menu-->
                            <table border="0px" >
                                <tr>
                                    <td width="92"></td>
                                    <td >
                                        <a href="' . $link . '1&pid=' . $mid . '" title="' . get_string('reply', 'assignment_mailsimulator') . '" onmouseover="document.re.src=\'' . $imgurl . 'button-reply-down.png\'" onmouseout="document.re.src=\'' . $imgurl . 'button-reply.png\'">
                                            <img name="re" src="' . $imgurl . 'button-reply.png">
                    </a>
                                    </td>
                                    <td >
                                        <a href="' . $link . '2&pid=' . $mid . '" title="' . get_string('replyall', 'assignment_mailsimulator') . '" onmouseover="document.all.src=\'' . $imgurl . 'button-replyall-down.png\'" onmouseout="document.all.src=\'' . $imgurl . 'button-replyall.png\'">
                                            <img name="all" src="' . $imgurl . 'button-replyall.png">
                                        </a>
                                    </td>
                                    <td >
                                        <a href="' . $link . '3&pid=' . $mid . '" title="' . get_string('forward', 'assignment_mailsimulator') . '" onmouseover="document.fwd.src=\'' . $imgurl . 'button-forward-down.png\'" onmouseout="document.fwd.src=\'' . $imgurl . 'button-forward.png\'">
                                            <img name="fwd" src="' . $imgurl . 'button-forward.png">
                                        </a>
                                    </td>
                                    <td width="10">&nbsp;</td>
                                    <td >
                                        <a href="' . $link . '0&pid=0" title="' . get_string('newmail', 'assignment_mailsimulator') . '" onmouseover="document.newmail.src=\'' . $imgurl . 'button-newmail-down.png\'" onmouseout="document.newmail.src=\'' . $imgurl . 'button-newmail.png\'">
                                            <img name="newmail" src="' . $imgurl . 'button-newmail.png">
                                        </a>
                                    </td>

                                </tr>
                            </table>
                            <!-- End Mail Top Menu-->';
        } else {
            $topmenu = '<!-- Start Mail Top Menu-->
                            <table border="0px" >
                                <tr>
                                    <td width="92"></td>
                                    <td >
                                        <img name="re" src="' . $imgurl . 'button-reply-dissabled.png">
                                    </td>
                                    <td >
                                        <img name="all" src="' . $imgurl . 'button-replyall-dissabled.png">
                                    </td>
                                    <td >
                                        <img name="fwd" src="' . $imgurl . 'button-forward-dissabled.png">
                                    </td>
                                    <td width="10">&nbsp;</td>
                                    <td >
                                        <a href="' . $link . '0&pid=0" title="' . get_string('newmail') . '" onmouseover="document.newmail.src=\'' . $imgurl . 'button-newmail-down.png\'" onmouseout="document.newmail.src=\'' . $imgurl . 'button-newmail.png\'">
                                            <img name="newmail" src="' . $imgurl . 'button-newmail.png">
                                        </a>
                                    </td>

                                </tr>
                            </table>
                            <!-- End Mail Top Menu-->';
        }

        return $topmenu;
    
    }

}
