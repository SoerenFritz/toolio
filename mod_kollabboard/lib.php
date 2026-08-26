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
 * Library functions for Whiteboard plugin
 *
 * @package   mod_whiteboard
 * @copyright 2025 (Your Name/School)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function kollabboard_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('kollabboard', $data);
}

function kollabboard_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('kollabboard', $data);
}

function kollabboard_delete_instance($id) {
    global $DB;
    $DB->delete_records('kollabboard_boards', ['kollabboardid' => $id]);
    return $DB->delete_records('kollabboard', ['id' => $id]);
}

function kollabboard_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Leitet Raum-ID und Ende-zu-Ende-Schlüssel für ein Board deterministisch ab.
 *
 * Gleiche (cmid, groupid) ergeben denselben Raum für alle Teilnehmer, sind aber
 * ohne das serverseitige Geheimnis nicht erratbar. Der Schlüssel wird nur an
 * berechtigte, eingeloggte Nutzer ausgeliefert (siehe view.php).
 *
 * @param int $cmid Course-Module-ID des Boards
 * @param int $groupid Gruppen-ID (0 = gemeinsames Board)
 * @return array{roomid: string, roomkey: string}
 */
function kollabboard_get_room($cmid, $groupid) {
    $secret = get_config('mod_kollabboard', 'roomsecret');
    if (empty($secret)) {
        $secret = bin2hex(random_bytes(32));
        set_config('roomsecret', $secret, 'mod_kollabboard');
    }

    $roomid = substr(bin2hex(hash_hmac('sha256', "id:$cmid:$groupid", $secret, true)), 0, 20);

    // 128-Bit-Schlüssel als base64url ohne Padding (22 Zeichen) – Format, das Excalidraw erwartet.
    $keybytes = substr(hash_hmac('sha256', "key:$cmid:$groupid", $secret, true), 0, 16);
    $roomkey = rtrim(strtr(base64_encode($keybytes), '+/', '-_'), '=');

    return ['roomid' => $roomid, 'roomkey' => $roomkey];
}

/**
 * Registriert einen Raum in der Datenbank, falls noch nicht vorhanden.
 *
 * Nur registrierte Räume werden vom Storage-Endpoint (storage.php) bedient. Die
 * Registrierung erfolgt ausschließlich aus view.php heraus, d.h. durch einen
 * eingeloggten, berechtigten Nutzer – der unauthentifizierte Storage-Endpoint kann
 * so keine beliebigen Räume anlegen.
 *
 * @param string $roomid Abgeleitete Raum-ID
 * @param int $kollabboardid Instanz-ID des Boards (kollabboard.id)
 * @param int $groupid Gruppen-ID (0 = gemeinsames Board)
 * @return void
 */
function kollabboard_register_room($roomid, $kollabboardid, $groupid) {
    global $DB;
    if ($DB->record_exists('kollabboard_boards', ['roomid' => $roomid])) {
        return;
    }
    $now = time();
    $DB->insert_record('kollabboard_boards', (object) [
        'roomid'        => $roomid,
        'kollabboardid' => $kollabboardid,
        'groupid'       => $groupid,
        'sceneversion'  => 0,
        'sceneblob'     => null,
        'savedby'       => 0,
        'timecreated'   => $now,
        'timemodified'  => $now,
    ]);
}
