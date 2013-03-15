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

        $teacher = has_capability('mod/assign:grade', $this->context);

        if ($teacher) {
            $this->print_tabs('mail');
            $this->view_assignment_mails();
        } else {
            if ($existingsubmission->status<>'submitted') {
                $this->view_mailbox();
                $this->update_user_submission($USER->id);
            } else {
                echo $OUTPUT->notification('You cannot view the mailbox since you have already sent this assignment for grading');
                echo html_writer::empty_tag('br');
            }
            echo html_writer::tag('div', html_writer::link('../../view.php?id=' . $this->cm->id, 'Back to the assignment start page'), array('align'=>'center'));
        }

    }

    function view_assignment_mails($trash=false) {
        global $DB;

        $deletestatus = ($trash ? 1 : 0);

        $sql = 'SELECT t.id AS tid, t.mailid AS id, t.randgroup, t.weight, t.correctiontemplate, t.deleted, m.priority, m.sender, m.userid, m.subject, m.message, m.timesent, m.parent, m.attachment
                FROM {assignsubmission_mail_tmplt} AS t
                LEFT JOIN {assignsubmission_mail_mail} AS m ON m.id = t.mailid
                WHERE m.assignment = ' . $this->cm->instance . '
                AND m.parent = 0
                AND m.userid = 0
                AND t.deleted = ' . $deletestatus . '
                ORDER BY t.randgroup';

        if (!$templatemailarr = $DB->get_records_sql($sql)) {
            return;
        }

        $group = 0;

        foreach ($templatemailarr as $mailobj) {
            $tid = $mailobj->id;
            $groupid = $DB->get_field('assignsubmission_mail_tmplt', 'randgroup', array('mailid' => $mailobj->id));

            if ($groupid == 0) {
                $this->add_parent($mailobj->id, $this->calculate_group());
            }


$editingteacher=false;


            if ($mailobj->randgroup != $group) {
                if ($group != 0) {
                    echo '</div><br />';
                }
                echo '<div style="border:1px; border-style:solid; width:90%; margin:auto; background-color:#ffffff">';

                echo '<table border="0" width="100%" style="background:gray; color:white;">';
                echo '  <tr>';
                echo '      <td style="padding:5px"> '.get_string('mail', 'assignsubmission_mailsimulator').' ' . $mailobj->randgroup . ' </td>';
                echo '      <td style="padding:5px; text-align:right">';
                echo '          <table align="right">';
                echo '              <tr>';
                echo '                  <td style="padding:0; margin:0;">';
                if($editingteacher) {
                    print_single_button($CFG->pagepath, array('id' => $this->cm->id, 'add' => 1, 'pid' => 0, 'gid' => $mailobj->randgroup), get_string('addmailalt', 'assignsubmission_mailsimulator'));
                }
                echo '                  </td>';
                echo '                  <td style="padding:0; margin:0;">';
                if($editingteacher) {
                    helpbutton('addalternativemail', get_string('addmailalt', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
                }
                echo '                  </td>';
                echo '              </tr>';
                echo '          </table>';
                echo '      </td>';
                echo '  </tr>';
                echo '</table>';
            }

            $replyobject = $this->get_nested_reply_object($mailobj, $editingteacher);

            if ($replyobject) {
                $mailobj = $replyobject;
            } else {
                $mailobj->message = '<div class="mailmessage">' . ($mailobj->attachment ? $this->get_files_str($mailobj->id, 0) : '') . format_text(unserialize($mailobj->message)["text"], FORMAT_MOODLE) . '</div>';
                if($editingteacher) {
                    $mailobj->message .= '<span style="text-align:right">' . print_single_button($CFG->wwwroot . '/mod/assignment/type/mailsimulator/mail.php', array('id' => $this->cm->id, 'mid' => $mailobj->id), get_string('edit'), 'get', '_self', true) . '</span>';
                }
            }

            $p = $this->get_top_parent_id($mailobj->id);
            $from = $this->get_sender_string($mailobj, true);
            //$firsttoname = $this->get_recipients_string($mailobj->id);
            $prio = '<span style="color:darkred">' . $this->get_prio_string($mailobj->priority) . '</span>';

            echo '<table class="allmailheader">';
            echo '  <tr>';
            echo '      <td style="width:100px;">' . get_string('subject', 'assignsubmission_mailsimulator') . ': </td>';
            echo '      <td colspan="5" style="background:white;border:1px;border-style:solid"><strong>' . $prio . format_text($mailobj->subject, 1) . '</strong></td>';
            echo '  </tr>';
            echo '  <tr>';
            echo '      <td>' . get_string('from') . ': </td>';
            echo '      <td colspan="5">' . $from . '</td>';
            echo '  </tr>';
            echo '  <tr>';
            echo '      <td>' . get_string('to') . ': </td>';
            echo '      <td colspan="5">' . $firsttoname . '</td>';
            echo '  </tr>';
            echo '  <tr>';
            echo '      <td>' . get_string('date') . ': </td>';
            echo '      <td colspan="5">' . date('Y-m-d H:i', $mailobj->timesent) . '</td>';
            echo '  </tr>';
            echo '  <tr>';
            echo '      <td>';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                print_single_button($CFG->pagepath, array('id' => $this->cm->id, 'add' => 1, 're' => 1, 'pid' => $mailobj->id), get_string('reply', 'assignment_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                helpbutton('reply', get_string('reply', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            }
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                print_single_button($CFG->pagepath, array('id' => $this->cm->id, 'add' => 1, 're' => 2, 'pid' => $mailobj->id), get_string('replyall', 'assignment_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                helpbutton('reply', get_string('reply', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            }
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                print_single_button($CFG->wwwroot . '/mod/assignment/type/mailsimulator/parent.php', array('id' => $this->cm->id, 'mid' => $p, 'gid' => $mailobj->randgroup), get_string('updatecorrectiontemplate', 'assignment_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
           # helpbutton('updatecorrectiontemplate', get_string('updatecorrectiontemplate', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                print_single_button($CFG->wwwroot . '/mod/assignment/view.php', array('id' => $this->cm->id, 'mid' => $pid, 'delete' => 1), get_string('delete'), 'get', '_self', false, '', $this->get_signed_out_status($pid), get_string('confirmdelete', 'assignment_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                helpbutton('delete', get_string('delete'), 'assignment/type/mailsimulator/');
            }
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                if ($trash) {
                    print_single_button($CFG->wwwroot . '/mod/assignment/view.php', array('id' => $this->cm->id, 'mid' => $pid, 'delete' => 3), get_string('restore'));
                } else {
                    print_single_button($CFG->wwwroot . '/mod/assignment/view.php', array('id' => $this->cm->id, 'mid' => $pid, 'delete' => 2), get_string('trash', 'assignment_mailsimulator'));
                }
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                helpbutton('trashrestore', get_string('trashrestore', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            }
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td rowspan="3">';
            echo '          <table align="right">';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            echo get_string('weight', 'assignsubmission_mailsimulator') . ': ' . $mailobj->weight;
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;" >';
            //helpbutton('weight', get_string('weight', 'assignment_mailsimulator'), 'assignment/type/mailsimulator/');
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '  </tr>';
            echo '</table>';

            echo '<div>' . format_text($mailobj->message, FORMAT_MOODLE) . '</div>';
            //echo '<div>' . $mailobj->message . '</div>';
            echo '<div style="padding: 5px; color:green; background: white">' . format_text($mailobj->correctiontemplate, FORMAT_MOODLE) . '</div>';
            echo '<br />';

            $group = $mailobj->randgroup;
        }
        echo '</div>';

    }

    function get_top_parent_id($mailid) {
        global $DB;

        $parentid = $mailid;

        do {
            $mailid = $parentid;
        } while ($parentid = $DB->get_field('assignsubmission_mail_mail', 'parent', array('id' => $mailid)));

        return $mailid;
    }

    function get_prio_string($prionumb) {

        switch ($prionumb) {
            case 1:
                $prio = '! ';
                break;
            case 2:
                $prio = '!! ';
                break;
            default:
                $prio = ' ';
                break;
        }

        return $prio;
    }

    function add_parent($mailid=0, $gid=0) {
        global $CFG;

        if ($mailid) {
            redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/template.php?id=' . $this->cm->id . '&mid=' . $mailid . '&gid=' . $gid, 'ADD PARENT ', 0);
        }
    }

    function calculate_group() {
        global $CFG, $DB;

        $sql = 'SELECT DISTINCT tm.randgroup
                FROM ' . $CFG->prefix . 'assignsubmission_mail_tmplt AS tm
                LEFT JOIN ' . $CFG->prefix . 'assignsubmission_mail_mail AS m ON m.id = tm.mailid
                WHERE m.assignment = ' . $this->cm->instance . '
                AND tm.randgroup != 0';

        $grouparr = $DB->get_fieldset_sql($sql);
        $group = 1;

        if ($grouparr) {
            sort($grouparr);

            for ($i = 0; $i < count($grouparr); $i++) {
                if ($grouparr[$i] != $group) {
                    break;
                }
                $group++;
            }
        }

        return $group;
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

    // Tabs for the header
    function print_tabs($current='mail') {
        global $CFG;

        $route = optional_param('route', 0, PARAM_INT);

        $tabs = array();
        $row = array();

        $row[] = new tabobject('mail', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $this->cm->id, get_string('mailbox', 'assignsubmission_mailsimulator'));
        $row[] = new tabobject('addmail', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php?id=' . $this->cm->id, get_string('addmail', 'assignsubmission_mailsimulator'));
        $row[] = new tabobject('addcontacts', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/contacts.php?id=' . $this->cm->id, get_string('addcontacts', 'assignsubmission_mailsimulator'));
    
        $tabs[] = $row;

        print_tabs($tabs, $current);
    }

    function delete_contact($contactid) {
        global $CFG, $DB, $OUTPUT;

        // Check if contact is in use.
        if (!$active = $DB->record_exists('assignsubmission_mail_to', array('contactid' => $contactid))) {
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

        $teacher = has_capability('mod/assign:grade', context_module::instance($this->cm->id));

        $mail = new stdClass;
        $mail->userid = 0;              // 0 = assignment mail
        $mail->teacher = $teacher;

        if (!$teacher) {
            $mail->userid = $USER->id;  // !0 = student mail
        }
        $mail->mailid = 0;
        $mail->parent = $parent;        // 0 = new mail, 1 = reply
        $mail->assignment = (integer) $this->cm->instance;
        $mail->priority = $priority;
        $mail->sender = $from;
        $mail->subject = '';
        $mail->message = '';
        $mail->timesent = '';

        $contacts = $DB->get_records('assignsubmission_mail_cntct', array('assignment' => $this->cm->instance));

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

    function prepare_parent($mailid=0, $group=0) {
        global $DB;        

        $templatemail = new stdClass;
        //$parentmail->maxweight = $DB->get_field('assignment', 'var2', 'id', $this->assignment->id);
        $templatemail->maxweight = 5; // TEMP VALUE!!!
        $templatemail->id = 0;

        if ($mailid) {
            $id = $DB->get_field('assignsubmission_mail_tmplt', 'id', array('mailid' => $mailid));
            if ($id) {
                $templatemail->id = $id;
            }
        }
        $templatemail->mailid = $mailid;
        $templatemail->randgroup = $group;

        // If assignment parent
        if (has_capability('mod/assign:grade', context_module::instance($this->cm->id)) && !$group) {
            //$templatemail->randgroup = $this->calculate_group();
        }

        $templatemail->weight = 0;
        $templatemail->correctiontemplate = '';
        $templatemail->deleted = 0;

        return $templatemail;
    }

    function get_nested_reply_object($mailobj, $editbuttons=false) {
        global $CFG, $DB;

        $replies = false;

        do {
            $replies[] = $mailobj;
        } while ($mailobj = $DB->get_record('assignsubmission_mail_mail', array('parent' => $mailobj->id, 'userid' => 0)));

        if (!$replies || count($replies) <= 1) {
            return false;
        }

        $replies = array_reverse($replies);
        $replystr = '';
        $divcount = 0;
        $attachment = 0;

        foreach ($replies as $m) {
            if ($divcount == 0) {
                $replystr .= '<div class="mailmessage">';
                $mailobj->id = $m->id;
                $mailobj->subject = $m->subject;
                $mailobj->timesent = $m->timesent;
                $mailobj->priority = $m->priority;
                $mailobj->sender = $m->sender;
            } else {
                $from = $this->get_sender_string($m);
                $replystr .= date('j F Y, H.i', $m->timesent) . ' ' . get_string('wrote', 'assignsubmission_mailsimulator', $from) . 
                ':<br /><div style="border-left: 2px outset #000000; padding: 5px">';
            }

            //$replystr .= ( $m->attachment ? $this->get_files_str($m->id, $m->userid) : '') . format_text($m->message);
            $replystr .= format_text(unserialize($m->message)['text']);

            if ($editbuttons) {
                $replystr .= '<span style="text-align:right">' . print_single_button($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php', array('id' => $this->cm->id, 'mid' => $m->id), get_string('edit'), 'get', '_self', true) . '</span>';
            }
            if ($m->attachment == 1) {
                $attachment = 1;
            }

            $mailobj->attachment = $attachment;
            $divcount++;
        }
        for ($i = 0; $i < count($replys); $i++) {
            $replystr .= '</div>';
        }

        $mailobj->message = $replystr;
        if (isset($m->pid))
            $mailobj->pid = $m->pid;
        if (isset($m->weight))
            $mailobj->weight = $m->weight;
        if (isset($m->correctiontemplate))
            $mailobj->correctiontemplate = $m->correctiontemplate;
        if (isset($m->randgroup))
            $mailobj->randgroup = $m->randgroup;

        return $mailobj;
    }

    function get_sender_string($mailobject, $long=false) {
        global $USER, $DB;

        if ($mailobject->sender == 0 && isset($mailobject->userid) && $mailobject->userid == 0) {
            //$teacherid = $DB->get_field('assignment', 'var3', 'id', $this->assignment->id);
            //$fromobj = get_record_select('user', 'id=' . $teacherid, 'firstname, lastname, email');
        } elseif ($mailobject->sender == 0) {
            $fromobj = $DB->get_record_select('user', 'id=: id', array("id" => $USER->id), 'firstname, lastname, email');
        } else {
            $fromobj = $DB->get_record('assignsubmission_mail_cntct', array('id' => $mailobject->sender));
        }

        if ($fromobj) {
            $from = $fromobj->firstname . ' ' . $fromobj->lastname . ($long ? ' &lt;' . $fromobj->email . '&gt;' : '');
        } else {
            $from = $mailobject->sender;
        }

        return $from;
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

                $DB->insert_record('assignsubmission_mail_to', $obj);
            }
            /*
            if ($this->upload_attachment($mailid, $mail->userid)) {

                $fileobj = new stdClass();
                $fileobj->id = $mailid;
                $fileobj->attachment = 1;

                $DB->update_record('assignsubmission_mail_mail', $fileobj);
            }
            */
            if ($mail->parent == 0) {
              //  $this->add_parent($mailid, $gid);
            } else {
                if (!has_capability('mod/assign:grade', context_module::instance($this->cm->id))) {

                    //$obj = $this->get_mail_status($mailid);
                    //$this->set_mail_status($obj->mailid, 2);
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
