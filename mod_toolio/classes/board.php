<?php
namespace mod_toolio;

defined('MOODLE_INTERNAL') || die();

/**
 * Board — die Raum-Verwaltung des eingebetteten Kollab-Boards (Port aus mod_kollabboard).
 *
 * Das Board-Werkzeug lebt — wie alle Toolio-Werkzeuge — INNERHALB von mod_toolio
 * (Monolith). Jede Gruppe eines Zyklus erhaelt beim Speichern einen eigenen Raum;
 * die Raum-ID wird deterministisch, aber ohne serverseitiges Geheimnis unerratbar aus
 * (cmid, groupid) abgeleitet. Der Board-Inhalt selbst ist im Frontend Ende-zu-Ende
 * verschluesselt (Excalidraw); der Storage-Endpunkt speichert nur opake Bytes.
 */
class board {

    /**
     * Leitet Raum-ID und Ende-zu-Ende-Schluessel fuer ein Gruppen-Board deterministisch ab.
     *
     * Gleiche (cmid, groupid) ergeben denselben Raum fuer alle Mitglieder, sind aber
     * ohne das serverseitige Geheimnis nicht erratbar. Der Schluessel wird nur an
     * berechtigte, eingeloggte Nutzer ausgeliefert (view.php, require_login).
     *
     * @param int $cmid Course-Module-ID der Toolio-Aktivitaet
     * @param int $groupid Gruppen-Nummer innerhalb des Zyklus (>= 1)
     * @return array{roomid: string, roomkey: string}
     */
    public static function get_room(int $cmid, int $groupid): array {
        $secret = get_config('mod_toolio', 'roomsecret');
        if (empty($secret)) {
            $secret = bin2hex(random_bytes(32));
            set_config('roomsecret', $secret, 'mod_toolio');
        }

        $roomid = substr(bin2hex(hash_hmac('sha256', "id:$cmid:$groupid", $secret, true)), 0, 20);

        // 128-Bit-Schluessel als base64url ohne Padding (22 Zeichen) — Format, das Excalidraw erwartet.
        $keybytes = substr(hash_hmac('sha256', "key:$cmid:$groupid", $secret, true), 0, 16);
        $roomkey  = rtrim(strtr(base64_encode($keybytes), '+/', '-_'), '=');

        return ['roomid' => $roomid, 'roomkey' => $roomkey];
    }

    /**
     * Registriert einen Raum in der Datenbank, falls noch nicht vorhanden.
     *
     * Nur registrierte Raeume werden vom Storage-Endpoint (tools/board/storage.php)
     * bedient. Die Registrierung erfolgt ausschliesslich aus berechtigtem, eingeloggtem
     * Kontext (save.php / view.php) — der unauthentifizierte Storage-Endpoint kann so
     * keine beliebigen Raeume anlegen.
     *
     * @param string $roomid Abgeleitete Raum-ID
     * @param int $cmid Course-Module-ID der Toolio-Aktivitaet
     * @param int $groupid Gruppen-Nummer innerhalb des Zyklus
     * @return void
     */
    public static function register_room(string $roomid, int $cmid, int $groupid): void {
        global $DB;
        if ($DB->record_exists('toolio_board', ['roomid' => $roomid])) {
            return;
        }
        $now = time();
        $DB->insert_record('toolio_board', (object) [
            'roomid'       => $roomid,
            'cmid'         => $cmid,
            'groupid'      => $groupid,
            'sceneversion' => 0,
            'sceneblob'    => null,
            'savedby'      => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }
}
