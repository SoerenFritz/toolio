<?php
/**
 * Schreib-Endpunkt des Gruppentools (Weg-A-Durchstich).
 *
 * Nimmt die von der Lehrkraft (🟢 LK ON) eingeteilte Sozialform + Gruppen entgegen und
 * persistiert sie als versionierten JSON-Blob am Standard-Zyklus. Nur LK mit
 * moodle/course:manageactivities darf schreiben. Antwortet als JSON.
 */

define('AJAX_SCRIPT', true);

require('../../config.php');

/**
 * Erzeugt/registriert pro Gruppe einen eingebetteten Board-Raum und liefert die Zuordnung.
 *
 * Das Board ist ein Toolio-Werkzeug im Monolithen (\mod_toolio\board), kein externes
 * Plugin. Jede gespeicherte Gruppe erhaelt einen eigenen, unerratbaren Raum.
 *
 * @param int $cmid Toolio-Coursemodule-ID
 * @param array $groups Bereinigte Gruppenliste aus dem Gruppentool-State
 * @return array[]
 */
function toolio_prepare_boardrooms(int $cmid, array $groups): array {
    $rooms = [];
    try {
        foreach ($groups as $idx => $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupid = $idx + 1;
            $room = \mod_toolio\board::get_room($cmid, $groupid);
            \mod_toolio\board::register_room((string) $room['roomid'], $cmid, $groupid);
            $rooms[] = [
                'groupid'   => $groupid,
                'groupname' => (string) ($group['name'] ?? ('Gruppe ' . $groupid)),
                'memberids' => array_map('intval', (array) ($group['memberids'] ?? [])),
                'roomid'    => (string) $room['roomid'],
                'roomkey'   => (string) $room['roomkey'],
            ];
        }
    } catch (\Throwable $e) {
        return [];
    }

    return $rooms;
}

$id      = required_param('id', PARAM_INT);      // cmid
$tool    = required_param('tool', PARAM_ALPHA);
$payload = required_param('payload', PARAM_RAW);  // JSON-String, wird unten bereinigt

$cm      = get_coursemodule_from_id('toolio', $id, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);

header('Content-Type: application/json; charset=utf-8');

// Leichtgewichtiger Timer-Push (LK OFF): schreibt nur den Timer-Zustand in den
// bestehenden Gruppentool-State und bumpt die Version, damit SuS ihn per SSE erhalten.
if ($tool === 'timer') {
    $cycleid  = \mod_toolio\store::get_cycle((int) $cm->instance);
    $existing = ($cycleid !== null) ? \mod_toolio\store::load_gruppentool($cycleid) : null;
    if ($existing === null) {
        echo json_encode(['ok' => false, 'error' => 'no activity']);
        die;
    }
    $data    = json_decode($payload, true);
    $running = is_array($data) && !empty($data['running']);
    $seconds = is_array($data) ? max(0, min(360000, (int) ($data['seconds'] ?? 0))) : 0;
    unset($existing['version']);
    $existing['timer'] = ['running' => $running, 'seconds' => $seconds];
    $version = \mod_toolio\store::save_gruppentool($cycleid, $existing);
    echo json_encode(['ok' => true, 'version' => $version]);
    die;
}

// Bearbeiten/Sichern-Switch (LK OFF): schreibt nur den frozen-Stand in den bestehenden
// Gruppentool-State und bumpt die Version, damit SuS ihn per Poll erhalten und ihr Board
// gesperrt (read-only) bekommen. Nur LK (Capability oben geprueft) darf das setzen.
if ($tool === 'frozen') {
    $cycleid  = \mod_toolio\store::get_cycle((int) $cm->instance);
    $existing = ($cycleid !== null) ? \mod_toolio\store::load_gruppentool($cycleid) : null;
    if ($existing === null) {
        echo json_encode(['ok' => false, 'error' => 'no activity']);
        die;
    }
    $data   = json_decode($payload, true);
    $frozen = is_array($data) && !empty($data['frozen']);
    unset($existing['version']);
    $existing['frozen'] = $frozen;
    $version = \mod_toolio\store::save_gruppentool($cycleid, $existing);
    echo json_encode(['ok' => true, 'version' => $version]);
    die;
}

