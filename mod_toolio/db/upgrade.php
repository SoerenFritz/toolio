<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_toolio_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026062601) {
        $table = new xmldb_table('toolio');

        $field = new xmldb_field('method', XMLDB_TYPE_CHAR, '50', null, false, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field2 = new xmldb_field('islive', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'method');
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        upgrade_mod_savepoint(true, 2026062601, 'toolio');
    }

    if ($oldversion < 2026072900) {
        // ADR-0003: relationales Rueckgrat (Zyklus-Kette & Verkettung) + Gruppentool-State.
        $tables = [];

        $t = new xmldb_table('toolio_cycle');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('toolioid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $t->add_field('ordinal', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('method', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $t->add_field('sozialform', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $t->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'draft');
        $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_key('toolioid', XMLDB_KEY_FOREIGN, ['toolioid'], 'toolio', ['id']);
        $t->add_index('toolioid-ordinal', XMLDB_INDEX_NOTUNIQUE, ['toolioid', 'ordinal']);
        $tables[] = $t;

        $t = new xmldb_table('toolio_cycle_tool');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('cycleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $t->add_field('tool', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $t->add_field('ordinal', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_key('cycleid', XMLDB_KEY_FOREIGN, ['cycleid'], 'toolio_cycle', ['id']);
        $t->add_index('cycleid-ordinal', XMLDB_INDEX_NOTUNIQUE, ['cycleid', 'ordinal']);
        $tables[] = $t;

        $t = new xmldb_table('toolio_snapshot');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('cycleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $t->add_field('tool', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $t->add_field('ownertype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'group');
        $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $t->add_field('groupno', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $t->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $t->add_field('format', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'toolio.v1');
        $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_key('cycleid', XMLDB_KEY_FOREIGN, ['cycleid'], 'toolio_cycle', ['id']);
        $t->add_index('cycleid-tool', XMLDB_INDEX_NOTUNIQUE, ['cycleid', 'tool']);
        $tables[] = $t;

        $t = new xmldb_table('toolio_cycle_input');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('cycleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $t->add_field('snapshotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $t->add_field('ordinal', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_key('cycleid', XMLDB_KEY_FOREIGN, ['cycleid'], 'toolio_cycle', ['id']);
        $t->add_key('snapshotid', XMLDB_KEY_FOREIGN, ['snapshotid'], 'toolio_snapshot', ['id']);
        $t->add_index('cycleid-ordinal', XMLDB_INDEX_NOTUNIQUE, ['cycleid', 'ordinal']);
        $tables[] = $t;

        $t = new xmldb_table('toolio_gruppentool_state');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('cycleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $t->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_key('cycleid', XMLDB_KEY_FOREIGN_UNIQUE, ['cycleid'], 'toolio_cycle', ['id']);
        $tables[] = $t;

        foreach ($tables as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }

        upgrade_mod_savepoint(true, 2026072900, 'toolio');
    }

    if ($oldversion < 2026080400) {
        // Gruppentool-Engine (1:1-Port): plugin-eigene relationale Tabellen fuer den
        // Live-Zustand, damit teacher.js/student.js unveraendert laufen.
        $tables = [];

        $t = new xmldb_table('toolio_gt_member');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('coursemoduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $t->add_field('groupid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $t->add_field('groupstableid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $t->add_field('grouporder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('groupmode', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $t->add_field('canvasx', XMLDB_TYPE_NUMBER, '10, 6', null, null, null, null);
        $t->add_field('canvasy', XMLDB_TYPE_NUMBER, '10, 6', null, null, null, null);
        $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_index('cm_user_uix', XMLDB_INDEX_UNIQUE, ['coursemoduleid', 'userid']);
        $t->add_index('cm_group_idx', XMLDB_INDEX_NOTUNIQUE, ['coursemoduleid', 'groupid']);
        $tables[] = $t;

        $t = new xmldb_table('toolio_gt_state');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('coursemoduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('groupcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('groupmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'groups');
        $t->add_field('groupstableidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $t->add_field('grouplabelsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $t->add_field('boardstatejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $t->add_field('stateversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_index('cm_uix', XMLDB_INDEX_UNIQUE, ['coursemoduleid']);
        $tables[] = $t;

        foreach ($tables as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }

        upgrade_mod_savepoint(true, 2026080400, 'toolio');
    }

    if ($oldversion < 2026082300) {
        // Board-Werkzeug in mod_toolio eingebettet (Port aus mod_kollabboard): ein Board
        // je Gruppe. Raum- und Datei-Persistenz als E2E-verschluesselte Blobs.
        $tables = [];

        $t = new xmldb_table('toolio_board');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('roomid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $t->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('sceneversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('sceneblob', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $t->add_field('savedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_index('roomid', XMLDB_INDEX_UNIQUE, ['roomid']);
        $t->add_index('cmid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        $tables[] = $t;

        $t = new xmldb_table('toolio_board_file');
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('roomid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $t->add_field('fileid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $t->add_field('filedata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $t->add_index('roomfile', XMLDB_INDEX_UNIQUE, ['roomid', 'fileid']);
        $tables[] = $t;

        foreach ($tables as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }

        upgrade_mod_savepoint(true, 2026082300, 'toolio');
    }

    return true;
}
