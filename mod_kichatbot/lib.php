<?php
defined('MOODLE_INTERNAL') || die();

function kichatbot_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('kichatbot', $data);
}

function kichatbot_update_instance($data, $mform = null) {
    global $DB;
    $data->id           = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('kichatbot', $data);
}

function kichatbot_delete_instance($id) {
    global $DB;
    $DB->delete_records('kichatbot', ['id' => $id]);
    return true;
}

function kichatbot_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_BACKUP_MOODLE2:    return false;
        case FEATURE_SHOW_DESCRIPTION:  return true;
        default:                        return null;
    }
}
