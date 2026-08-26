<?php
defined('MOODLE_INTERNAL') || die();

function abfragetool_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('abfragetool', $data);
}

function abfragetool_update_instance($data, $mform = null) {
    global $DB;
    $data->id           = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('abfragetool', $data);
}

function abfragetool_delete_instance($id) {
    global $DB;
    $DB->delete_records('abfragetool', ['id' => $id]);
    return true;
}
