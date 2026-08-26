<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Instance add/edit form for Whiteboard plugin
 *
 * @package   mod_whiteboard
 * @copyright 2025 (Your Name/School)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_kollabboard_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('pluginname', 'mod_kollabboard'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$defaultvalues) {
        if ($this->current->instance) {
            global $DB;
            $record = $DB->get_record('kollabboard', ['id' => $this->current->instance]);
            if ($record) {
                $defaultvalues['name'] = $record->name;
                $defaultvalues['intro'] = $record->intro;
                $defaultvalues['introformat'] = $record->introformat;
            }
        }
    }
}