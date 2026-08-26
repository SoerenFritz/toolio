<?php
/**
 * Gruppentool-Engine — SSE-Endpunkt (1:1-Port der Original-Engine mod_gruppentool).
 *
 * Sendet ein Server-Sent-Event `state_version`, sobald sich toolio_gt_state.stateversion
 * erhoeht. public/moodle-socket-adapter.js laedt daraufhin den vollen Zustand ueber ajax.php.
 */

require('../../../../config.php');

@set_time_limit(0);
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');

$id = required_param('id', PARAM_INT);
$sinceversion = optional_param('sinceversion', 0, PARAM_INT);

$cm = get_coursemodule_from_id('toolio', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

global $DB;

$state = $DB->get_record('toolio_gt_state', ['coursemoduleid' => $cm->id]);
if (!$state) {
    $state = (object)[
        'coursemoduleid' => $cm->id,
        'groupcount' => 0,
        'groupmode' => 'groups',
        'groupstableidsjson' => json_encode([]),
        'grouplabelsjson' => json_encode((object)[]),
        'boardstatejson' => null,
        'stateversion' => 0,
        'timemodified' => time(),
    ];
    $state->id = $DB->insert_record('toolio_gt_state', $state);
}

\core\session\manager::write_close();

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$cursor = max(0, (int)$sinceversion);
$startedat = time();
$timeoutseconds = 25;

echo ":connected\n\n";
flush();

while ((time() - $startedat) < $timeoutseconds) {
    if (connection_aborted()) {
        break;
    }

    $state = $DB->get_record('toolio_gt_state', ['coursemoduleid' => $cm->id], 'stateversion', IGNORE_MISSING);
    $currentversion = $state ? (int)$state->stateversion : 0;

    if ($currentversion > $cursor) {
        $cursor = $currentversion;
        echo 'id: ' . $cursor . "\n";
        echo "event: state_version\n";
        echo 'data: ' . json_encode(['version' => $cursor]) . "\n\n";
        flush();
    } else {
        echo ':ping ' . time() . "\n\n";
        flush();
    }

    usleep(500000);
}
