<?php
/**
 * AJAX-Endpoint: liefert den aktuellen Tool-Status (gepinnte Aktivitäten + Sessions)
 * für die Echtzeit-Aktualisierung des Blocks.
 * GET-Parameter: courseid, sesskey
 */
define('AJAX_SCRIPT', true);
require('../../config.php');
require_once(__DIR__ . '/state_lib.php');

$courseid = required_param('courseid', PARAM_INT);
require_sesskey();

$course  = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);

$isTeacher = has_capability('moodle/course:manageactivities', $context);

header('Content-Type: application/json');
echo json_encode(block_toolio_build_state($course, $context, $isTeacher));
