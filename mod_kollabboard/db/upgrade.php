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
 * Upgrade script for Whiteboard plugin
 *
 * @package   mod_whiteboard
 * @copyright 2025 (Your Name/School)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute whiteboard upgrade from the given old version.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool true if upgrade successful
 */
function xmldb_kollabboard_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026072902) {
        // Tabelle für die persistierte (E2E-verschlüsselte) Szene je Raum.
        $boards = new xmldb_table('kollabboard_boards');
        if (!$dbman->table_exists($boards)) {
            $boards->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $boards->add_field('roomid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $boards->add_field('kollabboardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boards->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boards->add_field('sceneversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boards->add_field('sceneblob', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $boards->add_field('savedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boards->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boards->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boards->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $boards->add_index('roomid', XMLDB_INDEX_UNIQUE, ['roomid']);
            $boards->add_index('kollabboardid', XMLDB_INDEX_NOTUNIQUE, ['kollabboardid']);
            $dbman->create_table($boards);
        }

        // Tabelle für die persistierten (E2E-verschlüsselten) Bild-Dateien je Raum.
        $files = new xmldb_table('kollabboard_files');
        if (!$dbman->table_exists($files)) {
            $files->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $files->add_field('roomid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $files->add_field('fileid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
            $files->add_field('filedata', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $files->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $files->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $files->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $files->add_index('roomfile', XMLDB_INDEX_UNIQUE, ['roomid', 'fileid']);
            $dbman->create_table($files);
        }

        upgrade_mod_savepoint(true, 2026072902, 'kollabboard');
    }

    return true;
}
