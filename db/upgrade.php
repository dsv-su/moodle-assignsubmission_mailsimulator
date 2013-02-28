<?php
/**
 * Code for upgrading an existing mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *					Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Stub for upgrade code
 * @param int $oldversion
 * @return bool
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_assignsubmission_mailsimulator_upgrade($oldversion) {
    global $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2013022801) {

        // Define table assignsubmission_mail to be created
        $table = new xmldb_table('assignsubmission_mail');

        // Adding fields to table assignsubmission_mail
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('assignment', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('submission', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('text', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table assignsubmission_mail
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('assignment', XMLDB_KEY_FOREIGN, array('assignment'), 'assign', array('id'));
        $table->add_key('submission', XMLDB_KEY_FOREIGN, array('submission'), 'assignsubmission', array('id'));

        // Conditionally launch create table for assignsubmission_mail
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // mailsimulator savepoint reached
        upgrade_plugin_savepoint(true, 2013022801, 'assignsubmission', 'mailsimulator');
    }

    return true;
}


