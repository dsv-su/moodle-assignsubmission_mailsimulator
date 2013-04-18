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
 * Mailbox view class for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once($CFG->dirroot.'/mod/assign/submission/mailsimulator/lib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir . '/portfolio/caller.php');

define('TO_STUDENT_ID', 9999999); // To identify when a mail is sent to a student.

class mailbox {

    private $url;
    private $cmid;
    private $cm;
    private $context;
    private $course;
    private $assigninstance;
    private $plugininstance;

    public function __construct($context, $cm, $course) {
        $this->context  = $context;
        $this->cm       = $cm;
        $this->course   = $course;

        $this->assigninstance = new assign($context, $cm, $course);
        $this->plugininstance = new assign_submission_mailsimulator($this->assigninstance, 'mailsimulator');
    }

    /**
     * Handle the view of the mailbox
     */
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
            } else if ($delete == 2) {
                $this->handle_trash($mid);
            } else if ($delete == 3) {
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
            if (!$existingsubmission) {
                $this->view_mailbox();
            } else if ($existingsubmission->status<>'submitted') {
                $this->view_mailbox();
            } else {
                error('You cannot view the mailbox since you have already sent this assignment for grading');
            }
            echo html_writer::tag('div', html_writer::link('../../view.php?id=' . $this->cm->id,
                get_string('backtostart', 'assignsubmission_mailsimulator')), array('align'=>'center'));
        }

    }

    /**
     * Check if plugin is set up correctly: all top mails must have a template,
     * add mail if the number of templates is below 'mailnumber' config setting.
     */
    function check_assignment_setup() {
        global $CFG, $DB;

        // All Teacher Mail Parents must have a template.
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
                $this->add_template($id);
            }
        }

        $mailgroupnumber = $this->get_config('mailnumber');

        // Count how many mailgroups this assignment has.
        $sql = 'SELECT count(DISTINCT tm.randgroup)
                FROM {assignsubmission_mail_tmplt} AS tm
                LEFT JOIN {assignsubmission_mail_mail} AS m ON m.id = tm.mailid
                WHERE m.assignment = ' . $this->cm->instance . '
                AND m.userid = 0
                AND tm.deleted = 0
                AND tm.randgroup != 0';

        $mailgroupcount = $DB->count_records_sql($sql);

        if ($mailgroupnumber > $mailgroupcount) {
            // Prevent display if the assignment is not configured.
            if (!has_capability('mod/assign:grade', $this->context)) {
                print_error('Assignment needs to be setup correctly, contact your teacher');
            }
            // Add new mail.
            if (!$DB->record_exists('assignsubmission_mail_cntct', array('assignment' => $this->cm->instance))) {
                $this->add_contacts();
            }
            $this->add_mail();
        } else if ($mailgroupnumber < $mailgroupcount) {
            $this->plugininstance->set_config('mailnumber', $mailgroupcount);
        }
    }

    /**
     * Returns the html code for attachments output (filetype image + link)
     *
     * @param int $mid
     * @param int $userid
     * @return string
     */
    function get_files_str($mid, $userid) {
        global $CFG, $OUTPUT;
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'assignsubmission_mailsimulator', 'attachment', $mid);

        $output = '';
        $type = 'html';

        if ($files) {
            $output .= '<p align=right style="padding-right:10px;>';
            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }

                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle',
                    array('class' => 'icon'));
                $path = file_encode_url($CFG->wwwroot . '/pluginfile.php', '/' . $this->context->id .
                    '/assignsubmission_mailsimulator/attachment/' . $mid . '/' . $filename);

                if ($type == 'html') {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= "<a href=\"$path\">".s($filename)."</a>";
                    /*if ($canexport) {
                        $button->set_callback_options('forum_portfolio_caller', array('postid' => $post->id,
                        'attachment' => $file->get_id()), 'mod_forum');
                        $button->set_format_by_file($file);
                        $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }*/
                    $output .= "<br />";

                } else if ($type == 'text') {
                    $output .= "$strattachment ".s($filename).":\n$path\n";
                }
            }
            $output .= '</p>';
        }
        return $output;
    }

    /**
     * View all mails that have been added by a teacher for this assignment. Teacher's main view.
     *
     * @param bool $trash
     */
    function view_assignment_mails($trash=false) {
        global $DB, $OUTPUT, $CFG;

        $deletestatus = ($trash ? 1 : 0);

        $sql = 'SELECT t.id AS tid, t.mailid AS id, t.randgroup, t.weight, t.correctiontemplate, t.deleted, m.priority,
                m.sender, m.userid, m.subject, m.message, m.timesent, m.parent, m.attachment
                FROM {assignsubmission_mail_tmplt} AS t
                LEFT JOIN {assignsubmission_mail_mail} AS m ON m.id = t.mailid
                WHERE m.assignment = ' . $this->cm->instance . '
                AND m.parent = 0
                AND m.userid = 0
                AND t.deleted = ' . $deletestatus . '
                ORDER BY t.randgroup';

        if (!$templatemailarr = $DB->get_records_sql($sql)) {
            echo $OUTPUT->notification('Trash is empty');
            return;
        }
        $group = 0;

        foreach ($templatemailarr as $mailobj) {
            $tid = $mailobj->id;
            $groupid = $DB->get_field('assignsubmission_mail_tmplt', 'randgroup', array('mailid' => $mailobj->id));

            if ($groupid == 0) {
                $this->add_template($mailobj->id, $this->calculate_group());
            }

            $editingteacher = has_capability('mod/assign:grade', $this->context);

            if ($mailobj->randgroup != $group) {
                if ($group != 0) {
                    echo '</div><br />';
                }
                echo '<div style="border:1px; border-style:solid; width:90%; margin:auto; background-color:#ffffff">';

                echo '<table border="0" width="100%" style="background:gray; color:white; margin-bottom: 0;">';
                echo '  <tr>';
                echo '      <td style="padding:5px"> '.get_string('mail', 'assignsubmission_mailsimulator').' ' .
                    $mailobj->randgroup . ' </td>';
                echo '      <td style="padding:5px; text-align:right">';
                echo '          <table align="right" style="margin-bottom:0;">';
                echo '              <tr>';
                echo '                  <td style="padding:0; margin:0;">';
                if ($editingteacher) {
                    echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php',
                        array('id' => $this->cm->id, 'tid' => 0, 'gid' => $mailobj->randgroup)),
                        get_string('addalternativemail', 'assignsubmission_mailsimulator'));
                }
                echo '                  </td>';
                echo '                  <td style="padding:0; margin:0;">';
                if ($editingteacher) {
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
                $attachments = $mailobj->attachment>0 ? $this->get_files_str($mailobj->id, 0) : '';
                $message = format_text(unserialize($mailobj->message)["text"], FORMAT_MOODLE);
                $mailobj->message = '<div class="mailmessage">' . $attachments . $message;

                if ($editingteacher) {
                    /*
                    $mailobj->message .= '<span style="text-align:right">' . print_single_button($CFG->wwwroot .
                        '/mod/assign/submission/mailsimulator/mail.php', array('id' => $this->cm->id, 'mid' => $mailobj->id),
                        get_string('edit'), 'get', '_self', true) . '</span>';
                    */
                    $link = new moodle_url($CFG->wwwroot .'/mod/assign/submission/mailsimulator/mail.php',
                        array('id' => $this->cm->id, 'mid' => $mailobj->id));
                    $mailobj->message .= '<div style="text-align:right">' . html_writer::link($link, get_string('edit')) . '</div>';
                }
                $mailobj->message .= '</div>';
            }

            $p = $this->get_top_parent_id($mailobj->id);
            $from = $this->get_sender_string($mailobj, true);
            $firsttoname = $this->get_recipients_string($mailobj->id);
            $prio = '<span style="color:darkred">' . $this->get_prio_string($mailobj->priority) . '</span>';

            echo '<table class="allmailheader">';
            echo '  <tr>';
            echo '      <td style="width:100px;">' . get_string('subject', 'assignsubmission_mailsimulator') . ': </td>';
            echo '      <td colspan="5" style="background:white;border:1px;border-style:solid;border-right:0px;"><strong>' .
                $prio . format_text($mailobj->subject, 1) . '</strong></td>';
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
            if ($editingteacher) {
                echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php',
                    array('id' => $this->cm->id, 're' => 1, 'tid' => $mailobj->id)),
                    get_string('reply', 'assignsubmission_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if ($editingteacher) {
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
            if ($editingteacher) {
                echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php',
                    array('id' => $this->cm->id, 're' => 2, 'tid' => $mailobj->id)),
                    get_string('replyall', 'assignsubmission_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if ($editingteacher) {
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
            if ($editingteacher) {
                echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot . '/mod/assign/submission/mailsimulator/template.php',
                    array('id' => $this->cm->id, 'mid' => $p, 'gid' => $mailobj->randgroup)),
                    get_string('updatecorrectiontemplate', 'assignsubmission_mailsimulator'));
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            echo $OUTPUT->help_icon('updatecorrectiontemplate', 'assignsubmission_mailsimulator');
            echo '                  </td>';
            echo '              </tr>';
            echo '          </table>';
            echo '      </td>';
            if ($editingteacher && $trash) {
                echo '      <td style="width:100px">';
                echo '          <table>';
                echo '              <tr>';
                echo '                  <td style="padding:0; margin:0;">';
                $url = new moodle_url($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php',
                    array('id' => $this->cm->id, 'mid' => $tid, 'delete' => 1, 'trash' => true, 'route' => 3));
                echo $OUTPUT->single_button($url, get_string('delete'));
                echo '                  </td>';
                echo '                  <td style="padding:0; margin:0;">';
                echo $OUTPUT->help_icon('delete', 'assignsubmission_mailsimulator');
                echo '                  </td>';
                echo '              </tr>';
                echo '          </table>';
                echo '      </td>';
            }
            echo '      <td style="width:100px">';
            echo '          <table>';
            echo '              <tr>';
            echo '                  <td style="padding:0; margin:0;">';
            if ($editingteacher) {
                if ($trash) {
                    echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php',
                        array('id' => $this->cm->id, 'mid' => $tid, 'delete' => 3)),
                        get_string('restore'));
                } else {
                    echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php',
                        array('id' => $this->cm->id, 'mid' => $tid, 'delete' => 2)),
                        get_string('trash', 'assignsubmission_mailsimulator'));
                }
            }
            echo '                  </td>';
            echo '                  <td style="padding:0; margin:0;">';
            if ($editingteacher) {
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
            echo '<div>' . format_text($mailobj->message) . '</div>';
            echo '<div style="padding: 5px; color:green; background: white">' . format_text($mailobj->correctiontemplate,
                FORMAT_MOODLE) . '</div>';
            echo '<br />';

            $group = $mailobj->randgroup;
        }
        echo '</div>';
        echo '<br />';
    }

    /**
     * Get all mails that have been sent by user together with feedback and given weight,
     * depending on whether it is $forgrading
     *
     * @param int $userid
     * @param bool $forgrading
     * @return array
     */
    function get_user_mails($userid=null, $forgrading=false) {
        global $CFG, $USER, $DB;

        if (!$userid) {
            $userid = $USER->id;
        }

        if ($forgrading) {
            $sql = 'SELECT sm.id, sm.mailid, t.weight, sm.gainedweight, sm.feedback, m.sender, m.subject, m.message,
                    t.correctiontemplate, m.timesent, m.priority, m.attachment, m.userid
                    FROM {assignsubmission_mail_sgndml} AS sm
                    LEFT JOIN {assignsubmission_mail_tmplt} AS t ON sm.mailid = t.mailid
                    LEFT JOIN {assignsubmission_mail_mail} AS m ON m.id = sm.mailid
                    WHERE sm.userid = ' . $userid . '
                    AND m.assignment = ' . $this->cm->instance;
        } else {
            $sql = 'SELECT signed.mailid AS id, m.userid, m.parent, m.priority, m.sender, m.subject, m.message,
                    m.timesent, m.attachment
                    FROM {assignsubmission_mail_sgndml} AS signed
                    LEFT JOIN {assignsubmission_mail_mail} as m ON m.id = signed.mailid
                    WHERE signed.userid = ' . $userid . '
                    AND m.assignment = ' . $this->cm->instance;
        }

        return $DB->get_records_sql($sql);
    }

    /**
     * Assign mails to student the first time he/she access the mailbox view, or add a mail if a student lacks any
     *
     * @param int $signedmaildiff
     */    
    function assign_student_mails($signedmaildiff=0) {
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

        for ($i=0; $i<$signedmaildiff; $i++) {
            unset($groupedtemplatesids[$i+1]);
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

    /**
     * Continiously delete all mail's children (i.e. replies) 
     *
     * @param int $mailid
     */
    function delete_mail_and_children($mailid) {
        global $DB;

        $this->delete_mail($mailid);

        $cid = $DB->get_field('assignsubmission_mail_mail', 'id', array('parent' => $mailid));

        if ($cid) {
            $this->delete_mail_and_children($cid);
        } else {
            $mailcount = $this->get_config('mailnumber')-1;
            $this->plugininstance->set_config('mailnumber', $mailcount);
        }
    }

    /**
     * Delete a single mail and its attachments
     *
     * @param int $mailid
     */
    function delete_mail($mailid) {
        global $CFG, $DB;

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'assignsubmission_mailsimulator', 'attachment', $mailid);
        foreach ($files as $file) {
            $file->delete();
        }

        $DB->delete_records('assignsubmission_mail_to', array('mailid' => $mailid));
        $DB->delete_records('assignsubmission_mail_tmplt', array('mailid' => $mailid));
        $DB->delete_records('assignsubmission_mail_mail', array('id' => $mailid));
    }

    /**
     * Update the info whether the mail is put to trash or not.
     *
     * @param int $mailid
     * @param bool $delete
     */
    function handle_trash($mailid, $delete=true) {
        global $DB;

        $status = 0;

        if ($delete) {
            $status = 1;
            $mailcount = $this->get_config('mailnumber')-1;
            $this->plugininstance->set_config('mailnumber', $mailcount);
        }

        $tid = $DB->get_field('assignsubmission_mail_tmplt', 'id', array('mailid' => $mailid));
        $DB->set_field('assignsubmission_mail_tmplt', 'deleted', $status, array('id' => $tid));
    }

    /**
     * Get id of the top parent mail for the given mail.
     *
     * @param int $mailid
     * @return int
     */
    function get_top_parent_id($mailid) {
        global $DB;

        $parentid = $mailid;

        do {
            $mailid = $parentid;
        } while ($parentid = $DB->get_field('assignsubmission_mail_mail', 'parent', array('id' => $mailid)));

        return $mailid;
    }

    /**
     * Retrieve the priority string based on priority number 
     *
     * @param int $prionumb
     * @return string
     */
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

    /**
     * Redirects to template editing view
     *
     * @param int $mailid
     * @param int $gid
     */
    function add_template($mailid=0, $gid=0) {
        global $CFG;

        if ($mailid) {
            redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/template.php?id=' . $this->cm->id .
                '&mid=' . $mailid . '&gid=' . $gid, 'Add a template ', 1);
        }
    }

    /**
     * Redirects to contacts editing view
     */
    function add_contacts() {
        global $CFG;

        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/contacts.php?id=' . $this->cm->id, 'Add a contact', 1);
    }

    /**
     * Redirects to mail editing view
     *
     * @param int $tid
     * @param int $gid
     */
    function add_mail($tid=0, $gid=0) {
        global $CFG;

        redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php?id=' . $this->cm->id . '&tid=' .
            $tid . '&gid=' . $gid, 'Add a mail', 1);
    }

    /**
     * Calculate the randgroup number to assign a given template to
     *
     * @return int 
     */
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

    /**
     * Update the information about user's submission
     *
     * @param int $userid
     */
    function update_user_submission($userid) {
        global $DB;

        $existingsubmission = $this->user_have_registered_submission($userid, $this->cm->instance);
        $submission = $this->assigninstance->get_user_submission($userid, true);

        if ($existingsubmission) {
            $submission->timemodified = time();
            $DB->update_record('assign_submission', $submission);
        }
    }

    /**
     * Print header tabs for teacher view and highlight the current one
     *
     * @param string $current
     */
    function print_tabs($current='mail') {
        global $CFG, $DB, $OUTPUT;

        echo $OUTPUT->header();

        $route = optional_param('route', 0, PARAM_INT);
        $tabs = array();
        $row = array();
        $sql = 'SELECT COUNT(*) FROM {assignsubmission_mail_tmplt} WHERE deleted = 1 AND randgroup != 0
                AND mailid IN (SELECT id FROM {assignsubmission_mail_mail} WHERE assignment = ' . $this->cm->instance . ')';
        $counttrashmail = $DB->get_field_sql($sql);

        $row[] = new tabobject('mail', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' .
            $this->cm->id, get_string('mailbox', 'assignsubmission_mailsimulator'));
        $row[] = new tabobject('addmail', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php?id=' .
            $this->cm->id, get_string('addmail', 'assignsubmission_mailsimulator'));
        $row[] = new tabobject('addcontacts', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/contacts.php?id=' .
            $this->cm->id, get_string('addcontacts', 'assignsubmission_mailsimulator'));
        $row[] = new tabobject('trashmail', $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' .
            $this->cm->id . '&route=3', get_string('trash', 'assignsubmission_mailsimulator') . ' (' . $counttrashmail . ')');

        $tabs[] = $row;

        print_tabs($tabs, $current);
    }

    /**
     * Delete a dummy mail contact
     *
     * @param int $contactid
     */
    function delete_contact($contactid) {
        global $CFG, $DB, $OUTPUT;

        // Check if contact is in use.
        if (!$active = $DB->record_exists('assignsubmission_mail_to', array('contactid' => $contactid))) {
            $active = $DB->record_exists('assignsubmission_mail_mail', array('userid' => 0, 'sender' => $contactid));
        }

        if ($active) {
            $contact = $DB->get_record('assignsubmission_mail_cntct', array('id' => $contactid));
            $msg = get_string('contactinuse', 'assignsubmission_mailsimulator');
            $msg .= html_writer::empty_tag('br') . $contact->firstname . ' ' . $contact->lastname; 
            $msg .= ' &lt;' . $contact->email . '&gt' . html_writer::empty_tag('br');
            echo $OUTPUT->error_text($msg);
        } else {
            $DB->delete_records('assignsubmission_mail_cntct', array('id' => $contactid));
        }
    }

    /**
     * Prepare a mail object with some prefilled data based on given params
     *
     * @param int $parent
     * @param int $from
     * @param int $priority
     * @return stdClass
     */
    function prepare_mail($parent=0, $from=0, $priority=0) {
        global $USER, $CFG, $DB;

        $teacher = has_capability('mod/assign:grade', $this->context);

        $mail = new stdClass;
        $mail->userid = 0;              // This is an assignment mail.
        $mail->teacher = $teacher;

        if (!$teacher) {
            $mail->userid = $USER->id;  // This is a student mail.
        }
        $mail->mailid = 0;
        $mail->parent = $parent;        // 0 = new mail, 1 = reply.
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

        $teacherid = $this->plugininstance->get_config('teacherid');
        $teacherobj = $DB->get_record('user', array('id' => $teacherid), 'firstname, lastname, email');
        $studentobj = $DB->get_record('user', array('id'=> $USER->id), 'firstname, lastname, email');

        $contacts[0] = $teacherobj->firstname . ' ' . $teacherobj->lastname . ' &lt;' . $teacherobj->email . '&gt;';

        if ($teacher) {
            $contacts[TO_STUDENT_ID] = get_string('mailtostudent', 'assignsubmission_mailsimulator');
        } else {
            $contacts[TO_STUDENT_ID] = $studentobj->firstname . ' ' . $studentobj->lastname . ' &lt;' . $studentobj->email . '&gt;';
        }

        asort($contacts);

        $mail->to = $contacts;

        return $mail;
    }

    /**
     * Prepare a template object for given mail
     *
     * @param int $mailid
     * @param int $group
     * @return stdClass
     */
    function prepare_parent($mailid=0, $group=0) {
        global $DB;

        $templatemail = new stdClass;
        $templatemail->maxweight = $this->plugininstance->get_config('maxweight');
        $templatemail->id = 0;

        if ($mailid) {
            $id = $DB->get_field('assignsubmission_mail_tmplt', 'id', array('mailid' => $mailid));
            if ($id) {
                $templatemail->id = $id;
            }
        }
        $templatemail->mailid = $mailid;
        $templatemail->randgroup = $group;

        // If we need to calculate a group for this template.
        if (has_capability('mod/assign:grade', $this->context) && !$group) {
            $templatemail->randgroup = $this->calculate_group();
        }

        $templatemail->weight = 0;
        $templatemail->correctiontemplate = '';
        $templatemail->deleted = 0;

        return $templatemail;
    }

    /**
     * Get nested mail thread from child (looking up for top parent mail)
     *
     * @param stdClass $mailobj
     * @return string
     */
    function get_nested_from_child($mailobj) {
        global $CFG, $DB;
        $message = '<div class="mailmessage">' . format_text(unserialize($mailobj->message)['text'], FORMAT_MOODLE);
        $dept = 1;

        while ($mailobj = $DB->get_record('assignsubmission_mail_mail', array('id' => $mailobj->parent))) {
            $from = $this->get_sender_string($mailobj);
            $date = date('j M Y, H.i', $mailobj->timesent);
            $message .= '<br /><br/>' . $date . ' ' . get_string('wrote', 'assignsubmission_mailsimulator', $from) . ':';
            $message .= '<div style="border-left: 2px outset #000000; padding: 5px; padding-bottom: 0px; padding-right: 0px;">';
            $message .= $mailobj->attachment>0 ? $this->get_files_str($mailobj->id, 0) : '';
            $message .= format_text(unserialize($mailobj->message)['text'], FORMAT_MOODLE);
            $dept++;
        }

        for ($i = 0; $i < $dept; $i++) {
            $message .= '</div>';
        }

        return $message;
    }

    /**
     * Get nested reply objects or false if there is no reply
     *
     * @param stdClass $mailobj
     * @param bool $editbuttons
     * @return mixed bool if no replies | stdClass with reply mail
     */
    function get_nested_reply_object($mailobj, $editbuttons=false) {
        global $CFG, $DB, $OUTPUT;

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
                $mailobj = new stdClass();
                $mailobj->id = $m->id;
                $mailobj->subject = $m->subject;
                $mailobj->timesent = $m->timesent;
                $mailobj->priority = $m->priority;
                $mailobj->sender = $m->sender;
            } else {
                $from = $this->get_sender_string($m);
                $replystr .= date('j F Y, H.i', $m->timesent) . ' ' . get_string('wrote', 'assignsubmission_mailsimulator', $from) .
                ':<br /><div style="border-left: 2px outset #000000; padding: 5px; padding-bottom: 0px; padding-right: 0px;">';
            }

            $replystr .= $m->attachment>0 ? $this->get_files_str($m->id, 0) : '';
            $replystr .= format_text(unserialize($m->message)['text'], FORMAT_MOODLE);

            if ($editbuttons) {
                /*
                ob_start();
                echo $OUTPUT->single_button('fgfgfg', get_string('edit'));
                $myStr = ob_get_contents();
                ob_end_clean();
                $replystr .= '<div style="text-align:right">' . print_single_button($CFG->wwwroot .
                    '/mod/assign/submission/mailsimulator/mail.php', array('id' => $this->cm->id, 'mid' => $m->id),
                    get_string('edit'), 'get', '_self', true) . '</div>';
                $replystr .= $myStr;
                */
                $link = new moodle_url($CFG->wwwroot .'/mod/assign/submission/mailsimulator/mail.php',
                    array('id' => $this->cm->id, 'mid' => $m->id));
                $replystr .= '<div style="text-align:right">' . html_writer::link($link, get_string('edit')) . '</div>';
            }
            if ($m->attachment == 1) {
                $attachment = 1;
            }

            $mailobj->attachment = $m->attachment;
            $divcount++;
        }
        for ($i = 0; $i < count($replies); $i++) {
            $replystr .= '</div>';
        }

        $mailobj->message = $replystr;
        if (isset($m->pid)) {
            $mailobj->pid = $m->pid;
        }
        if (isset($m->weight)) {
            $mailobj->weight = $m->weight;
        }
        if (isset($m->correctiontemplate)) {
            $mailobj->correctiontemplate = $m->correctiontemplate;
        }
        if (isset($m->randgroup)) {
            $mailobj->randgroup = $m->randgroup;
        }

        return $mailobj;
    }

    /**
     * Get string with sender firstname, lastname and email
     *
     * @param stdClass $mailobj
     * @param bool $long
     * @return string
     */
    function get_sender_string($mailobject, $long=false) {
        global $USER, $DB;

        if ($mailobject->sender == 0 && isset($mailobject->userid) && $mailobject->userid == 0) {
            $teacherid = $this->plugininstance->get_config('teacherid');
            $fromobj = $DB->get_record('user', array('id' => $teacherid), 'firstname, lastname, email');
        } else if ($mailobject->sender == 0) {
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

    /**
     * Get string with recipients for this mail, returns the first recipient
     *
     * @param int $mailid
     * @return string
     */
    function get_recipients_string($mailid) {
        global $USER, $DB;

        $to_mail_arr = $DB->get_records('assignsubmission_mail_to', array('mailid' => $mailid));
        $teacherid = $this->plugininstance->get_config('teacherid');
        $toarr = array();

        foreach ($to_mail_arr as $value) {
            if ($value->contactid == 0) {
                $toarr[] = $DB->get_record('user', array('id' => $teacherid), 'firstname, lastname, email');
            } else if ($value->contactid == TO_STUDENT_ID) {
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
                if ($con) {
                    $firsttoname .= $con->firstname . ' ' . $con->lastname . ' &lt;' . $con->email . '&gt;';
                } else {
                    $firsttoname .= 'MISSING CONTACT';
                }
                if ($commacount < $toarrcount) {
                    $firsttoname .= ', ';
                }
            }
        } else {
            $firsttoname = 'UNSPECIFIED';
        }

        return $firsttoname;
    }

    /**
     * Student mailbox view
     */
    function view_mailbox() {
        global $CFG, $USER, $OUTPUT;

        $route = optional_param('route', 0, PARAM_INT);
        $mid = optional_param('mid', 0, PARAM_INT);
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
                    $link = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $this->cm->id .
                        '&mid=' . $sentmail->id . '&route=' . $route;
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
            $templatemailarr = $this->get_user_mails();
            $mailsignedcount = count($templatemailarr);
            $mailgroupnumber = $this->get_config('mailnumber');
            if ($mailsignedcount < $mailgroupnumber) {
                if (!$templatemailarr) {
                    $this->assign_student_mails();
                } else {
                    $this->assign_student_mails($mailgroupnumber-$mailsignedcount);
                }
                redirect($CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $this->cm->id, '', 0);
            }

            $replyobject = false;
            $titlestr = get_string('inbox', 'assignsubmission_mailsimulator') . ' (' . count($templatemailarr) . ' '
                . get_string('mail', 'assignsubmission_mailsimulator') . ')';

            foreach ($templatemailarr as $mailobj) {
                $nested = $this->get_nested_reply_object($mailobj);

                if ($nested) {
                    $replyobject[] = $nested;
                } else {
                    $message = format_text(unserialize($mailobj->message)['text'], FORMAT_MOODLE);
                    $attachments = $mailobj->attachment>0 ? $this->get_files_str($mailobj->id, 0) : '';
                    $mailobj->message = '<div class="mailmessage">' . $attachments . $message . '</div>';
                    $replyobject[] = $mailobj;
                }
            }

            $replyobject = $this->vsort($replyobject, 'timesent', false);
            $key = null;

            foreach ($replyobject as $k => $m) {
                $link = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mailbox.php?id=' . $this->cm->id . '&mid=' . $m->id;
                $attachment = false;

                if ($m->attachment > 0) {
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

        echo $OUTPUT->header();

        // Mailbox printout.
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
    }

    /**
     * Output the sidebar for student mailbox view
     *
     * @return string
     */
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

    /**
     * Output the topbar for student mailbox view
     *
     * @return string
     */
    function topbar() {
        global $CFG;

        $mid = optional_param('mid', 0, PARAM_INT);
        $link = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/mail.php?id=' . $this->cm->id . '&re=';
        $imgurl = $CFG->wwwroot . '/mod/assign/submission/mailsimulator/pix/';

        if ($mid) {
            $topmenu = '<!-- Start Mail Top Menu-->
                            <table border="0px" >
                                <tr>
                                    <td width="92"></td>
                                    <td >
                                        <a href="' . $link . '1&tid=' . $mid . '" title="' .
                                        get_string('reply', 'assignsubmission_mailsimulator') .
                                        '" onmouseover="document.re.src=\'' . $imgurl . 'button-reply-down.png\'
                                        " onmouseout="document.re.src=\'' .
                                        $imgurl . 'button-reply.png\'">
                                            <img name="re" src="' . $imgurl . 'button-reply.png">
                    </a>
                                    </td>
                                    <td >
                                        <a href="' . $link . '2&tid=' . $mid . '" title="' .
                                        get_string('replyall', 'assignsubmission_mailsimulator') .
                                        '" onmouseover="document.all.src=\'' . $imgurl . 'button-replyall-down.png\'
                                        " onmouseout="document.all.src=\'' .
                                        $imgurl . 'button-replyall.png\'">
                                            <img name="all" src="' . $imgurl . 'button-replyall.png">
                                        </a>
                                    </td>
                                    <td >
                                        <a href="' . $link . '3&tid=' . $mid . '" title="' .
                                        get_string('forward', 'assignsubmission_mailsimulator') .
                                        '" onmouseover="document.fwd.src=\'' . $imgurl . 'button-forward-down.png\'
                                        " onmouseout="document.fwd.src=\'' .
                                        $imgurl . 'button-forward.png\'">
                                            <img name="fwd" src="' . $imgurl . 'button-forward.png">
                                        </a>
                                    </td>
                                    <td width="10">&nbsp;</td>
                                    <td >
                                        <a href="' . $link . '0&tid=0" title="' .
                                        get_string('newmail', 'assignsubmission_mailsimulator') .
                                        '" onmouseover="document.newmail.src=\'' . $imgurl . 'button-newmail-down.png\'
                                        " onmouseout="document.newmail.src=\'' .
                                        $imgurl . 'button-newmail.png\'">
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
                                        <a href="' . $link . '0&tid=0" title="' .
                                        get_string('newmail', 'assignsubmission_mailsimulator') .
                                        '" onmouseover="document.newmail.src=\'' . $imgurl . 'button-newmail-down.png\'
                                        " onmouseout="document.newmail.src=\'' .
                                        $imgurl . 'button-newmail.png\'">
                                            <img name="newmail" src="' . $imgurl . 'button-newmail.png">
                                        </a>
                                    </td>

                                </tr>
                            </table>
                            <!-- End Mail Top Menu-->';
        }

        return $topmenu;
    }

    /**
     * Output the header of the mail in student mailbox view with attachment icons
     *
     * @param stdClass $obj
     * @param string $link
     * @param bool $attachment
     * @return string
     */
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
        $header .= '        <td class="mailheadertd"><table width="100%"><tr><td><strong>' . $from . '</strong></td>';
        $header .= '        <td ' .$datestyle . '>' . date('Y-m-d', $obj->timesent) . '&nbsp;</td></tr></table></td>';
        $header .= '    </tr><tr>';
        $header .= '        <td class="statusimg">' . $statusstr . '</td>';
        $header .= '        <td><strong>' . $this->get_prio_string($obj->priority) . '</strong> ' . $obj->subject . '</td>';
        $header .= '    </tr>';
        $header .= '</table>';

        return $header;
    }

    /**
     * Get the status from user signedout mail: 0 = unread, 1 = read, 2 = replied
     *
     * @param int $mailid
     * @return stdClass
     */
    function get_mail_status($mailid) {
        global $USER, $DB;

        $statusobj = new stdClass();

        for (;;) {
            $statusobj->status = $DB->get_field('assignsubmission_mail_sgndml', 'status', array(
                'mailid' => $mailid,
                'userid' => $USER->id)
            );

            if ($statusobj->status === false) {
                $mailid = $DB->get_field('assignsubmission_mail_mail', 'parent', array('id' => $mailid));
            } else {
                break;
            }
        }
        $statusobj->mailid = $mailid;

        return $statusobj;
    }

    /**
     * Get the status from user signedout mail: 0 = unassigned, 1 = assigned to students
     *
     * @param int $mailid
     * @return bool
     */
    function get_signed_out_status($mailid) {
        global $DB;

        return $DB->record_exists('assignsubmission_mail_sgndml', array('mailid' => $mailid));
    }

    /**
     * Get the image for mail status from user signedout mail: 0 = unread, 1 = read, 2 = replied
     *
     * @param int $status
     * @return string
     */
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

    /**
     * Set the status for user signedout mail: 0 = unread, 1 = read, 2 = replied
     *
     * @param int $mailid
     * @param int $newstatus
     * @return int
     */
    function set_mail_status($mailid, $newstatus) {
        global $USER, $DB;

        $dataobject = new stdClass();
        $dataobject->id = $DB->get_field('assignsubmission_mail_sgndml', 'id', array('mailid' => $mailid, 'userid' => $USER->id));
        $dataobject->status = $newstatus;

        $DB->update_record('assignsubmission_mail_sgndml', $dataobject);

        return $newstatus;
    }

    /**
     * Get the body of the mail sent by student
     *
     * @param stdClass $mailobject
     * @param bool $sentview
     * @return string
     */
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
            $bodystr .= $mailobject->attachment>0 ? $this->get_files_str($mailobject->id, 0) : '';
            $bodystr .= $this->get_nested_from_child($mailobject);
        } else {
            $bodystr .= format_text($mailobject->message, FORMAT_MOODLE);
        }

        return $bodystr;
    }

    /**
     * Get the mails sent by student sorted by timesent
     *
     * @param int $userid
     * @return array
     */
    function get_user_sent($userid=null) {
        global $USER, $DB;

        if (!$userid) {
            $userid = $USER->id;
        }

        return $DB->get_records('assignsubmission_mail_mail', array(
            "userid" => $userid,
            "assignment" => $this->cm->instance
            ), 'timesent DESC');
    }


    /**
     * Insert a new mail and return the id or false
     *
     * @param stdClass $mail
     * @param int $gid
     * @return mixed int id of the inserted mail | bool false if not inserted
     */
    // 
    function insert_mail($mail, $gid=0) {
        global $CFG, $DB;

        // Update submission information only when a student sends a mail.
        $this->update_user_submission($mail->userid);
        $mailid = $DB->insert_record('assignsubmission_mail_mail', $mail);

        if ($mailid) {
            foreach ($mail->to as $to) {
                $obj = new stdClass();
                $obj->contactid = $to;
                $obj->mailid = $mailid;

                $DB->insert_record('assignsubmission_mail_to', $obj);
            }

            return $mailid;
        }

        return false;
    }

    /**
     * Update the given mail in database
     *
     * @param stdClass $mail
     */
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

        $DB->update_record('assignsubmission_mail_mail', $mail);
    }

    /**
     * Function for sorting mails in array
     *
     * @param array $array
     * @param string $id
     * @param bool $sort_ascending
     * @return array
     */
    function vsort($array, $id="id", $sort_ascending=true) {
        $temp_array = array();

        while (count($array) > 0) {
            $lowest_id = 0;
            $index = 0;

            foreach ($array as $item) {
                if (isset($item->$id)) {
                    if ($array[$lowest_id]->$id) {
                        if ($item->$id < $array[$lowest_id]->$id) {
                            $lowest_id = $index;
                        }
                    }
                }
                $index++;
            }

            $temp_array[] = $array[$lowest_id];
            $array = array_merge(array_slice($array, 0, $lowest_id), array_slice($array, $lowest_id + 1));
        }

        if ($sort_ascending) {
            return $temp_array;
        } else {
            return array_reverse($temp_array);
        }
    }

    /**
     * Return a config value for this mailsimulator plugin instance
     *
     * @param string $config
     * @return mixed string|int
     */
    function get_config($config) {
        return $this->plugininstance->get_config($config);
    }

    /**
     * Partial grading view for teacher (feedback giving view). Outputs student's mails and provides tool for teacher
     * to write comments and give weigths. Also used for downloading a student's submission.
     *
     * @param int $userid
     * @param bool $teacher
     * @param bool $download
     */
    function view_grading_feedback($userid=null, $teacher=true, $download=false) {
        global $CFG, $DB, $OUTPUT, $USER, $COURSE;

        if (!$user = $DB->get_record("user", array("id" => $userid))) {
            error("User is misconfigured");
        }

        $submission = $this->user_have_registered_submission($userid, $this->cm->instance);
        $sid = optional_param('sid', $submission->id, PARAM_INT);
        $gid = optional_param('gid', 0, PARAM_INT);

        // If $teacher is set to true, then we need to check that it's really so.
        if ($teacher) {
            $teacher = has_capability('mod/assign:grade', $this->context);
        }

        if ($teacher && !$download) {
            // Update grade/weight.
            if (isset($_POST['submit'])) {
                /* $submission->data1 = $_POST['completion'];*/
                unset($_POST['submit']);
                unset($_POST['completion']);

                foreach ($_POST as $key => $value) {
                    $obj = new stdClass();
                    $karr = explode('_', $key);

                    if ($karr[0] == 'gainedweight') {
                        $obj->id = $karr[1];
                        $obj->$karr[0] = $value;
                        $sarr[$karr[1]] = $obj;
                    }

                    $sarr[$karr[1]]->$karr[0] = $value;
                }

                foreach ($sarr as $dataobject) {
                    $DB->update_record('assignsubmission_mail_sgndml', $dataobject);
                }

                // Update of timemarked and teacher for submission used to be done here. We don't have it anymore.

                echo '<script language="javascript" type="text/javascript">';
                echo '  window.opener.location.reload(true);window.close();';
                echo '</script>';
            }
        }

        // Mails that have been signed out to user.
        $signedoutarr = $this->get_user_mails($userid, true);
        // Mails that the student has sent.
        $newmailarr = $DB->get_records('assignsubmission_mail_mail', array(
            'parent' => 0,
            'userid' => $userid,
            'assignment' => $this->cm->instance
            ));
        // Get replies the student has made on hes own mail.
        $newmailarr = $this->get_recursive_replies($newmailarr, $tmparr, $userid);

        $maxweight = 0;
        $totalgained = 0;

        if ($signedoutarr) {
            foreach ($signedoutarr as $mailobj) {
                $mailobj->id = $mailobj->mailid;
                $mailobj->message = '<div class="mailmessage">' . format_text(unserialize($mailobj->message)['text']) .
                    ($mailobj->attachment>0 ? $this->get_files_str($mailobj->id, $mailobj->userid) : '') . '</div>';
                unset($mailobj->mailid);
                $nestedobj = $this->get_nested_reply_object($mailobj);

                if ($nestedobj) {
                    $mailobj->id = $nestedobj->id;
                    $mailobj->subject = $nestedobj->subject;
                    $mailobj->timesent = $nestedobj->timesent;
                    $mailobj->sender = $nestedobj->sender;
                    $mailobj->message = $nestedobj->message;
                }

                $select = 'parent = ' . $mailobj->id . ' AND userid = ' . $userid . ' AND assignment = ' . $this->cm->instance;
                $mailobj->studentreplys = $DB->get_records_select('assignsubmission_mail_mail', $select);

                if ($mailobj->studentreplys) {
                    $tmp = array();
                    $mailobj->studentreplys = $this->get_recursive_replies($mailobj->studentreplys, $tmp, $userid);
                }

                $maxweight += $mailobj->weight;
                $totalgained += ( $mailobj->gainedweight * $mailobj->weight);
            }
        }

        $show = get_string('show') . ' ' . get_string('teacherid', 'assignsubmission_mailsimulator');
        $hide = get_string('hide') . ' ' . get_string('teacherid', 'assignsubmission_mailsimulator');

        echo '<script language="javascript">';
        echo 'function toggle(showHideDiv, switchText) {';
        echo '  var ele = document.getElementById(showHideDiv);';
        echo '  var text = document.getElementById(switchText);';
        echo '  if(ele.style.display == "block") {';
        echo '      ele.style.display = "none";';
        echo '      text.innerHTML = "' . $show . '";';
        echo '  }';
        echo '  else {';
        echo '      ele.style.display = "block";';
        echo '      text.innerHTML = "' . $hide . '";';
        echo '  }';
        echo '}';
        echo '</script>';

        echo '<table>';
        if ($teacher) {
            $duedate = $DB->get_field('assign', 'duedate', array('id' => $this->cm->instance));
            if ($duedate) {
                echo '  <tr>';
                echo '      <td class="c0">' . get_string('duedate', 'assign') . ':</td>';
                echo '      <td class="c1">' . userdate($duedate) . '</td>';
                echo '  </tr>';
            }

            echo '  <tr>';
            echo '      <td class="c0">' . get_string('lastmodified') . ' (' . get_string('student', 'grades') . '): </td>';
            echo '      <td class="c1">' . userdate($submission->timemodified) . '</td>';
            echo '  </tr>';
            /*
            echo '  <tr>';
            echo '      <td class="c0" style="padding-right: 15px;">' . get_string('lastmodified') .
                ' (' . get_string('defaultcourseteacher'). '):</td>';
            echo '      <td class="c1">' . userdate($submission->timemarked) . '</td>';
            echo '  </tr>';
            */
            if ($download) {
                echo '  <tr>';
                echo '      <td class="c0" style="padding-right: 15px;">' .
                    get_string('printed', 'assignsubmission_mailsimulator') .
                    ' (' . get_string('defaultcourseteacher'). '):</td>';
                echo '      <td class="c1">' . userdate(time()) . '</td>';
                echo '  </tr>';
            }
        }

        echo '  <tr>';
        echo '      <td class="c0">' . get_string('weight_maxweight', 'assignsubmission_mailsimulator') . ':';
        if ($download) {
            echo $OUTPUT->help_icon('weight', 'assignsubmission_mailsimulator') .' ';
        }
        echo '      </td>';
        echo '      <td class="c1">' . $totalgained . '/' . $maxweight * 2 . '</td>';
        echo '  </tr>';
        echo '</table>';

        if ($teacher && !$download) {
            echo '<form name="input" action="' . $CFG->wwwroot . '/mod/assign/view.php?id=' . $this->cm->id . '&sid=' . $sid .
                '&gid=' . $gid . '&plugin=mailsimulator&action=viewpluginassignsubmission&returnaction=grading&returnparams="
                 method="post">';
        }
        if ($signedoutarr) {
            echo $OUTPUT->heading(get_string('studentreplies', 'assignsubmission_mailsimulator'));

            $toggleid = 0;

            foreach ($signedoutarr as $signedoutid => $mobj) {

                $toggleid++;
                $multiplier = $mobj->gainedweight;

                echo '<table style="border:1px solid; margin-bottom: 0px;" width=100%>';
                echo '  <tr style="background:lightgrey">';
                echo '      <td style="width:200px; padding-left:5px; white-space:nowrap;">' .
                    format_text($mobj->subject) . ' </td>';
                echo '      <td><p><a id="showhide' . $toggleid . '" href="javascript:toggle(\'teachermail' . $toggleid .
                    '\',\'showhide' . $toggleid . '\');">' . $show . '</a></p></td>';

                if ($teacher && !$download) {
                    echo '<td style="text-align:right">';

                    echo '<select name="gainedweight_' . $signedoutid . '" >';
                    echo '  <option value="0" ' . (($multiplier == 0) ? 'selected' : '') . '>' .
                        get_string('fail', 'assignsubmission_mailsimulator') . '</option>';
                    echo '  <option value="1" ' . ($multiplier == 1 ? 'selected' : '') . '>' .
                        get_string('ok') . '</option>';
                    echo '  <option value="2" ' . ($multiplier == 2 ? 'selected' : '') . '>' .
                        get_string('good', 'assignsubmission_mailsimulator') . '</option>';
                    echo '</select> ';

                    echo get_string('weight', 'assignsubmission_mailsimulator') . ': ' . $mobj->gainedweight * $mobj->weight .
                        ' / ' . $mobj->weight * 2;
                    echo $OUTPUT->help_icon('weight', 'assignsubmission_mailsimulator');
                    echo '&nbsp; </td>';
                }
                echo '  </tr>';
                echo '</table>';
                echo '<div id="teachermail' . $toggleid . '" style="border:1px solid; border-top: 0px;
                    padding: 5px; padding-bottom: 0px; background-color:#ffffff; display: none;">' .
                    format_text($mobj->message, FORMAT_MOODLE) . '</div>';

                if ($mobj->studentreplys) {
                    echo '<div style="padding: 5px; background:white; border-left:1px solid; border-right:1px solid">';
                    foreach ($mobj->studentreplys as $reply) {
                        echo format_text('<b>' . $reply->subject . '</b><br />' . unserialize($reply->message)['text']) .
                            ($reply->attachment>0 ? $this->get_files_str($reply->id, $reply->userid) : '');
                    }
                    echo '</div>';
                } else {
                    echo $OUTPUT->notification(get_string('noanswer', 'assignsubmission_mailsimulator'));
                }

                if ($teacher && !$download) {
                    echo '<div style="padding: 5px; background:white; color:green; border:1px solid black">' .
                        format_text($mobj->correctiontemplate) . '</div>';
                    echo '<label for="c' . $signedoutid . '">' . get_string('comment', 'assign') . ':</label>';
                    echo '<input id="c' . $signedoutid . '" type="text" name="feedback_' . $signedoutid .
                        '" value="' . $mobj->feedback . '" style="width: 100%;"><br /><br />';
                } else {
                    echo '<div style="padding: 5px; background:white; border:1px solid black"><p style="color:green;">' .
                        get_string('comment', 'assign') . ':</p>' . $mobj->feedback . '</div><br />';
                }
            }
        } else {
            echo get_string('noreplies') . '<br />';
        }

        if ($newmailarr) {
            echo $OUTPUT->heading(get_string('studentnewmails', 'assignsubmission_mailsimulator'));

            foreach ($newmailarr as $mid => $mobj) {
                echo '<div style="padding-left: 5px; background:lightgrey; border:1px solid;">' .
                    format_text($mobj->subject) . '</div>';
                echo '<div style="padding: 5px; background:white; border-left:1px solid; border-right:1px solid;
                    border-bottom:1px solid">';
                echo format_text(unserialize($mobj->message)['text']) .
                    ($mobj->attachment>0 ? $this->get_files_str($mobj->id, $mobj->userid) : '');
                echo '</div><br />';
            }
        } /* else {
            print_heading(get_string('nonewstudentmail', 'assignment_mailsimulator'));
        }
        if ($signedoutarr && $teacher) {
            echo '<br />' . get_string('needcompletion', 'assignsubmission_mailsimulator') . ': ';
            echo '<select name="completion" >';
            echo '  <option value="2" >' . get_string('no') . '</option>';
            echo '  <option value="3" >' . get_string('yes') . '</option>';
           echo '</select> ';
            echo '<br /><input type="submit" value="Submit" name="submit" />';
        }
        */
        if ($teacher && !$download) {
            echo '<br /><center><input type="submit" value="Submit" name="submit" /></center>';
            echo '</form>';
        }
    }

    /**
     * Get replies the student has made on his/her own mail (recursive replies).
     *
     * @param array $arr
     * @param array &$tmp
     * @param int $userid
     * @return array
     */
    function get_recursive_replies($arr, &$tmp, $userid) {
        global $DB;

        if (!$arr) {
            return false;
        }

        foreach ($arr as $key => $value) {
            $select = 'parent = ' . $key . ' AND userid = ' . $userid . ' AND assignment = ' . $this->cm->instance;
            $rearr = $DB->get_records_select('assignsubmission_mail_mail', $select);
            if ($rearr) {
                $this->get_recursive_replies($rearr, $tmp, $userid);
            }
            $tmp[$value->id] = $value;
        }
        return $tmp;
    }

}

