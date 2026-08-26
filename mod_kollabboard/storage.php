<?php
// Storage-Endpoint für das kitsteam-Excalidraw-Frontend (HTTP-Storage-Backend-Vertrag).
//
// Sicherheitsmodell (bewusst unauthentifiziert, da cross-origin vom Board aufgerufen):
//  - Der Board-Inhalt ist client-seitig Ende-zu-Ende verschlüsselt; hier werden nur
//    opake, verschlüsselte Bytes gespeichert (DSGVO: kein Klartext auf dem Server).
//  - Zugriff erfolgt über die unerratbare, HMAC-abgeleitete Raum-ID (Capability).
//  - Es werden ausschliesslich Raeume bedient, die zuvor von einem eingeloggten,
//    berechtigten Nutzer in view.php registriert wurden (kein Anlegen beliebiger Raeume).
//  - Größenlimit gegen Missbrauch als Fremdspeicher.

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require('../../config.php');

// Maximale Blob-Größe (Base64-Overhead eingerechnet großzügig): 25 MB Rohdaten.
const KOLLABBOARD_MAX_BLOB = 26214400;

/**
 * Sendet einen HTTP-Status ohne Body und beendet das Skript.
 *
 * @param int $code HTTP-Statuscode
 * @return never
 */
function kollabboard_storage_finish($code) {
    http_response_code($code);
    exit;
}

// --- CORS -----------------------------------------------------------------
$boardurl = get_config('mod_kollabboard', 'boardurl');
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
    kollabboard_storage_finish(204);
}

// --- Pfad ermitteln (PATH_INFO, mit Fallback über REQUEST_URI) -------------
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
    $board = $DB->get_record('kollabboard_boards', ['roomid' => $roomid]);
    if (!$board) {
        // Unbekannter (nicht registrierter) Raum – nichts ausliefern/anlegen.
        kollabboard_storage_finish(404);
    }

    if ($method === 'GET') {
        if (empty($board->sceneblob)) {
            // Registriert, aber noch keine Szene → 404 (Client behandelt das als „leer").
            kollabboard_storage_finish(404);
        }
        header('Content-Type: application/octet-stream');
        echo base64_decode($board->sceneblob);
        exit;
    }

    if ($method === 'PUT') {
        $body = file_get_contents('php://input');
        if ($body === false || strlen($body) > KOLLABBOARD_MAX_BLOB) {
            kollabboard_storage_finish(413);
        }
        $sceneversion = 0;
        if (strlen($body) >= 4) {
            $parsed = unpack('N', substr($body, 0, 4));
            $sceneversion = $parsed[1];
        }
        $board->sceneblob = base64_encode($body);
        $board->sceneversion = $sceneversion;
        $board->timemodified = time();
        $DB->update_record('kollabboard_boards', $board);
        header('Content-Type: text/plain');
        echo 'ok';
        exit;
    }

    kollabboard_storage_finish(405);
}

// Datei-Timestamp:  /api/v2/files/rooms/:roomid/:fileid/timestamp
if (preg_match('#^/api/v2/files/rooms/([A-Za-z0-9_-]{8,64})/([A-Za-z0-9_.-]{1,128})/timestamp$#', $path, $m)) {
    $roomid = $m[1];
    $fileid = $m[2];
    if (!$DB->record_exists('kollabboard_boards', ['roomid' => $roomid])) {
        kollabboard_storage_finish(404);
    }
    if ($method !== 'PATCH') {
        kollabboard_storage_finish(405);
    }
    $file = $DB->get_record('kollabboard_files', ['roomid' => $roomid, 'fileid' => $fileid]);
    if ($file) {
        $file->timemodified = time();
        $DB->update_record('kollabboard_files', $file);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Datei:  /api/v2/files/rooms/:roomid/:fileid
if (preg_match('#^/api/v2/files/rooms/([A-Za-z0-9_-]{8,64})/([A-Za-z0-9_.-]{1,128})$#', $path, $m)) {
    $roomid = $m[1];
    $fileid = $m[2];
    if (!$DB->record_exists('kollabboard_boards', ['roomid' => $roomid])) {
        kollabboard_storage_finish(404);
    }

    if ($method === 'GET') {
        $file = $DB->get_record('kollabboard_files', ['roomid' => $roomid, 'fileid' => $fileid]);
        if (!$file || empty($file->filedata)) {
            kollabboard_storage_finish(404);
        }
        header('Content-Type: application/octet-stream');
        echo base64_decode($file->filedata);
        exit;
    }

    if ($method === 'PUT') {
        $body = file_get_contents('php://input');
        if ($body === false || strlen($body) > KOLLABBOARD_MAX_BLOB) {
            kollabboard_storage_finish(413);
        }
        $now = time();
        $existing = $DB->get_record('kollabboard_files', ['roomid' => $roomid, 'fileid' => $fileid]);
        if ($existing) {
            $existing->filedata = base64_encode($body);
            $existing->timemodified = $now;
            $DB->update_record('kollabboard_files', $existing);
        } else {
            $DB->insert_record('kollabboard_files', (object) [
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

    kollabboard_storage_finish(405);
}

kollabboard_storage_finish(404);
