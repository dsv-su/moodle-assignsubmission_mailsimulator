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

    return true;
}


