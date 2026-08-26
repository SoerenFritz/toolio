<?php
/**
 * Unterrichtsstunde umbenennen (AJAX).
 * GET: id=CMID & name=... & sesskey=...
 * Response: JSON {success, name}
 */
define('AJAX_SCRIPT', true);
require('../../config.php');

$cmid = required_param('id',   PARAM_INT);
$name = required_param('name', PARAM_TEXT);

$cm     = get_coursemodule_from_id('toolio', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course);
require_sesskey();
require_capability('moodle/course:manageactivities', context_course::instance($course->id));

$name = clean_param(trim($name), PARAM_TEXT);
if ($name === '') {
    $name = get_string('defaultname', 'mod_toolio');
}

$DB->set_field('toolio', 'name',         $name,  ['id' => $cm->instance]);
$DB->set_field('toolio', 'timemodified', time(),  ['id' => $cm->instance]);

// Modulinfo-Cache für diesen Kurs invalidieren
rebuild_course_cache($course->id, true);

echo json_encode(['success' => true, 'name' => $name]);
