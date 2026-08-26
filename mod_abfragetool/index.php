<?php
require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$PAGE->set_url('/mod/abfragetool/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_abfragetool'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_abfragetool'));

$instances = get_all_instances_in_course('abfragetool', $course);
if (empty($instances)) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_abfragetool')),
        new moodle_url('/course/view.php', ['id' => $id]));
}

$table = new html_table();
$table->head  = [get_string('name')];
$table->align = ['left'];
foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/abfragetool/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name)
    );
    $table->data[] = [$link];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
