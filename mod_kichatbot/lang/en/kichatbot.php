<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']           = 'KI-Chatbot';
$string['modulename']           = 'KI-Chatbot';
$string['modulenameplural']     = 'KI-Chatbots';
$string['pluginadministration'] = 'KI-Chatbot Administration';
$string['pluginnamedesc']       = 'KI-Chatbot mit RAG-Backend (FastAPI + OpenAI).';
$string['activityname']         = 'Name';
$string['backendurl']           = 'Backend-URL';
$string['backendurldesc']       = 'Vollstaendige URL des FastAPI-Backends, z.\u00a0B. https://ki.example.com';
$string['openaikey']            = 'OpenAI API-Key';
$string['openaikeydesc']        = 'Wird bei jedem Request als X-Api-Key-Header an das Backend uebergeben. Leer lassen, wenn der Key direkt in der .env-Datei des Backends konfiguriert ist.';
$string['apibaseurl']           = 'API-Basis-URL';
$string['apibaseurldesc']       = 'Basis-URL des Chat-Completions-Endpunkts. Standard: https://api.openai.com/v1 | OpenRouter: https://openrouter.ai/api/v1 | Lokales Ollama: http://localhost:11434/v1';
$string['apimodel']             = 'Modell';
$string['apimodeldesc']         = 'Modell-ID fuer OpenRouter, z.\u00a0B.: google/gemma-4-26b-a4b-it:free | nvidia/nemotron-3.5-lightning:free | nvidia/nemotron-3-super-120b-a12b:free. Alle kostenfreien Modelle: https://openrouter.ai/models?q=:free';
