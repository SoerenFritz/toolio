<?php
defined('MOODLE_INTERNAL') || die();

function toolio_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('toolio', $data);
}

function toolio_update_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    return $DB->update_record('toolio', $data);
}

function toolio_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('toolio', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('toolio', ['id' => $id]);
    return true;
}

/**
 * Experiment: FEATURE_MOD_INTRO => false entfernt das Beschreibungsfeld.
 * FEATURE_GROUPS/GROUPINGS => false entfernt die Gruppeneinstellungen.
 * Ergebnis: nur noch das Name-Feld bleibt im Formular.
 */
function toolio_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:        return false;
        case FEATURE_SHOW_DESCRIPTION: return false;
        case FEATURE_GRADE_HAS_GRADE:  return false;
        case FEATURE_GROUPS:           return false;
        case FEATURE_GROUPINGS:        return false;
        default:                       return null;
    }
}
