<?php
require('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('kollabboard', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$kollabboard = $DB->get_record('kollabboard', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$PAGE->set_url('/mod/kollabboard/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($kollabboard->name));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/mod/kollabboard/style.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($kollabboard->name));

if (!empty($kollabboard->intro)) {
    echo $OUTPUT->box(format_module_intro('kollabboard', $kollabboard, $cm->id), 'generalbox', 'intro');
}

$boardurl = get_config('mod_kollabboard', 'boardurl');

if (empty($boardurl)) {
    echo $OUTPUT->notification(get_string('boardurl_missing', 'mod_kollabboard'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// Aktive Gruppe der Aktivität ermitteln (0 = keine Gruppen / alle Teilnehmer).
$groupid = groups_get_activity_group($cm) ?: 0;

$room = kollabboard_get_room($cm->id, $groupid);

// Raum registrieren, damit der (unauthentifizierte) Storage-Endpoint ihn bedient.
kollabboard_register_room($room['roomid'], $cm->instance, $groupid);

// Anzeigename der/des Nutzenden aus Moodle, damit auf dem Board echte Namen
// statt Excalidraw-Zufallsnamen erscheinen (per URL-Query an den Fork übergeben).
$displayname = fullname($USER);

// Der Excalidraw-Editor liegt unter /app; Name per Query, Raum per URL-Fragment.
$boardsrc = rtrim($boardurl, '/') . '/app?username=' . rawurlencode($displayname)
    . '#room=' . $room['roomid'] . ',' . $room['roomkey'];

echo html_writer::start_div('kollabboard-frame-wrap');
echo html_writer::tag('button', get_string('fullscreen', 'mod_kollabboard'), [
    'id'    => 'kollabboard-fs-btn',
    'class' => 'kollabboard-fullscreen-btn btn btn-secondary btn-sm',
    'type'  => 'button',
]);
echo html_writer::tag('iframe', '', [
    'src'             => $boardsrc,
    'title'           => format_string($kollabboard->name),
    'class'           => 'kollabboard-frame',
    'id'              => 'kollabboard-iframe',
    'allow'           => 'clipboard-read; clipboard-write; fullscreen',
    'allowfullscreen' => 'allowfullscreen',
]);
echo html_writer::end_div();

$PAGE->requires->js_init_code("
(function() {
    var btn    = document.getElementById('kollabboard-fs-btn');
    var iframe = document.getElementById('kollabboard-iframe');
    var labelEnter = " . json_encode(get_string('fullscreen', 'mod_kollabboard')) . ";
    var labelExit  = " . json_encode(get_string('exitfullscreen', 'mod_kollabboard')) . ";
    document.addEventListener('fullscreenchange', function() {
        btn.textContent = document.fullscreenElement ? labelExit : labelEnter;
    });
    btn.addEventListener('click', function() {
        if (!document.fullscreenElement) {
            iframe.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    });
})();
");

echo $OUTPUT->footer();