// Abfrage-Konfiguration (Fragen + Einstellungen) – wird als 'abfrage'-Schlüssel in den
// bestehenden State-Blob eingebettet, damit das JSON einer Toolio-Aktivität vollständig
// exportierbar/importierbar bleibt (ADR-0003 Verkettung).
if ($tool === 'abfrage') {
    $cycleid  = \mod_toolio\store::get_cycle((int) $cm->instance);
    $existing = ($cycleid !== null) ? \mod_toolio\store::load_gruppentool($cycleid) : null;
    if ($existing === null) {
        $existing = [];
    }
    $data = json_decode($payload, true);
    if (!is_array($data)) {
        echo json_encode(['ok' => false, 'error' => 'invalid payload']);
        die;
    }
    $allowedtypes = ['choice', 'text', 'rating', 'scale', 'likert'];
    $allowedviz   = ['bar', 'pie', 'wordcloud', 'none'];
    $questions = [];
    foreach (($data['questions'] ?? []) as $q) {
        if (!is_array($q)) {
            continue;
        }
        $options = [];
        foreach (($q['options'] ?? []) as $opt) {
            $t = clean_param((string) $opt, PARAM_TEXT);
            if ($t !== '') {
                $options[] = $t;
            }
        }
        $qtype = in_array((string) ($q['type'] ?? ''), $allowedtypes, true)
            ? (string) $q['type'] : 'choice';
        $qviz  = in_array((string) ($q['viz'] ?? ''), $allowedviz, true)
            ? (string) $q['viz'] : 'bar';
        $questions[] = [
            'type'     => $qtype,
            'text'     => clean_param((string) ($q['text'] ?? ''), PARAM_TEXT),
            'required' => !empty($q['required']),
            'options'  => $options,
            'viz'      => $qviz,
        ];
    }
    unset($existing['version']);
    $existing['abfrage'] = [
        'title'     => clean_param((string) ($data['title'] ?? ''), PARAM_TEXT),
        'questions' => $questions,
        'settings'  => [
            'anonymous' => !empty($data['settings']['anonymous'] ?? false),
            'timer'     => max(0, min(120, (int) ($data['settings']['timer'] ?? 0))),
        ],
        'active'    => !empty($data['active']),
    ];
    if ($cycleid === null) {
        $cycleid = \mod_toolio\store::ensure_default_cycle((int) $cm->instance);
    }
    $version = \mod_toolio\store::save_gruppentool($cycleid, $existing);
    echo json_encode(['ok' => true, 'version' => $version]);
    die;
}

if ($tool !== 'gruppen') {
    echo json_encode(['ok' => false, 'error' => 'unknown tool']);
    die;
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    echo json_encode(['ok' => false, 'error' => 'invalid payload']);
    die;
}

// Vorhandenen Abfrage-State aus DB sichern — wird am Ende wieder eingesetzt,
// da tool=gruppen ihn nie überschreibt (Trennung der Verantwortlichkeiten).
$_preExistingCycleid = \mod_toolio\store::get_cycle((int) $cm->instance);
$_preExistingState   = ($_preExistingCycleid !== null)
    ? \mod_toolio\store::load_gruppentool((int) $_preExistingCycleid)
    : null;
$_preservedAbfrage   = (is_array($_preExistingState) && isset($_preExistingState['abfrage']))
    ? $_preExistingState['abfrage']
    : null;

// Nur bekannte Felder übernehmen — kein beliebiges JSON in die DB.
$allowedforms = ['einzel', 'paar', 'gruppe', 'plenum'];
$allowedtools = ['none', 'gruppen', 'board', 'chatbot', 'abfrage'];
$clean = [
    'methodid'   => clean_param((string) ($data['methodid'] ?? ''), PARAM_ALPHANUMEXT),
    'methodlabel'=> clean_param((string) ($data['methodlabel'] ?? ''), PARAM_TEXT),
    'methodsummary' => clean_param((string) ($data['methodsummary'] ?? ''), PARAM_TEXT),
    'materials'  => [],
    'tool'       => in_array((string) ($data['tool'] ?? ''), $allowedtools, true)
        ? (string) $data['tool'] : 'gruppen',
    'sozialform' => in_array(($data['sozialform'] ?? ''), $allowedforms, true)
        ? $data['sozialform'] : 'gruppe',
    'count'      => max(1, min(12, (int) ($data['count'] ?? 1))),
    'groups'     => [],
    'board'      => null,
    'boardrooms' => [],
];
foreach (($data['materials'] ?? []) as $item) {
    $value = clean_param((string) $item, PARAM_TEXT);
    if ($value !== '') {
        $clean['materials'][] = $value;
    }
}
foreach (($data['groups'] ?? []) as $g) {
    if (!is_array($g)) {
        continue;
    }
    $members = [];
    foreach (($g['members'] ?? []) as $m) {
        $name = clean_param((string) $m, PARAM_TEXT);
        if ($name !== '') {
            $members[] = $name;
        }
    }
    $memberids = [];
    foreach (($g['memberids'] ?? []) as $mid) {
        $memberids[] = (int) $mid;
    }
    $clean['groups'][] = [
        'id'        => clean_param((string) ($g['id'] ?? ''), PARAM_ALPHANUMEXT),
        'name'      => clean_param((string) ($g['name'] ?? ''), PARAM_TEXT),
        'members'   => $members,
        'memberids' => $memberids,
    ];
}

