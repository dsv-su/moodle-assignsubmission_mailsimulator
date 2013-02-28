<?php

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG, $DB, $PAGE;

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
        //$this->url = new moodle_url('/mod/assign/submission/mailsimulator/mailbox.php', array("id"=>$cmid));
    }
    
    function view() {
        global $OUTPUT;

        //$teacher = has_capability('mod/assignment:grade', $this->context);

        $readyforgrading = optional_param('readyforgrading', 0, PARAM_INT);

        $this->view_mailbox();
var_dump($this->cm);
        //var_dump($this->assignment->get_instance());
        
       // if ($readyforgrading==1) {$this->submit_for_grading();}

        print_single_button('../../view.php?id=' . $this->cm->id, array('action' => 'savesubmission'),
                    'I am ready for grading', 'post', 'self', false, '', $dissabled);
    }

    function submit_for_grading() {
        var_dump($this->cm);
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
