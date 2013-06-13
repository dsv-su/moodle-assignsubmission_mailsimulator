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
 * Strings for component 'assignsubmission_mailsimulator', language 'en'
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *					Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addalternativemail'] = 'Add an alternative mail';
$string['addalternativemail_help'] = '<p>Each time a new student checkout the assignment for the first time the student is given as a set of random mail, the more alternatives a mail has the less likely it is that to two students receive the same mail.</p><p>This makes it difficult for students to cheat.</p>';
$string['addcontacts'] = 'Add contacts';
$string['addmail'] = 'Add mail';
$string['addnewcontact'] = 'Add a new contact';
$string['addonecontact'] = 'You need to add at least one contact.';
$string['backtostart'] = 'Back to the assignment start page';
$string['contactinuse'] = 'This contact is in use and can therefore not be deleted.';
$string['contact'] = 'Contact';
$string['correctiontemplate'] = 'Correction Template';
$string['correctiontemplateadded'] = 'Correction template {$a} has been added';
$string['correctiontemplateupdated'] = 'Correction template {$a} has been updated';
$string['newcorrectiontemplate'] = 'New correction template';
$string['updatecorrectiontemplate'] = 'Update a correction template / weight';
$string['updatecorrectiontemplate_help'] = 'Sets the changes into the correction template and updates its weight.';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['defaultnumbermails'] = 'Number of mails';
$string['defaultnumbermails_help'] = 'The default value for the \'Number of mails\' setting on the assignment configuration page';
$string['delete'] = 'Delete a mail';
$string['delete_help'] = '<p>Completely removes the mail and its attachments and replies. If the delete button is disabled, it means that the mail is in use and therefore can not be deleted. 
    In order to prevent new students to checkout the mail, use the trash button instead.</p>';
$string['deletecontact'] = 'To delete a contact, clear its three fields.';
$string['enabled'] = 'Mail Simulator';
$string['enabled_help'] = 'If enabled, students are able to work with Mail Simulator.';
$string['err_date'] = 'Later than todays date.';
$string['err_emptymessage'] = 'Message can not be empty.';
$string['err_emptysubject'] = 'Subject can not be empty.';
// $string['err_invalidfile'] = 'Invalid file type, the following filetypes are allowed: ';
$string['err_reciever'] = 'Chose atleast 1 reciever.';
$string['err_template'] = 'Correction template cannot be empty';
$string['fail'] = 'Fail';
//$string['filetypes'] = 'Allowed filetypes';
$string['filesubmissions'] = 'Mail Simulator attachments allowed';
$string['filesubmissions_help'] = 'If enabled, students are able to attach documents.';
$string['forward'] = 'Forward';
$string['fwd'] = 'Fwd: ';
$string['good'] = 'Good';
$string['high'] = 'high';
$string['inbox'] = 'Inbox';
$string['low'] = 'low';
$string['mail'] = 'Mail';
$string['mailssent'] = 'Mails sent:';
$string['mailadmin'] = 'Mail Simulator Teacher View';
$string['mailbox'] = 'Mailbox';
$string['mailboxes'] = 'Mailboxes';
$string['mailsimulator'] = 'Mail Simulator submission';
$string['mailsimulatorfilename'] = 'mailsubmission.html';
$string['mailtostudent'] = 'STUDENT E-MAIL ADRESS';
$string['maxattachments'] = 'Maximum size of attachments';
$string['maxattachments_help'] = 'Sets the maximum size of attachments. By default the course filesize is used.';
$string['maxweight'] = 'Maxweight per mail';
$string['maxweight_help'] = '<p>Sets the maximum weight for a mail within an assignment.</p>
    <p>The weights are used as ratings assistance when grading the assignment.</p>';
$string['medium'] = 'medium';
$string['message'] = 'Message';
$string['newmail'] = 'New Mail';
$string['noanswer'] = 'No Answer';
$string['noreplies'] = 'No mails have been sent';
$string['pluginname'] = 'Mail Simulator';
$string['printed'] = 'Printed';
$string['priority'] = 'Priority';
$string['re'] = 'Re: ';
$string['recieved'] = 'Recieved';
$string['reply'] = 'Reply';
$string['reply_help'] = '<p>By replying to an email, an ongoing email exchange is simulated by two or more fictional characters.</p>';
$string['replyall'] = 'Reply all';
$string['replyall_help'] = 'By clicking this button, an reply will be sent to all contacts included in the converstation.';
$string['rating'] = 'Rating';
$string['returnmailbox'] = 'Return to the mailbox view';
$string['send'] = 'Send';
$string['sent'] = 'Sent';
$string['studentreplies'] = 'Replies:';
$string['studentnewmails'] = 'New mails:';
$string['subject'] = 'Subject';
$string['teacherid'] = 'Teacher mail';
$string['teacherid_help'] = 'This teacher will be added to sender/recipient lists.';
$string['timesent'] = 'Time sent';
$string['trash'] = 'Trash';
$string['trashrestore'] = 'Trash/restore';
$string['trashrestore_help'] = '<p>A mail can be deactivated by adding them in the trash, this means that new students will
    not checkout the mail when they open the assignment.</p><p>To activate an email again, go into the trash and press the
    restore button for the mail.</p>';
$string['weight'] = 'Weight';
$string['weightgiven'] = 'Weight given:';
$string['weight_help'] = "<p>Weights are used to assist in the grading, the weight determines how much a mail is worth.</p>
    <p>If a student has given a Good answer the weight is multiplied by 2, if the answer is OK multiply the weight by 1 and if
    the answer is Fail multiply the weight with 0.</p><p>Example of an assignment with three mail:<br />
    5 (w) x 2 (Good) = 10<br />
    3 (w) x 1 (OK) = 3<br />
    4 (w) x 0 (Fail) = 0<br /><br />
    Total weight is 13 out of 24 possible.</p>";
$string['weight_maxweight'] = 'Weigt/Max';
$string['wrote'] = '{$a} wrote';
