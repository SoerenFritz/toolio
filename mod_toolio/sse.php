<?php
/**
 * Gemeinsamer SSE-Endpoint für Toolio-Realtime-Updates.
 *
 * Sendet bei Änderungen am Standard-Zyklus ein »update«-Event mit dem kompletten
 * Zustand (JSON). LK und SuS lesen so denselben Stand nahezu in Echtzeit.
 *
 * WICHTIG: Die Session wird sofort nach den Zugriffsprüfungen geschlossen
 * (session_write_close). Sonst hielte die langlebige SSE-Schleife die
 * Session-Sperre und würde alle weiteren Requests derselben Person blockieren
 * (Seite hängt, Speichern kommt nicht durch, keine Echtzeit).
 */

define('NO_OUTPUT_BUFFERING', true);

require('../../config.php');

$id     = required_param('id', PARAM_INT);
$cm     = get_coursemodule_from_id('toolio', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('moodle/course:view', $context);

$toolioid = (int) $cm->instance;
$cycleid  = \mod_toolio\store::get_cycle($toolioid);

// Zugriffsprüfung erledigt — Session-Sperre freigeben, damit parallele Requests
// (Speichern, Navigation) derselben Person nicht blockiert werden.
\core\session\manager::write_close();

// Sämtliche Ausgabepuffer leeren, damit Events sofort beim Client ankommen.
while (ob_get_level() > 0) {
    ob_end_flush();
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

ignore_user_abort(true);
core_php_time_limit::raise(0);

// Kurzlebige Verbindung wie im funktionierenden Beispiel (textsyncsse): nach
// ~25 s beendet der Server den Stream sauber, der Browser (EventSource) baut
// automatisch neu auf. Das hält PHP-Worker frei und verhindert, dass Apache/
// Proxys den langlebigen Stream puffern.
$startedat      = time();
$timeoutseconds = 25;
$lastversion    = -1;

// Engine-Gruppen-Bruecke (wie in view.php): damit LK OFF / Schueleransicht auch bei
// SSE-Updates die im Gruppentool gebildeten Gruppen sehen und nicht auf die
// Moodle-Kursgruppen (»Kurs«-Fallback) zurueckfallen. Lernende einmalig ermitteln.
require_once(__DIR__ . '/tools/gruppentool/state_lib.php');
$learners = toolio_gt_learners($context);

echo ": connected\n\n";
@ob_flush();
flush();

while ((time() - $startedat) < $timeoutseconds) {
    if (connection_aborted()) {
        break;
    }

    // Zyklus nachladen, falls er beim Verbindungsaufbau noch nicht existierte
    // (z. B. SuS öffnet die Seite, bevor die LK etwas eingerichtet hat).
    if ($cycleid === null) {
        $cycleid = \mod_toolio\store::get_cycle($toolioid);
    }
    $state   = ($cycleid !== null) ? \mod_toolio\store::load_gruppentool((int) $cycleid) : null;
    // Kombinierte Version: Zyklus-Store + Engine-Zustand. So loesen auch
    // Gruppenaenderungen im Gruppentool (toolio_gt_state.stateversion) einen Push
    // aus, nicht nur Tool-/Timer-Aenderungen (Zyklus-Store-Version).
    $storeversion = ($state !== null && isset($state['version'])) ? (int) $state['version'] : 0;
    $version      = $storeversion + toolio_gt_state_version((int) $cm->id, $DB);

    if ($version !== $lastversion) {
        $state = toolio_gt_bridge_state($state, (int) $cm->id, $learners, $DB);
        echo "event: update\n";
        echo 'data: ' . json_encode(['version' => $version, 'cmid' => $id, 'state' => $state]) . "\n\n";
        $lastversion = $version;
    } else {
        // Heartbeat in jeder Runde: konstanter Fluss verhindert Proxy-Pufferung.
        echo ': ping ' . time() . "\n\n";
    }

    @ob_flush();
    flush();

    // Zügiges Polling (1 s) für spürbare Echtzeit; das Beispiel nutzt 0,5 s.
    usleep(1000000);
}