// Vollständiger Whiteboard-Zustand des Gruppentools (Positionen, Aktiv-Status, Modus).
// Wird als JSON im selben State abgelegt — keine eigene Tabelle nötig.
if (isset($data['board']) && is_array($data['board'])) {
    $b     = $data['board'];
    $parts = [];
    foreach (($b['participants'] ?? []) as $p) {
        if (!is_array($p)) {
            continue;
        }
        $gid = $p['groupId'] ?? null;
        $parts[] = [
            'id'      => (int) ($p['id'] ?? 0),
            'name'    => clean_param((string) ($p['name'] ?? ''), PARAM_TEXT),
            'active'  => !empty($p['active']),
            'groupId' => ($gid !== null && $gid !== '')
                ? clean_param((string) $gid, PARAM_ALPHANUMEXT) : null,
            'x'       => (isset($p['x']) && $p['x'] !== null) ? max(0.0, min(1.0, (float) $p['x'])) : null,
            'y'       => (isset($p['y']) && $p['y'] !== null) ? max(0.0, min(1.0, (float) $p['y'])) : null,
        ];
    }
    $labels = [];
    foreach (($b['labels'] ?? []) as $key => $value) {
        $lk = clean_param((string) $key, PARAM_ALPHANUMEXT);
        if ($lk !== '') {
            $labels[$lk] = clean_param((string) $value, PARAM_TEXT);
        }
    }
    $anchors = [];
    foreach (($b['anchors'] ?? []) as $key => $pos) {
        $ak = clean_param((string) $key, PARAM_ALPHANUMEXT);
        if ($ak !== '' && is_array($pos)) {
            $anchors[$ak] = [
                'x' => max(0.0, min(1.0, (float) ($pos['x'] ?? 0))),
                'y' => max(0.0, min(1.0, (float) ($pos['y'] ?? 0))),
            ];
        }
    }
    $clean['board'] = [
        'mode'         => (($b['mode'] ?? '') === 'partner') ? 'partner' : 'groups',
        'groupCount'   => max(0, min(50, (int) ($b['groupCount'] ?? 0))),
        'labels'       => $labels,
        'anchors'      => $anchors,
        'participants' => $parts,
    ];
}

// Autoritativer Snapshot: die aktuell in der Gruppentool-Engine (toolio_gt_*) gebildete
// Aufteilung wird beim Speichern eingefroren — so wird die Aenderung ERST beim Speichern
// an LK OFF/SuS uebermittelt (poll.php liefert nur diesen gespeicherten Stand).
require_once(__DIR__ . '/tools/gruppentool/state_lib.php');
$gtsnap = toolio_gt_compute_result((int) $cm->id, toolio_gt_learners($context), $DB);
if ($gtsnap['hasdata']) {
    $snapgroups = [];
    foreach ($gtsnap['groups'] as $g) {
        $snapgroups[] = [
            'id'        => (string) ($g['id'] ?? ''),
            'name'      => (string) $g['name'],
            'members'   => $g['students'],
            'memberids' => $g['studentids'],
        ];
    }
    $clean['groups']     = $snapgroups;
    $clean['sozialform'] = $gtsnap['sozialform'];
}

// Wenn Board aktiv ist, bekommt jede gespeicherte Gruppe einen eigenen Raum.
if ($clean['tool'] === 'board' && !empty($clean['groups'])) {
    $clean['boardrooms'] = toolio_prepare_boardrooms((int) $cm->id, $clean['groups']);
}

$cycleid = \mod_toolio\store::ensure_default_cycle((int) $cm->instance);

// Abfrage-State aus vorherigem DB-Stand einfügen (wird nur via tool=abfrage geändert).
if ($_preservedAbfrage !== null) {
    $clean['abfrage'] = $_preservedAbfrage;
}

$version = \mod_toolio\store::save_gruppentool($cycleid, $clean);
$state   = \mod_toolio\store::load_gruppentool($cycleid);

echo json_encode(['ok' => true, 'version' => $version, 'state' => $state]);
