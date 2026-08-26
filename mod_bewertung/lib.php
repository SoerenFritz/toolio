<?php
defined('MOODLE_INTERNAL') || die();

function bewertung_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('bewertung', $data);
}

function bewertung_update_instance($data, $mform = null) {
    global $DB;
    $data->id           = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('bewertung', $data);
}

function bewertung_delete_instance($id) {
    global $DB;
    $DB->delete_records('bewertung', ['id' => $id]);
    return true;
}
