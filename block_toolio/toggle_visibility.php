<?php
define('AJAX_SCRIPT', true);
require_once('../../config.php');

$cmid    = required_param('cmid',    PARAM_INT);
$action  = required_param('action',  PARAM_ALPHA); // show | hide
$sesskey = required_param('sesskey', PARAM_RAW);

require_sesskey();

$cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
require_login($cm->course);

$context = context_course::instance($cm->course);
require_capability('moodle/course:manageactivities', $context);

$visible = ($action === 'show') ? 1 : 0;
set_coursemodule_visible($cmid, $visible);
rebuild_course_cache($cm->course, true);

echo json_encode(['success' => true, 'visible' => (bool)$visible]);
