<?php
/**
 * AJAX-Endpoint: Aktivität anpinnen / loslösen.
 * GET-Parameter: cmid, action (pin|unpin), sesskey
 */
define('AJAX_SCRIPT', true);
require('../../config.php');

$cmid   = required_param('cmid',   PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
require_sesskey();

$cm      = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
$course  = get_course($cm->course);
$context = context_course::instance($course->id);
require_login($course);
require_capability('moodle/course:manageactivities', $context);

$key     = 'pins_' . $course->id;
$current = get_config('block_toolio', $key);
$pins    = ($current && $current !== '') ? json_decode($current, true) : [];
$pins    = is_array($pins) ? $pins : [];

if ($action === 'pin') {
    if (!in_array($cmid, $pins)) {
        $pins[] = $cmid;
    }
} else {
    $pins = array_values(array_filter($pins, function($id) use ($cmid) { return $id !== $cmid; }));
}

set_config($key, json_encode($pins), 'block_toolio');

echo json_encode(['success' => true, 'pinned' => ($action === 'pin')]);
