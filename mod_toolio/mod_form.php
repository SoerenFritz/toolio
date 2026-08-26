<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_toolio_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // Flash-Unterdrückung: Formular unsichtbar, JS klickt sofort Submit
        $mform->addElement('html', '<style>#page-content,#region-main,.mform,.activity-header{opacity:0!important}</style>');

        $mform->addElement('text', 'name', get_string('activityname', 'mod_toolio'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', get_string('defaultname', 'mod_toolio'));

        $this->standard_coursemodule_elements(); // Pflicht in Moodle 5.1
        $this->add_action_buttons();
    }

    public function definition_after_data() {
        parent::definition_after_data();
        global $PAGE;
        $PAGE->requires->js_init_code('
            document.addEventListener("DOMContentLoaded", function() {
                var btn = document.querySelector("[name=submitbutton2]");
                if (btn) { btn.click(); }
            });
        ');
    }
}
