<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'mod_kollabboard/boardurl',
        get_string('boardurl', 'mod_kollabboard'),
        get_string('boardurl_desc', 'mod_kollabboard'),
        '',
        PARAM_URL
    ));
}
