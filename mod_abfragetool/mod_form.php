<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_abfragetool_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('activityname', 'mod_abfragetool'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
