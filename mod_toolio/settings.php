<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'mod_toolio/boardurl',
        get_string('boardurl', 'mod_toolio'),
        get_string('boardurl_desc', 'mod_toolio'),
        '',
        PARAM_URL
    ));
}
