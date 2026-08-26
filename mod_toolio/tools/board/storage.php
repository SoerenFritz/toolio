<?php
// Storage-Endpoint fuer das eingebettete Excalidraw-Board (kitsteam-Fork HTTP-Storage-Vertrag).
//
// Port aus mod_kollabboard/storage.php — jetzt Teil des Toolio-Monolithen.
//
// Sicherheitsmodell (bewusst unauthentifiziert, da cross-origin vom Board aufgerufen):
//  - Der Board-Inhalt ist client-seitig Ende-zu-Ende verschluesselt; hier werden nur
//    opake, verschluesselte Bytes gespeichert (DSGVO: kein Klartext auf dem Server).
//  - Zugriff erfolgt ueber die unerratbare, HMAC-abgeleitete Raum-ID (Capability).
//  - Es werden ausschliesslich Raeume bedient, die zuvor von einem eingeloggten,
//    berechtigten Nutzer registriert wurden (view.php/save.php) — kein Anlegen beliebiger Raeume.
//  - Groessenlimit gegen Missbrauch als Fremdspeicher.

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require('../../../../config.php');

// Maximale Blob-Groesse (Base64-Overhead grosszuegig eingerechnet): 25 MB Rohdaten.
const TOOLIO_BOARD_MAX_BLOB = 26214400;

/**
 * Sendet einen HTTP-Status ohne Body und beendet das Skript.
 *
 * @param int $code HTTP-Statuscode
 * @return never
 */
function toolio_board_storage_finish($code) {
    http_response_code($code);
    exit;
}

// --- CORS -----------------------------------------------------------------
$boardurl = get_config('mod_toolio', 'boardurl');
$allowedorigin = '';
if (!empty($boardurl) && preg_match('#^(https?://[^/]+)#i', $boardurl, $m)) {
    $allowedorigin = $m[1];
}
if ($allowedorigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedorigin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    toolio_board_storage_finish(204);
}

// --- Pfad ermitteln (PATH_INFO, mit Fallback ueber REQUEST_URI) -------------
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
if ($path === '' && isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $script = $_SERVER['SCRIPT_NAME'];
    if ($uri !== null && strpos($uri, $script) === 0) {
        $path = substr($uri, strlen($script));
    }
}

// --- Routing --------------------------------------------------------------
// Szene:  /api/v2/rooms/:roomid
if (preg_match('#^/api/v2/rooms/([A-Za-z0-9_-]{8,64})$#', $path, $m)) {
    $roomid = $m[1];
    $board = $DB->get_record('toolio_board', ['roomid' => $roomid]);
    if (!$board) {
        // Unbekannter (nicht registrierter) Raum – nichts ausliefern/anlegen.
        toolio_board_storage_finish(404);
    }

    if ($method === 'GET') {
        if (empty($board->sceneblob)) {
            // Registriert, aber noch keine Szene → 404 (Client behandelt das als „leer").
            toolio_board_storage_finish(404);
        }
        header('Content-Type: application/octet-stream');
        echo base64_decode($board->sceneblob);
        exit;
    }

    if ($method === 'PUT') {
        $body = file_get_contents('php://input');
        if ($body === false || strlen($body) > TOOLIO_BOARD_MAX_BLOB) {
            toolio_board_storage_finish(413);
        }
        $sceneversion = 0;
        if (strlen($body) >= 4) {
            $parsed = unpack('N', substr($body, 0, 4));
            $sceneversion = $parsed[1];
        }
        $board->sceneblob = base64_encode($body);
        $board->sceneversion = $sceneversion;
        $board->timemodified = time();
        $DB->update_record('toolio_board', $board);
        header('Content-Type: text/plain');
        echo 'ok';
        exit;
    }

    toolio_board_storage_finish(405);
}

// Datei-Timestamp:  /api/v2/files/rooms/:roomid/:fileid/timestamp
if (preg_match('#^/api/v2/files/rooms/([A-Za-z0-9_-]{8,64})/([A-Za-z0-9_.-]{1,128})/timestamp$#', $path, $m)) {
    $roomid = $m[1];
    $fileid = $m[2];
    if (!$DB->record_exists('toolio_board', ['roomid' => $roomid])) {
        toolio_board_storage_finish(404);
    }
    if ($method !== 'PATCH') {
        toolio_board_storage_finish(405);
    }
    $file = $DB->get_record('toolio_board_file', ['roomid' => $roomid, 'fileid' => $fileid]);
    if ($file) {
        $file->timemodified = time();
        $DB->update_record('toolio_board_file', $file);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Datei:  /api/v2/files/rooms/:roomid/:fileid
if (preg_match('#^/api/v2/files/rooms/([A-Za-z0-9_-]{8,64})/([A-Za-z0-9_.-]{1,128})$#', $path, $m)) {
    $roomid = $m[1];
    $fileid = $m[2];
    if (!$DB->record_exists('toolio_board', ['roomid' => $roomid])) {
        toolio_board_storage_finish(404);
    }

    if ($method === 'GET') {
        $file = $DB->get_record('toolio_board_file', ['roomid' => $roomid, 'fileid' => $fileid]);
        if (!$file || empty($file->filedata)) {
            toolio_board_storage_finish(404);
        }
        header('Content-Type: application/octet-stream');
        echo base64_decode($file->filedata);
        exit;
    }

    if ($method === 'PUT') {
        $body = file_get_contents('php://input');
        if ($body === false || strlen($body) > TOOLIO_BOARD_MAX_BLOB) {
            toolio_board_storage_finish(413);
        }
        $now = time();
        $existing = $DB->get_record('toolio_board_file', ['roomid' => $roomid, 'fileid' => $fileid]);
        if ($existing) {
            $existing->filedata = base64_encode($body);
            $existing->timemodified = $now;
            $DB->update_record('toolio_board_file', $existing);
        } else {
            $DB->insert_record('toolio_board_file', (object) [
                'roomid'       => $roomid,
                'fileid'       => $fileid,
                'filedata'     => base64_encode($body),
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
        header('Content-Type: text/plain');
        echo 'ok';
        exit;
    }

    toolio_board_storage_finish(405);
}

toolio_board_storage_finish(404);
