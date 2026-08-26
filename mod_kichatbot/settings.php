<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'mod_kichatbot/heading',
        get_string('pluginname', 'mod_kichatbot'),
        get_string('pluginnamedesc', 'mod_kichatbot')
    ));

    // OpenAI API-Key -- als Passwortfeld gespeichert.
    // Der Key wird bei jedem Seitenaufruf als window.CHATBOT_API_KEY
    // injiziert. Er ist nur fuer eingeloggte Moodle-Nutzer sichtbar.
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_kichatbot/openaikey',
        get_string('openaikey', 'mod_kichatbot'),
        get_string('openaikeydesc', 'mod_kichatbot'),
        ''
    ));

    // Modell-Bezeichnung (OpenAI- oder OpenRouter-Format)
    $settings->add(new admin_setting_configtext(
        'mod_kichatbot/apimodel',
        get_string('apimodel', 'mod_kichatbot'),
        get_string('apimodeldesc', 'mod_kichatbot'),
        'google/gemma-4-26b-a4b-it:free',
        PARAM_TEXT
    ));

    // API-Basis-URL: OpenAI, OpenRouter oder beliebiger kompatibler Endpunkt.
    $settings->add(new admin_setting_configtext(
        'mod_kichatbot/apibaseurl',
        get_string('apibaseurl', 'mod_kichatbot'),
        get_string('apibaseurldesc', 'mod_kichatbot'),
        'https://api.openai.com/v1',
        PARAM_URL
    ));
}
