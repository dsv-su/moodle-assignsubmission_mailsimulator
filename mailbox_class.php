<?php

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir . '/portfolio/caller.php');

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
        $route = optional_param('route', 0, PARAM_INT);
        $delete = optional_param('delete', 0, PARAM_INT);
        $mid = optional_param('mid', 0, PARAM_INT);
        $pid = optional_param('pid', 0, PARAM_INT);
        $teacher = has_capability('mod/assign:grade', $this->context);
        $this->check_assignment_setup();

        if ($teacher) {
            if ($delete == 1) {
                $this->delete_mail_and_children($mid);
            } elseif ($delete == 2) {
                $this->handle_trash($mid);
            } elseif ($delete == 3) {
                $this->handle_trash($mid, false);
                $route = 3;
            }

            if ($route==3) {
                $this->print_tabs('trashmail');
                $this->view_assignment_mails(true);
            } else {
                $this->print_tabs('mail');
                $this->view_assignment_mails();
            }
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

    function check_assignment_setup() {
        global $CFG, $DB;    

        // All Teacher Mail Parents must have a Parent 
        $sql = 'SELECT id
                FROM {assignsubmission_mail_mail}
                WHERE assignment = ' . $this->cm->instance . '
                AND parent = 0
                AND userid = 0 ';

        $teacherparents = $DB->get_records_sql($sql);

        foreach ($teacherparents as $mailid) {
            $id = $mailid->id;
            $parentexists = $DB->record_exists('assignsubmission_mail_tmplt', array('mailid' => $id));

            if (!$parentexists) {
                $gid=0;
                $this->add_parent($id);
            }
        }
    }

    function get_files_str($mid, $userid) {
        global $DB, $USER;
        $fs = get_file_storage();
        //$attachment += 0;
        $files = $fs->get_area_files($this->context->id, 'assignsubmission_mailsimulator', 'attachment', $mid);

        $attachments = array();
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $attach = new stdClass();
            $attach->file = $file;
            $attach->filename = $file->get_filename();
            $attach->url = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/assignsubmission_mailsimulator/attachment/'. $mid .'/'.$attach->filename);
            $attachments[] = $attach;
        }
        //var_dump($attachments);
        return $attachments;

/*
        $this->modcontext = context_module::instance($this->cm->id);
        $fs = get_file_storage();
        if ($this->post) {
            if ($this->attachment) {
                $this->set_file_and_format_data($this->attachment);
            } else {
                $attach = $fs->get_area_files($this->modcontext->id, 'mod_forum', 'attachment', $this->post->id, 'timemodified', false);
                $embed  = $fs->get_area_files($this->modcontext->id, 'mod_forum', 'post', $this->post->id, 'timemodified', false);
                $files = array_merge($attach, $embed);
                $this->set_file_and_format_data($files);
            }
            if (!empty($this->multifiles)) {
                $this->keyedfiles[$this->post->id] = $this->multifiles;
            } else if (!empty($this->singlefile)) {
                $this->keyedfiles[$this->post->id] = array($this->singlefile);
            }
*/
/*
        if (empty($this->multifiles) && !empty($this->singlefile)) {
            $this->multifiles = array($this->singlefile); // copy_files workaround
        }
        // depending on whether there are files or not, we might have to change richhtml/plainhtml
        if (empty($this->attachment)) {
            if (!empty($this->multifiles)) {
                $this->add_format(PORTFOLIO_FORMAT_RICHHTML);
            } else {
                $this->add_format(PORTFOLIO_FORMAT_PLAINHTML);
            }
        }
*/
    }

    function view_assignment_mails($trash=false) {
        global $DB, $OUTPUT, $CFG;

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

        $editingteacher = has_capability('mod/assign:grade', $this->context);

            if ($mailobj->randgroup != $group) {
                if ($group != 0) {
                    echo '</div><br />';
                }
                echo '<div style="border:1px; border-style:solid; width:90%; margin:auto; background-color:#ffffff">';

                echo '<table border="0" width="100%" style="background:gray; color:white; margin-bottom: 0;">';
                echo '  <tr>';
                echo '      <td style="padding:5px"> '.get_string('mail', 'assignsubmission_mailsimulator').' ' . $mailobj->randgroup . ' </td>';
                echo '      <td style="padding:5px; text-align:right">';
                echo '          <table align="right" style="margin-bottom:0;">';
                echo '              <tr>';
                echo '                  <td style="padding:0; margin:0;">';
                if($editingteacher) {
                    print_single_button($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php', 
                        array('id' => $this->cm->id, 'tid' => 0, 'gid' => $mailobj->randgroup), 
                        get_string('addalternativemail', 'assignsubmission_mailsimulator'));
                }
                echo '                  </td>';
                echo '                  <td style="padding:0; margin:0;">';
                if($editingteacher) {
                    echo $OUTPUT->help_icon('addalternativemail', 'assignsubmission_mailsimulator');
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
                $mailobj->message = '<div class="mailmessage">' . format_text(unserialize($mailobj->message)["text"], FORMAT_MOODLE) . '</div>';
                
                //var_dump($mailobj);
                $attachments = $mailobj->attachment>0 ? $this->get_files_str($mailobj->id, 0) : '';
                foreach ($attachments as $attachment) {
                    $mailobj->message .=  '<a href=' . $CFG->wwwroot . $attachment->url . '>'. $CFG->wwwroot . $attachment->url .'</a><br/>';
                }

                if($editingteacher) {
                    $mailobj->message .= '<span style="text-align:right">' . print_single_button($CFG->wwwroot . 
                        '/mod/assign/submission/mailsimulator/mail.php', array('id' => $this->cm->id, 'mid' => $mailobj->id), 
                        get_string('edit'), 'get', '_self', true) . '</span>';
                }
            }

            $p = $this->get_top_parent_id($mailobj->id);
            $from = $this->get_sender_string($mailobj, true);
            $firsttoname = $this->get_recipients_string($mailobj->id);
            $prio = '<span style="color:darkred">' . $this->get_prio_string($mailobj->priority) . '</span>';

            echo '<table class="allmailheader">';
            echo '  <tr>';
            echo '      <td style="width:100px;">' . get_string('subject', 'assignsubmission_mailsimulator') . ': </td>';
            echo '      <td colspan="5" style="background:white;border:1px;border-style:solid;border-right:0px;"><strong>' . $prio . format_text($mailobj->subject, 1) . '</strong></td>';
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
                print_single_button($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php', array('id' => $this->cm->id, 're' => 1, 'tid' => $mailobj->id), get_string('reply', 'assignsubmission_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                echo $OUTPUT->help_icon('reply', 'assignsubmission_mailsimulator');
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
                print_single_button($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php', array('id' => $this->cm->id, 're' => 2, 'tid' => $mailobj->id), get_string('replyall', 'assignsubmission_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                echo $OUTPUT->help_icon('replyall', 'assignsubmission_mailsimulator');
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
                print_single_button($CFG->wwwroot . '/mod/assign/submission/mailsimulator/template.php', array('id' => $this->cm->id, 'mid' => $p, 'gid' => $mailobj->randgroup), get_string('updatecorrectiontemplate', 'assignsubmission_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            echo $OUTPUT->help_icon('updatecorrectiontemplate', 'assignsubmission_mailsimulator');
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                //print_single_button($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php', array('id' => $this->cm->id, 'mid' => $tid, 'delete' => 1), get_string('delete'), 'get', '_self', false, '', $this->get_signed_out_status($tid), get_string('confirmdelete', 'assignsubmission_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                echo $OUTPUT->help_icon('delete', 'assignsubmission_mailsimulator');
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
                    print_single_button($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php', array('id' => $this->cm->id, 'mid' => $tid, 'delete' => 3), get_string('restore'));
                } else {
                    print_single_button($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php', array('id' => $this->cm->id, 'mid' => $tid, 'delete' => 2), get_string('trash', 'assignsubmission_mailsimulator'));
                }
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if($editingteacher) {
                echo $OUTPUT->help_icon('trashrestore', 'assignsubmission_mailsimulator');
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
            echo $OUTPUT->help_icon('weight', 'assignsubmission_mailsimulator');
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

    function get_user_mails($userid=null, $forgrading=false) {
        global $CFG, $USER, $DB;

        if (!$userid) {
            $userid = $USER->id;
        }

        if ($forgrading) {
            $sql = 'SELECT sm.id, sm.mailid, p.weight, sm.gainedweight, sm.comment, m.sender, m.subject, m.message, t.correctiontemplate, m.timesent, m.priority, m.attachment, m.userid
                    FROM {assignsubmission_mail_sgndml} AS sm
                    LEFT JOIN {assignsubmission_mail_tmplt} AS t ON sm.mailid = t.mailid
                    LEFT JOIN {assignsubmission_mail_mail} AS m ON m.id = sm.mailid
                    WHERE sm.userid = ' . $userid . '
                    AND m.assignment = ' . $this->cm->instance;
        } else {
            $sql = 'SELECT signed.mailid AS id, m.userid, m.parent, m.priority, m.sender, m.subject, m.message, m.timesent, m.attachment
                    FROM {assignsubmission_mail_sgndml} AS signed
                    LEFT JOIN {assignsubmission_mail_mail} as m ON m.id = signed.mailid
                    WHERE signed.userid = ' . $userid . '
                    AND m.assignment = ' . $this->cm->instance;
        }

        return $DB->get_records_sql($sql);
    }

    // Assign mail to student the first time the student acces the assignment
    function assign_student_mails() {
        global $CFG, $USER, $DB;

        $sql = 'SELECT m.id, t.randgroup FROM {assignsubmission_mail_mail} AS m
                LEFT JOIN {assignsubmission_mail_tmplt} AS t ON m.id = t.mailid
                WHERE m.assignment = ' . $this->cm->instance . '
                AND m.userid = 0
                AND m.parent = 0
                AND t.deleted = 0
                ORDER BY t.randgroup';

        $assignmentmails = $DB->get_records_sql($sql);
        $groupedtemplatesids = array();

        foreach ($assignmentmails as $key => $value) {
            $groupedtemplatesids[$value->randgroup][] = $value->id;
        }

        foreach ($groupedtemplatesids as $key => $value) {
            $signedoutmailobj = new stdClass();
            $signedoutmailobj->userid = $USER->id;
            $signedoutmailobj->gainedweight = 0;
            $signedoutmailobj->feedback = '';
            $signedoutmailobj->status = 0;

            $count = count($value) - 1;

            if ($count > 0) {
                $signedoutmailobj->mailid = $value[rand(0, $count)];
            } else {
                $signedoutmailobj->mailid = $value[0];
            }

            $DB->insert_record('assignsubmission_mail_sgndml', $signedoutmailobj);
        }
    }

    function delete_mail_and_children($mailid) {
        global $DB;

        $this->delete_mail($mailid);

        $cid = $DB->get_field('assignsubmission_mail_mail', 'id', array('parent' => $mailid));

        if ($cid) {
            $this->delete_mail_and_children($cid);
        } else {
            //$mailcount = $DB->get_field('assignment', 'var1', 'id', $this->assignment->id) - 1;
            //set_field('assignment', 'var1', $mailcount, 'id', $this->assignment->id);
        }
    }

    function handle_trash($mailid, $delete=true) {
        global $DB;

        $status = 0;

        if ($delete) {
            $status = 1;
            //$mailcount = get_field('assignment', 'var1', 'id', $this->assignment->id) - 1;
            //set_field('assignment', 'var1', $mailcount, 'id', $this->assignment->id);
        }

        $tid = $DB->get_field('assignsubmission_mail_tmplt', 'id', array('mailid' => $mailid));
        $DB->set_field('assignsubmission_mail_tmplt', 'deleted', $status, array('id' => $tid));
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
        global $CFG, $DB;

        $route = optional_param('route', 0, PARAM_INT);
        $tabs = array();
        $row = array();
        $sql = 'SELECT COUNT(*) FROM {assignsubmission_mail_tmplt} WHERE deleted = 1 AND randgroup != 0 AND mailid IN (SELECT id FROM {assignsubmission_mail_mail} WHERE assignment = ' . $this->cm->instance . ')';
        $counttrashmail = $DB->get_field_sql($sql);

        $row[] = new tabobject('mail', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $this->cm->id, get_string('mailbox', 'assignsubmission_mailsimulator'));
        $row[] = new tabobject('addmail', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php?id=' . $this->cm->id, get_string('addmail', 'assignsubmission_mailsimulator'));
        $row[] = new tabobject('addcontacts', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/contacts.php?id=' . $this->cm->id, get_string('addcontacts', 'assignsubmission_mailsimulator'));
        $row[] = new tabobject('trashmail', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $this->cm->id . '&route=3', get_string('trash', 'assignsubmission_mailsimulator') . ' (' . $counttrashmail . ')');

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
            $templatemail->randgroup = $this->calculate_group();
        }

        $templatemail->weight = 0;
        $templatemail->correctiontemplate = '';
        $templatemail->deleted = 0;

        return $templatemail;
    }

    function get_nested_from_child($mailobj) {
        global $CFG, $DB;
        $message = '<div class="mailmessage">' . format_text(unserialize($mailobj->message)['text'], FORMAT_MOODLE);
        $dept = 1;

        while ($mailobj = $DB->get_record('assignsubmission_mail_mail', array('id' => $mailobj->parent))) {
            $from = $this->get_sender_string($mailobj);
            $date = date('j M Y, H.i', $mailobj->timesent);
            $message .= '<br /><br/>' . $date . ' ' . get_string('wrote', 'assignsubmission_mailsimulator', $from) . ':';
            $message .= '<div style="border-left: 2px outset #000000; padding: 5px; padding-bottom: 0px;">'; 
                
            $message .= format_text(unserialize($mailobj->message)['text'], FORMAT_MOODLE);
                //$message .= ($mailobj->attachment ? $this->get_files_str($mailobj->id, $mailobj->userid) : '')

            $attachments = $mailobj->attachment>0 ? $this->get_files_str($mailobj->id, 0) : '';
            
            foreach ($attachments as $attachment) {
                $message .=  '<a href=' . $CFG->wwwroot . $attachment->url . '>'. $CFG->wwwroot . $attachment->url .'</a><br/>';
            }

            $dept++;
        }

        for ($i = 0; $i < $dept; $i++) {
            $message .= '</div>';
        }

        return $message;
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
                ':<br /><div style="border-left: 2px outset #000000; padding: 5px; padding-bottom: 0px;">';
            }

            //$replystr .= ( $m->attachment ? $this->get_files_str($m->id, $m->userid) : '') . format_text($m->message);
            
            $replystr .= format_text(unserialize($m->message)['text'], FORMAT_MOODLE);

            $attachments = $m->attachment>0 ? $this->get_files_str($m->id, 0) : '';
            //var_dump($attachments);
            foreach ($attachments as $attachment) {
                $replystr .=  '<a href=' . $CFG->wwwroot . $attachment->url . '>'. $CFG->wwwroot . $attachment->url .'</a><br/>';
            }

            if ($editbuttons) {
                $replystr .= '<div style="text-align:right">' . 'FDFGDFGFD' . print_single_button($CFG->wwwroot . 
                    '/mod/assign/submission/mailsimulator/mail.php', array('id' => $this->cm->id, 'mid' => $m->id), 
                    get_string('edit'), 'get', '_self', true) . '</div>';
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
            $fromobj = $DB->get_record('user', array("id" => $USER->id), 'firstname, lastname, email');
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

    function get_recipients_string($mailid) {
        global $USER, $DB;

        $to_mail_arr = $DB->get_records('assignsubmission_mail_to', array('mailid' => $mailid));
        //$teacherid = get_field('assignment', 'var3', 'id', $this->assignment->id);
        $toarr = array();

        foreach ($to_mail_arr as $value) {
            if ($value->contactid == 0) {
                $toarr[] = $DB->get_record_select('user', 'id=: id', array('id' => $teacherid), 'firstname, lastname, email');
            } elseif ($value->contactid == TO_STUDENT_ID) {
                $obj = new stdClass();

                if (has_capability('mod/assign:grade', $this->context)) {
                    $obj->firstname = 'STUDENT';
                    $obj->lastname = 'STUDENT';
                    $obj->email = 'STUDENT@STUDENT.COM';
                } else {
                    $obj->firstname = $USER->firstname;
                    $obj->lastname = $USER->lastname;
                    $obj->email = $USER->email;
                }
                $toarr[] = $obj;
            } else {
                $toarr[] = $DB->get_record('assignsubmission_mail_cntct', array('id' => $value->contactid));
            }
        }

        $firsttoname = '';
        $commacount = 0;
        $toarrcount = count($toarr);

        if ($toarr) {
            foreach ($toarr as $con) {
                $commacount++;

                if ($con)
                    $firsttoname .= $con->firstname . ' ' . $con->lastname . ' &lt;' . $con->email . '&gt;';
                else
                    $firsttoname .= 'MISSING CONTACT';

                if ($commacount < $toarrcount)
                    $firsttoname .= ', ';
            }
        } else {
            $firsttoname = 'UNSPECIFIED';
        }

        return $firsttoname;
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
                $this->add_parent($mailid, $gid);
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
        $mid = optional_param('mid', 0, PARAM_INT);         // Mail id

        $imgurl = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/';
        $mailcontent = '&nbsp;';
        $mailheaders = '';

        if ($route==1) {
            $sentarr = $this->get_user_sent();
            $titlestr = get_string('sent', 'assignsubmission_mailsimulator') . ' (' . count($sentarr) . ' ' 
                . get_string('mail', 'assignsubmission_mailsimulator') . ')';

            if ($sentarr) {
                $mailcount = count($sentarr);

                foreach ($sentarr as $k => $sentmail) {
                    $link = $CFG->pagepath . '?id=' . $this->cm->id . '&mid=' . $sentmail->id . '&route=' . $route;
                    $attachment = false;

                    if ($sentmail->attachment > 0) {
                        $attachment = true;
                    }
                    $mailheaders .= $this->mail_header($sentmail, $link, $attachment);

                    if ($mid == $sentmail->id) {
                        $key = $k;
                    }
                }
                if (isset($key)) {
                    $mailcontent = $this->mail_body($sentarr[$key], true);
                }
            }
        } else {
            if (!$templatemailarr = $this->get_user_mails()) {
                $this->assign_student_mails();
                redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $this->cm->id, '', 0);    
            }

            $replyobject = false;
            $titlestr = get_string('inbox', 'assignsubmission_mailsimulator') . ' (' . count($templatemailarr) . ' ' 
                . get_string('mail', 'assignsubmission_mailsimulator') . ')';

            foreach ($templatemailarr as $mailobj) {
                $nested = $this->get_nested_reply_object($mailobj);

                if ($nested)
                    $replyobject[] = $nested;
                else {
                    $mailobj->message = '<div class="mailmessage">' . format_text(unserialize($mailobj->message)['text'], FORMAT_MOODLE) ;
                    //var_dump($mailobj);
                    $attachments = $mailobj->attachment>0 ? $this->get_files_str($mailobj->id, 0) : '';
                    foreach ($attachments as $attachment) {
                        $mailobj->message .=  '<a href=' . $CFG->wwwroot . $attachment->url . '>'. $CFG->wwwroot . $attachment->url .'</a><br/>';
                    }

                    //($mailobj->attachment ? $this->get_files_str($mailobj->id, $mailobj->userid) : '') 
                    $mailobj->message .= '</div>';
                    
                    $replyobject[] = $mailobj;
                }
            }

            //$replyobject = $this->vsort($replyobject, 'timesent', false);
            $key = null;

            foreach ($replyobject as $k => $m) {
                $link = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $this->cm->id . '&mid=' . $m->id;
                $attachment = false;

                if ($m->attachment == 1) {
                    $attachment = true;
                }
                $mailheaders .= $this->mail_header($m, $link, $attachment);

                if ($mid == $m->id) {
                    $key = $k;
                }
            }

            if (isset($key)) {
                $mailcontent = $this->mail_body($replyobject[$key]);
            }
        }

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
        $sidebarstr .= '<div class="' . ($route == 0 ? 'mailboxselect' : 'mailbox') . '"><img src="' . $imgurl . 
        'inbox.png"><a href="' . $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id='.
        $this->cm->id.'&route=0">' . get_string('inbox', 'assignsubmission_mailsimulator') . '</a></div>';
        $sidebarstr .= '<div class="' . ($route == 1 ? 'mailboxselect' : 'mailbox') . '"><img src="' . 
        $imgurl . 'sent.png"><a href="' . $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id='.
        $this->cm->id.'&route=1">' . get_string('sent', 'assignsubmission_mailsimulator') . '</a></div>';

        return $sidebarstr;
    }

    function topbar() {
        global $CFG;

        //$submission = $this->get_submission();
        $mid = optional_param('mid', 0, PARAM_INT);       // Mail id
        $link = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php?id=' . $this->cm->id . '&re=';
        $imgurl = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/';

        if ($mid) {
            $topmenu = '<!-- Start Mail Top Menu-->
                            <table border="0px" >
                                <tr>
                                    <td width="92"></td>
                                    <td >
                                        <a href="' . $link . '1&tid=' . $mid . '" title="' . get_string('reply', 'assignsubmission_mailsimulator') . '" onmouseover="document.re.src=\'' . $imgurl . 'button-reply-down.png\'" onmouseout="document.re.src=\'' . $imgurl . 'button-reply.png\'">
                                            <img name="re" src="' . $imgurl . 'button-reply.png">
                    </a>
                                    </td>
                                    <td >
                                        <a href="' . $link . '2&tid=' . $mid . '" title="' . get_string('replyall', 'assignsubmission_mailsimulator') . '" onmouseover="document.all.src=\'' . $imgurl . 'button-replyall-down.png\'" onmouseout="document.all.src=\'' . $imgurl . 'button-replyall.png\'">
                                            <img name="all" src="' . $imgurl . 'button-replyall.png">
                                        </a>
                                    </td>
                                    <td >
                                        <a href="' . $link . '3&tid=' . $mid . '" title="' . get_string('forward', 'assignsubmission_mailsimulator') . '" onmouseover="document.fwd.src=\'' . $imgurl . 'button-forward-down.png\'" onmouseout="document.fwd.src=\'' . $imgurl . 'button-forward.png\'">
                                            <img name="fwd" src="' . $imgurl . 'button-forward.png">
                                        </a>
                                    </td>
                                    <td width="10">&nbsp;</td>
                                    <td >
                                        <a href="' . $link . '0&tid=0" title="' . get_string('newmail', 'assignsubmission_mailsimulator') . '" onmouseover="document.newmail.src=\'' . $imgurl . 'button-newmail-down.png\'" onmouseout="document.newmail.src=\'' . $imgurl . 'button-newmail.png\'">
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
                                        <a href="' . $link . '0&tid=0" title="' . get_string('newmail', 'assignsubmission_mailsimulator') . '" onmouseover="document.newmail.src=\'' . $imgurl . 'button-newmail-down.png\'" onmouseout="document.newmail.src=\'' . $imgurl . 'button-newmail.png\'">
                                            <img name="newmail" src="' . $imgurl . 'button-newmail.png">
                                        </a>
                                    </td>

                                </tr>
                            </table>
                            <!-- End Mail Top Menu-->';
        }

        return $topmenu;
    
    }

    function mail_header($obj, $link='#', $attachment = false) {
        global $USER, $CFG;

        $selected = optional_param('mid', 0, PARAM_INT);
        $route = optional_param('route', 0, PARAM_INT);

        if ($selected == $obj->id) {
            $stylelink = 'class="mailheadertableselected"';
            $datestyle = 'class="headerdateselected"';
        } else {
            $stylelink = 'class="mailheadertable" onclick="window.location.href=\'' . $link . '\';"';
            $datestyle = 'class="headerdate"';
        }

        $from = $this->get_sender_string($obj);

        if (!$route) {
            $statusobj = $this->get_mail_status($obj->id);

            if ($selected == $obj->id && $statusobj->status == 0) {
                $statusobj->status = $this->set_mail_status($statusobj->mailid, 1);
            }

            $statusstr = $this->get_mail_status_img($statusobj->status);
        } else {
            $statusstr = '&nbsp;&nbsp;';
        }

        if ($attachment) {
            $attachment = '<img src="' . $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/attachment.png">';
        }

        $header = '<table width="100%" ' . $stylelink . ' >';
        $header .= '    <tr>';
        $header .= '        <td>' . $attachment . '</td>';
        $header .= '        <td class="mailheadertd"><table width="100%"><tr><td><strong>' . $from . '</strong></td><td ' . $datestyle . '>' . date('Y-m-d', $obj->timesent) . '&nbsp;</td></tr></table></td>';
        $header .= '    </tr><tr>';
        $header .= '        <td class="statusimg">' . $statusstr . '</td>';
        $header .= '        <td><strong>' . $this->get_prio_string($obj->priority) . '</strong> ' . $obj->subject . '</td>';
        $header .= '    </tr>';
        $header .= '</table>';

        return $header;
    }

    // Get the status from user signedout mail
    // 0 = unread, 1 = read, 2 = replied
    function get_mail_status($mailid) {
        global $USER, $DB;

        $statusobj = new stdClass();

        for (;;) {
            $statusobj->status = $DB->get_field('assignsubmission_mail_sgndml', 'status', array('mailid' => $mailid, 'userid' => $USER->id));

            if ($statusobj->status === false) {
                $mailid = $DB->get_field('assignsubmission_mail_mail', 'parent', array('id' => $mailid));
            } else {
                break;
            }
        }
        $statusobj->mailid = $mailid;

        return $statusobj;
    }

    function get_signed_out_status($mailid) {
        global $DB;

        return $DB->record_exists('assignsubmission_mail_sgndml', array('mailid' => $mailid));
    }

    function get_mail_status_img($status) {
        global $CFG;

        $imgurl = '<img src="' . $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/';

        switch ($status) {
            case 2:
                $status = $imgurl . 'status-replied.png">';
                break;

            case 1:
                $status = $imgurl . 'status-read.png">';
                break;

            case 0:
            default:
                $status = $imgurl . 'status-unread.png">';
                break;
        }
        return $status;
    }

    function set_mail_status($mailid, $newstatus) {
        global $USER, $DB;

        $dataobject = new stdClass();
        $dataobject->id = $DB->get_field('assignsubmission_mail_sgndml', 'id', array('mailid' => $mailid, 'userid' => $USER->id));
        $dataobject->status = $newstatus;

        $DB->update_record('assignsubmission_mail_sgndml', $dataobject);

        return $newstatus;
    }

    function mail_body($mailobject, $sentview = false) {
        global $CFG;

        $bodystr = '<div class="mailmessage">';
        $bodystr .= '<strong>' . $this->get_sender_string($mailobject, true) . '</strong><br />';
        $bodystr .= format_text($mailobject->subject, 1) . '<br />';
        $bodystr .= date('j F Y, H.i', $mailobject->timesent) . '<br />';
        $bodystr .= $this->get_recipients_string($mailobject->id) . '<br />';
        $bodystr .= '<hr />';
        $bodystr .= '</div>';

        if ($sentview) {
            //$bodystr .= ( $mailobject->attachment ? $this->get_files_str($mailobject->id, $mailobject->userid) : '');

            $attachments = $mailobject->attachment>0 ? $this->get_files_str($mailobject->id, 0) : '';
            foreach ($attachments as $attachment) {
                $bodystr .=  '<a href=' . $CFG->wwwroot . $attachment->url . '>'. $CFG->wwwroot . $attachment->url .'</a><br/>';
            }

            $bodystr .= $this->get_nested_from_child($mailobject);
        } else {
            $bodystr .= format_text($mailobject->message, FORMAT_MOODLE);
        }

        return $bodystr;
    }

    function get_user_sent($userid=null) {
        global $USER, $DB;

        if (!$userid) {
            $userid = $USER->id;
        }

        $select = 'userid = ' . $userid . ' AND assignment = ' . $this->assignment->id;
        return $DB->get_records('assignsubmission_mail_mail', array("userid" => $userid, "assignment" => $this->cm->instance), 'timesent DESC');
    }

    function update_mail($mail) {
        global $DB;

        $DB->delete_records('assignsubmission_mail_to', array('mailid' => $mail->mailid));

        foreach ($mail->to as $to) {
            $obj = new stdClass();
            $obj->contactid = $to;
            $obj->mailid = $mail->mailid;

            $DB->insert_record('assignsubmission_mail_to', $obj);
        }

        $mail->id = $mail->mailid;
        unset($mail->mailid);
        unset($mail->MAX_FILE_SIZE);

        //if ($this->upload_attachment($mail->id, $mail->userid)) {
        //    $mail->attachment = 1;
        //}

        $DB->update_record('assignsubmission_mail_mail', $mail);
    }

}

