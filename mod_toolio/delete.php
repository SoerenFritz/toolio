<?php
/**
 * Unterrichtsstunde (cm) löschen.
 * GET: id=CMID & sesskey=... & returnurl=... [& format=json]
 */
if (!empty($_GET['format']) && $_GET['format'] === 'json') {
    define('AJAX_SCRIPT', true);
}
require('../../config.php');

$cmid      = required_param('id', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$format    = optional_param('format', '', PARAM_ALPHA);

$cm     = get_coursemodule_from_id('toolio', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course);
require_sesskey();
require_capability('moodle/course:manageactivities', context_course::instance($course->id));

// Synchron löschen (false = kein async/cron), danach sofort sauber
course_delete_module($cmid, false);
rebuild_course_cache($course->id, true);

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    die();
}

$back = $returnurl
    ? new moodle_url($returnurl)
    : new moodle_url('/course/view.php', ['id' => $course->id]);

redirect($back);
