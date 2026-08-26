<?php
/**
 * Experiment: Aktivität direkt anlegen ohne Formular.
 *
 * Aufruf: /mod/toolio/create.php?courseid=X&section=Y&sesskey=...
 * Ergebnis: Modul wird mit Defaultwerten angelegt → redirect zu view.php
 */
if (!empty($_GET['format']) && $_GET['format'] === 'json') {
    define('AJAX_SCRIPT', true);
}
require('../../config.php');
require_once($CFG->dirroot . '/course/modlib.php');

$courseid   = required_param('courseid', PARAM_INT);
$sectionnum = optional_param('section', 0, PARAM_INT);
$after      = optional_param('after',   0, PARAM_INT); // cmid nach dem eingefügt wird
$sessionname   = trim(optional_param('sessionname', '', PARAM_TEXT));
$visible_param = optional_param('visible', 1, PARAM_INT);
$pinned_param  = optional_param('pinned',  0, PARAM_INT);
$format        = optional_param('format',  '', PARAM_ALPHA);

$course  = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_sesskey();
require_capability('moodle/course:manageactivities', $context);

// Modul mit Defaultwerten anlegen — kein Formular, kein Nutzer-Input
// Felder entsprechen dem, was standard_coursemodule_elements() normalerweise liefert.
// WICHTIG: add_moduleinfo() in Moodle 5.x erwartet ->module (Integer-ID),
// nicht ->modulename. modedit.php setzt das vor dem Aufruf — wir auch.
$modrecord = $DB->get_record('modules', ['name' => 'toolio'], '*', MUST_EXIST);

$moduleinfo                          = new stdClass();
$moduleinfo->modulename              = 'toolio';
$moduleinfo->module                  = $modrecord->id;
$moduleinfo->course                  = $courseid;
$moduleinfo->section                 = $sectionnum;
$moduleinfo->visible                 = ($visible_param ? 1 : 0);
$moduleinfo->visibleoncoursepage     = 1;
$moduleinfo->name                    = ($sessionname !== '') ? $sessionname : get_string('defaultname', 'mod_toolio');
$moduleinfo->intro                   = '';
$moduleinfo->introformat             = FORMAT_HTML;
$moduleinfo->cmidnumber              = '';
$moduleinfo->groupmode               = NOGROUPS;
$moduleinfo->groupingid              = 0;
$moduleinfo->availability            = null;
$moduleinfo->completion              = COMPLETION_DISABLED;
$moduleinfo->completionview          = COMPLETION_VIEW_NOT_REQUIRED;
$moduleinfo->completionexpected      = 0;
$moduleinfo->completionpassgrade     = 0;
$moduleinfo->completiongradeitemnumber = null;
$moduleinfo->completionunlocked      = 1;
$moduleinfo->showdescription         = 0;
$moduleinfo->downloadcontent         = DOWNLOAD_COURSE_CONTENT_ENABLED;
$moduleinfo->lang                    = '';
$moduleinfo->tags                    = [];

$moduleinfo = add_moduleinfo($moduleinfo, $course);

// Kurs-Modinfo-Cache nach Anlegen immer invalidieren
rebuild_course_cache($courseid, true);

// Position innerhalb der Sektion: nach $after einfügen
if ($after > 0) {
    $section = $DB->get_record('course_sections', [
        'course'  => $courseid,
        'section' => $sectionnum,
    ]);
    if ($section) {
        $newcm = (string)$moduleinfo->coursemodule;
        $seq   = $section->sequence ? explode(',', trim($section->sequence)) : [];
        // newcm ist bereits am Ende (von add_moduleinfo); aus der aktuellen Position entfernen
        $seq   = array_values(array_filter($seq, fn($c) => $c !== $newcm));
        // Nach $after einfügen
        $pos   = array_search((string)$after, $seq);
        if ($pos !== false) {
            array_splice($seq, $pos + 1, 0, [$newcm]);
        } else {
            $seq[] = $newcm; // fallback: Ende
        }
        $DB->set_field('course_sections', 'sequence', implode(',', $seq), ['id' => $section->id]);
        rebuild_course_cache($courseid, true);
    }
}

// Handle pinning if requested
if (!empty($pinned_param)) {
    $pinsKey  = 'pins_' . $courseid;
    $pinsJson = get_config('block_toolio', $pinsKey);
    $pins     = ($pinsJson && $pinsJson !== '') ? json_decode($pinsJson, true) : [];
    $pins     = is_array($pins) ? array_map('intval', $pins) : [];
    $newcmid  = (int)$moduleinfo->coursemodule;
    if (!in_array($newcmid, $pins)) {
        $pins[] = $newcmid;
        set_config($pinsKey, json_encode($pins), 'block_toolio');
    }
}
if (!empty($format) && $format === 'json') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'cmid' => (int)$moduleinfo->coursemodule]);
    die();
}
redirect(new moodle_url('/mod/toolio/view.php', ['id' => $moduleinfo->coursemodule]));
