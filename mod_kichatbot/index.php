<?php
require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);

$PAGE->set_url('/mod/kichatbot/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_kichatbot'));

$cms = get_coursemodules_in_course('kichatbot', $course->id);
if (!$cms) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_kichatbot')),
        new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table          = new html_table();
$table->head    = [get_string('name')];
$table->align   = ['left'];
foreach ($cms as $cm) {
    $link = html_writer::link(
        new moodle_url('/mod/kichatbot/view.php', ['id' => $cm->id]),
        format_string($cm->name)
    );
    $table->data[] = [$link];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
