<?php
/**
 * Leichtgewichtiger Poll-Endpunkt für Toolio-Realtime (Fallback zu sse.php).
 *
 * Manche Server-Setups (Apache + php-fpm hinter einem Proxy) puffern langlebige
 * SSE-Streams, sodass Updates erst beim Reload ankommen. Dieser Endpunkt liefert
 * bei jedem Aufruf den aktuellen versionierten Zustand als JSON. Der Client fragt
 * ihn im Sekundentakt ab und wendet nur echte Versionswechsel an — so sehen SuS
 * Änderungen auch dann nahezu in Echtzeit, wenn SSE geblockt/gepuffert wird.
 *
 * Nur-Lesen: jeder mit Zugriff auf die Aktivität (require_login) darf den Stand abrufen.
 */

define('AJAX_SCRIPT', true);

require('../../config.php');

$id     = required_param('id', PARAM_INT); // cmid
$cm     = get_coursemodule_from_id('toolio', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);

$context = context_module::instance($cm->id);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$cycleid = \mod_toolio\store::get_cycle((int) $cm->instance);
$state   = ($cycleid !== null) ? \mod_toolio\store::load_gruppentool((int) $cycleid) : null;

// Uebermittlung ERST nach dem Speichern: nur der gespeicherte Zyklus-Store zaehlt,
// NICHT die Live-Engine (toolio_gt_state.stateversion). Kein Live-Bridge — der beim
// Speichern in save.php eingefrorene Snapshot ist maßgeblich. So sehen LK OFF/SuS
// Gruppen-/Tool-Aenderungen erst, wenn die LK "Aenderungen speichern" geklickt hat.
$version = ($state !== null && isset($state['version'])) ? (int) $state['version'] : 0;

echo json_encode(['version' => $version, 'state' => $state]);
