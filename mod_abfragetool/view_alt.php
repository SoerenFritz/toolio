<?php
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('abfragetool', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$instance = $DB->get_record('abfragetool', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$PAGE->set_url('/mod/abfragetool/view.php', ['id' => $id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
?>

<style>
:root {
    --moodle-orange: #f98012;
    --moodle-orange-dark: #d86400;
    --moodle-orange-soft: #fff3e8;
    --bg-soft: #f3f3f3;
    --border: #d8d8d8;
    --text: #2f2f2f;
}

.umfrage-app {
    max-width: 1180px;
    margin: 20px auto 60px;
    font-family: Arial, sans-serif;
    color: var(--text);
}

.top-actions {
    display: none;
    margin-bottom: 30px;
    border-bottom: 2px solid #ececec;
    padding-bottom: 15px;
    gap: 12px;
    flex-wrap: wrap;
}

.top-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    border: 2px solid var(--moodle-orange);
    border-radius: 8px;
    background: white;
    color: var(--moodle-orange);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: 
        background-color 0.2s,
        color 0.2s,
        border-color 0.2s,
        transform 0.15s;
}

.top-tab:hover,
.top-tab:focus {
    background: var(--moodle-orange);
    color: white;
}

.top-tab i {
    font-style: normal;
    font-size: 18px;
}

.type-selection h1,
.form-title {
    font-size: 34px;
    font-weight: 600;
    color: #5f5f5f;
    margin: 25px 0 34px;
}

.form-title {
    border: none;
    width: 100%;
    outline: none;
    background: transparent;
}

.type-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.type-card {
    border: 1.7px solid var(--moodle-orange);
    border-radius: 7px;
    padding: 22px 24px;
    background: #fafafa;
    display: flex;
    align-items: center;
    gap: 16px;
    cursor: pointer;
    min-height: 68px;
}

.type-card:hover {
    background: var(--moodle-orange-soft);
}

.type-icon {
    color: var(--moodle-orange);
    font-weight: bold;
    min-width: 30px;
}

.editor {
    display: none;
}

.question-card {
    background: var(--bg-soft);
    border-radius: 4px;
    border-top: 5px solid var(--moodle-orange);
    margin-bottom: 28px;
}

.card-toolbar {
    display: flex;
    justify-content: flex-end;
    gap: 24px;
    padding: 20px 24px 8px;
}

.icon-btn {
    border: none;
    background: transparent;
    color: #666;
    font-size: 22px;
    cursor: pointer;
}

.icon-btn:hover {
    color: var(--moodle-orange);
}

.card-body {
    padding: 20px 32px 28px;
}

.question-row {
    display: grid;
    grid-template-columns: 36px 1fr;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.question-input {
    width: 100%;
    border: 1px solid #d8d8d8;
    border-radius: 4px;
    background: white;
    padding: 12px 14px;
    font-size: 18px;
    outline: none;
    transition: .2s;
}

.question-input:focus {
    border-color: var(--moodle-orange);
    box-shadow: 0 0 0 2px rgba(249, 128, 18, .15);
}

.option-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 14px 0;
}

.option-input,
.answer-input,
.rank-input,
.scale-label-input,
.likert-input {
    border: none;
    background: white;
    padding: 12px 14px;
    font-size: 16px;
    border-radius: 4px;
    outline: none;
}
.option-input {
    width: 390px;
    border: 1px solid #d8d8d8;
    border-radius: 4px;
    background: white;
    padding: 12px 14px;
    font-size: 16px;
    outline: none;
    transition: .2s;
}

.option-input:focus {
    border-color: var(--moodle-orange);
    box-shadow: 0 0 0 2px rgba(249,128,18,.15);
}

.answer-input {
    width: 100%;
    border: 1px solid var(--border);
}

textarea.answer-input {
    min-height: 110px;
}

input[type="radio"],
input[type="checkbox"] {
    accent-color: var(--moodle-orange);
    width: 22px;
    height: 22px;
}

.add-inline {
    color: var(--moodle-orange-dark);
    font-weight: 700;
    cursor: pointer;
    margin-top: 18px;
    display: inline-block;
    margin-right: 30px;
}

.card-footer {
    border-top: 1px solid #ddd;
    padding: 16px 24px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 18px;
}

.switch-wrap {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.switch {
    width: 46px;
    height: 24px;
    border: 2px solid #666;
    border-radius: 999px;
    background: white;
    position: relative;
    cursor: pointer;
}

.switch::after {
    content: "";
    width: 16px;
    height: 16px;
    background: #666;
    border-radius: 50%;
    position: absolute;
    top: 2px;
    left: 3px;
    transition: .2s;
}

.switch.active {
    border-color: var(--moodle-orange);
}

.switch.active::after {
    background: var(--moodle-orange);
    left: 23px;
}

.add-question-main {
    color: var(--moodle-orange-dark);
    font-size: 20px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    margin: 8px 0 28px;
}

.plus-circle {
    background: var(--moodle-orange);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.stars {
    font-size: 36px;
    color: var(--moodle-orange);
    letter-spacing: 14px;
    margin: 30px 0;
}

.select-row {
    display: flex;
    gap: 40px;
    align-items: center;
}

.small-select {
    padding: 9px 14px;
    border: none;
    border-radius: 4px;
    background: white;
    font-size: 16px;
}

.rank-input {
    display: block;
    width: 390px;
    margin: 14px 0;

    border: 1px solid #d8d8d8;
    border-radius: 4px;
    background: white;
    padding: 12px 14px;
    font-size: 16px;
    outline: none;
    transition: .2s;
}

.rank-input:focus {
    border-color: var(--moodle-orange);
    box-shadow: 0 0 0 2px rgba(249,128,18,.15);
}

.date-wrap {
    position: relative;
}

.date-wrap::after {
    content: "📅";
    position: absolute;
    right: 14px;
    top: 10px;
    opacity: .55;
}

.date-input {
    cursor: pointer;
}

.date-input::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 1;
}

.likert-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
}

.likert-table th,
.likert-table td {
    padding: 14px;
    text-align: center;
}

.likert-table td:first-child,
.likert-table th:first-child {
    text-align: left;
    width: 220px;
}

.likert-table tr:nth-child(even) {
    background: #f7f7f7;
}

.likert-input {
    width: 150px;
    text-align: center;
    border: 1px solid #d8d8d8;
    border-radius: 4px;
    background: white;
    padding: 10px 12px;
    font-size: 15px;
    outline: none;
    transition: .2s;
}

.likert-input:hover {
    border-color: #c7c7c7;
}

.likert-input:focus {
    border-color: var(--moodle-orange);
    box-shadow: 0 0 0 2px rgba(249,128,18,.15);
}

.likert-table td:first-child .likert-input {
    width: 220px;
    text-align: left;
}
.scale-row {
    display: grid;
    grid-template-columns: repeat(11, 1fr);
    border: 1px solid #bbb;
    background: white;
    margin: 20px 0;
}

.scale-cell {
    text-align: center;
    padding: 13px 0;
    border-right: 1px solid #bbb;
    color: #999;
}

.scale-cell:last-child {
    border-right: none;
}

.scale-labels {
    display: flex;
    justify-content: space-between;
    width: 100%;
    margin-top: 10px;
}

.scale-label-left,
.scale-label-right {
    font-size: 15px;
    color: #666;
}

.scale-label-right {
    text-align: right;
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.25);
    z-index: 9999;

    align-items: center;
    justify-content: center;

    padding: 20px;
    box-sizing: border-box;
}

.modal-content {
    position: relative;

    background: white;
    width: min(900px, 95vw);

    max-height: 90vh;

    border-radius: 8px;
    box-shadow: 0 8px 30px rgba(0,0,0,.2);

    overflow-y: auto;
    overflow-x: hidden;

    padding: 60px 28px 28px;

    box-sizing: border-box;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 15px;

    z-index: 100;

    width: 40px;
    height: 40px;

    margin: 0;
    padding: 0;

    border: none;
    border-radius: 50%;

    background: var(--moodle-orange);
    color: white;

    font-size: 26px;
    font-weight: 400;
    line-height: 1;

    cursor: pointer;

    display: flex;
    align-items: center;
    justify-content: center;

    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}

.close-modal:hover {
    background: var(--moodle-orange-dark);
}

.preview-question {
    padding: 18px 0;
    border-bottom: 1px solid #eee;
}

.visual-card {
    border: 1px solid #e2e2e2;
    border-radius: 8px;
    padding: 18px;
    margin: 16px 0;
    background: #fafafa;
}

.visual-header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.visual-title {
    font-weight: 700;
    font-size: 17px;
}

.visual-type {
    color: #666;
    font-size: 14px;
    margin-top: 4px;
}

.visual-select {
    border: 1px solid var(--moodle-orange);
    color: var(--moodle-orange-dark);
    border-radius: 6px;
    background: white;
    padding: 10px 14px;
    font-weight: 600;
}

.visual-preview {
    margin-top: 18px;
    background: white;
    border-radius: 6px;
    padding: 18px;
    min-height: 160px;
}

.mock-chart {
    display: flex;
    align-items: end;
    gap: 16px;
    height: 145px;
    padding: 10px;
}

.mock-column {
    flex: 1;
    background: var(--moodle-orange);
    border-radius: 6px 6px 0 0;
    opacity: .82;
    min-width: 30px;
}

.mock-bars {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 8px 0;
}

.mock-bar-row {
    display: grid;
    grid-template-columns: 90px 1fr 38px;
    align-items: center;
    gap: 12px;
}

.mock-bar {
    height: 24px;
    background: var(--moodle-orange);
    border-radius: 4px;
    opacity: .82;
}

.mock-pie {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    margin: 0 auto;
    background: conic-gradient(var(--moodle-orange) 0 42%, #ffc078 42% 70%, #ffe1c2 70% 100%);
}

.wordcloud {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 14px;
    min-height: 150px;
    color: var(--moodle-orange-dark);
}

.wordcloud span:nth-child(1) {
    font-size: 34px;
    font-weight: 800;
}

.wordcloud span:nth-child(2) {
    font-size: 28px;
    font-weight: 700;
}

.wordcloud span:nth-child(3) {
    font-size: 24px;
    font-weight: 700;
}

.wordcloud span:nth-child(4) {
    font-size: 20px;
}

.wordcloud span:nth-child(5) {
    font-size: 18px;
}

.participant-preview {
    max-width: 820px;
    margin: 0 auto;
}

.participant-header {
    margin-bottom: 28px;
}

.participant-header h2 {
    margin-bottom: 6px;
    font-size: 28px;
}

.participant-header p {
    color: #666;
}

.participant-question-card {
    background: #fff;
    border: 1px solid #e3e3e3;
    border-top: 5px solid var(--moodle-orange);
    border-radius: 8px;
    padding: 26px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

.participant-question-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 22px;
}

.participant-number {
    color: var(--moodle-orange);
}

.required-mark {
    color: var(--moodle-orange);
}

.participant-options,
.participant-date-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.participant-option {
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid #ddd;
    border-radius: 7px;
    padding: 14px 16px;
    cursor: pointer;
    background: #fff;
    transition: .2s;
}

.participant-option:hover {
    border-color: var(--moodle-orange);
    background: var(--moodle-orange-soft);
}

.participant-option:has(input:checked) {
    border-color: var(--moodle-orange);
    background: var(--moodle-orange-soft);
}

.participant-text-input,
.participant-textarea,
.participant-date-input {
    width: 100%;
    border: 1px solid #d8d8d8;
    border-radius: 6px;
    padding: 13px 14px;
    font-size: 16px;
    outline: none;
}

.participant-textarea {
    min-height: 120px;
    resize: vertical;
}

.participant-text-input:focus,
.participant-textarea:focus,
.participant-date-input:focus {
    border-color: var(--moodle-orange);
    box-shadow: 0 0 0 2px rgba(249,128,18,.15);
}

.participant-rating {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.rating-symbol {
    border: none;
    background: transparent;
    color: #aaa;
    font-size: 34px;
    cursor: pointer;
    padding: 4px;
    transition: .15s;
}

.rating-symbol:hover,
.rating-symbol.selected {
    color: var(--moodle-orange);
    transform: scale(1.12);
}

.date-suggestion {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #eee;
}

.date-suggestion span {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.participant-ranking {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ranking-card {
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid #ddd;
    border-radius: 7px;
    padding: 14px 16px;
    background: white;
    cursor: grab;
}

.ranking-card:hover {
    border-color: var(--moodle-orange);
}

.drag-handle {
    color: var(--moodle-orange);
    font-size: 20px;
}

.participant-hint {
    margin-top: 10px;
    font-size: 14px;
    color: #777;
}

.participant-likert-wrapper {
    overflow-x: auto;
}

.participant-likert {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

.participant-likert th,
.participant-likert td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    text-align: center;
}

.participant-likert th:first-child,
.participant-likert td:first-child {
    text-align: left;
}

.participant-likert thead {
    background: #fafafa;
}

.participant-scale-values {
    display: grid;
    grid-template-columns: repeat(11, 1fr);
    gap: 6px;
}

.scale-button {
    border: 1px solid #d8d8d8;
    border-radius: 6px;
    background: white;
    padding: 11px 4px;
    cursor: pointer;
    transition: .15s;
}

.scale-button:hover,
.scale-button.selected {
    border-color: var(--moodle-orange);
    background: var(--moodle-orange);
    color: white;
}

.participant-scale-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    color: #666;
    font-size: 14px;
}

.participant-submit {
    display: block;
    margin: 28px 0 10px auto;
    padding: 12px 24px;
    border: none;
    border-radius: 7px;
    background: var(--moodle-orange);
    color: white;
    font-weight: 700;
    cursor: pointer;
}

.participant-submit:hover {
    background: var(--moodle-orange-dark);
}

@media (max-width: 800px) {
    .type-grid {
        grid-template-columns: 1fr;
    }

    .option-input,
    .rank-input {
        width: 100%;
    }

    .question-row {
        grid-template-columns: 1fr;
    }
}

.no-data-message {
    margin-top: 20px;
    text-align: center;
    color: #777;
    font-size: 14px;
}

.empty-columns {
    height: 180px;
    display: flex;
    align-items: flex-end;
    justify-content: space-around;
    gap: 15px;
    border-bottom: 1px solid #ddd;
}

.empty-column-item {
    flex: 1;
    text-align: center;
}

.empty-column {
    width: 60%;
    height: 3px;
    margin: 0 auto 8px;
    background: var(--moodle-orange);
    border-radius: 4px;
}

.empty-column-item span {
    font-size: 13px;
    color: #666;
}

.empty-bars {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.empty-bar-row {
    display: grid;
    grid-template-columns: 140px 1fr 30px;
    align-items: center;
    gap: 12px;
}

.empty-bar-track {
    height: 22px;
    background: #f1f1f1;
    border-radius: 4px;
    overflow: hidden;
}

.empty-bar-value {
    width: 0%;
    height: 100%;
    background: var(--moodle-orange);
}

.empty-pie-container {
    text-align: center;
}

.empty-pie {
    width: 160px;
    height: 160px;
    margin: 10px auto 20px;
    border-radius: 50%;
    border: 18px solid #eeeeee;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 28px;
    font-weight: 700;
    color: #999;
}

.pie-legend {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: 350px;
    margin: 0 auto;
    text-align: left;
}

.pie-legend-item {
    display: grid;
    grid-template-columns: 14px 1fr 30px;
    gap: 8px;
    align-items: center;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--moodle-orange);
    opacity: .45;
}

.empty-wordcloud {
    min-height: 180px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #777;
    text-align: center;
}

.empty-wordcloud > span {
    font-size: 46px;
    color: var(--moodle-orange);
}

.empty-wordcloud small {
    max-width: 430px;
}

/* ==============================
   PRÄSENTATIONSMODUS
   ============================== */

.modal-content.presentation-mode {
    width: 96vw;
    max-width: 1500px;
    height: 92vh;
    max-height: 92vh;
    padding: 0;
    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #fff7ef 0%,
            #ffffff 45%,
            #fff2e3 100%
        );
}

.presentation-wrapper {
    height: 100%;
    min-height: 80vh;

    display: flex;
    flex-direction: column;

    padding: 38px 48px 30px;

    box-sizing: border-box;
}

.presentation-topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;

    padding-bottom: 20px;
    border-bottom: 2px solid rgba(249,128,18,.15);
}

.presentation-survey-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--moodle-orange-dark);
}

.presentation-question-counter-top {
    margin-top: 5px;
    color: #777;
    font-size: 14px;
}

.presentation-responses {
    font-size: 17px;
    font-weight: 700;
    color: var(--moodle-orange-dark);

    margin-right: 55px;
}

.presentation-main {
    flex: 1;

    display: flex;
    flex-direction: column;

    justify-content: center;

    padding: 35px 45px;
}

.presentation-question {
    font-size: 34px;
    font-weight: 700;
    color: #333;

    margin-bottom: 40px;

    max-width: 1000px;
}

.presentation-visualization {
    width: 100%;
    max-width: 1100px;

    margin: 0 auto;

    font-size: 18px;
}

/* Diagramme im Präsentationsmodus größer darstellen */

.presentation-visualization .empty-columns {
    height: 330px;
}

.presentation-visualization .empty-pie {
    width: 260px;
    height: 260px;
}

.presentation-visualization .empty-bar-row {
    grid-template-columns: 190px 1fr 50px;
    font-size: 18px;
}

.presentation-visualization .empty-bar-track {
    height: 32px;
}

.presentation-visualization .normal-answer-row {
    padding: 18px 20px;
    font-size: 18px;
}

.presentation-bottom {
    display: grid;
    grid-template-columns: 1fr auto 1fr;

    align-items: center;

    padding-top: 20px;
    border-top: 2px solid rgba(249,128,18,.15);
}

.presentation-visualization-control {
    display: flex;
    align-items: center;
    gap: 10px;

    font-weight: 600;
}

.presentation-select {
    border: 2px solid var(--moodle-orange);
    border-radius: 7px;

    background: white;
    color: var(--moodle-orange-dark);

    padding: 10px 14px;

    font-size: 15px;
    font-weight: 600;

    cursor: pointer;
}

.presentation-select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(249,128,18,.15);
}

.presentation-navigation {
    grid-column: 2;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 18px;

    font-size: 16px;
    font-weight: 600;
}

.presentation-nav-button {
    width: 46px;
    height: 46px;

    border: 2px solid var(--moodle-orange);
    border-radius: 7px;

    background: white;
    color: var(--moodle-orange);

    font-size: 30px;
    line-height: 1;

    cursor: pointer;

    transition: .2s;
}

.presentation-nav-button:hover:not(:disabled) {
    background: var(--moodle-orange);
    color: white;
}

.presentation-nav-button:disabled {
    opacity: .3;
    cursor: default;
}

.presentation-empty {
    padding: 40px;
}

.settings-wrapper {
    max-width: 760px;
    margin: 0 auto;
}

.settings-description {
    color: #666;
    margin-bottom: 25px;
}

.settings-card {
    border: 1px solid #e1e1e1;
    border-left: 4px solid var(--moodle-orange);

    border-radius: 8px;

    padding: 20px;

    margin-bottom: 18px;

    background: #fff;
}

.settings-row {
    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 25px;
}

.settings-title {
    font-size: 17px;
    font-weight: 700;
    color: #333;
}

.settings-hint {
    margin-top: 5px;

    color: #777;
    font-size: 14px;

    max-width: 540px;
}

.settings-input-area {
    margin-top: 20px;

    padding-top: 18px;

    border-top: 1px solid #eee;
}

.settings-input-area label {
    display: block;

    margin-bottom: 8px;

    font-weight: 600;
}

.settings-input {
    width: 100%;

    border: 1px solid #d8d8d8;
    border-radius: 6px;

    padding: 11px 13px;

    background: white;

    font-size: 15px;

    outline: none;
}

.settings-input:focus {
    border-color: var(--moodle-orange);

    box-shadow:
        0 0 0 2px rgba(249,128,18,.15);
}

.timer-input-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.timer-minutes {
    width: 110px;
}

.survey-timer-box {
    position: sticky;
    top: 0;
    z-index: 20;

    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 20px;
    padding: 14px 18px;

    background: #fff7ef;

    border: 1px solid rgba(249,128,18,.35);
    border-left: 5px solid var(--moodle-orange);
    border-radius: 8px;

    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.survey-timer-label {
    font-size: 15px;
    font-weight: 600;
    color: #555;
}

#surveyTimer {
    font-size: 23px;
    color: var(--moodle-orange-dark);
    font-variant-numeric: tabular-nums;
}

#surveyTimer.expired {
    color: #b42318;
}

.timer-expired-message {
    margin-bottom: 20px;
    padding: 18px;

    border: 1px solid #e3a5a0;
    border-radius: 8px;

    background: #fff4f3;
    color: #8a1c13;

    display: flex;
    flex-direction: column;
    gap: 4px;
}

.timer-expired-message strong {
    font-size: 17px;
}

.timer-expired-message span {
    font-size: 14px;
}

</style>

<div class="umfrage-app">

    <div id="topActions" class="top-actions">
        <button class="top-tab" onclick="openModal('settings')"><i>⚙</i> Einstellungen</button>
        <button class="top-tab" onclick="openModal('preview')"><i>👁</i> Vorschau</button>
        <button class="top-tab" onclick="openModal('answers')"><i>📊</i> Antworten anzeigen</button>
        <button class="top-tab" onclick="openModal('present')"><i>🖥</i> Präsentieren</button>
        <button class="top-tab" onclick="openModal('share')"><i>🔗</i> Teilen</button>
    </div>

    <div id="typeSelection" class="type-selection">
        <h1>Umfrage</h1>
        <div class="type-grid">
            <div class="type-card" onclick="startEditor('choice')"><span class="type-icon">◉</span>Auswahl</div>
            <div class="type-card" onclick="startEditor('text')"><span class="type-icon">T</span>Text</div>
            <div class="type-card" onclick="startEditor('rating')"><span class="type-icon">☆</span>Bewertung</div>
            <div class="type-card" onclick="startEditor('date')"><span class="type-icon">▣</span>Datum</div>
            <div class="type-card" onclick="startEditor('ranking')"><span class="type-icon">↕</span>Rangfolge</div>
            <div class="type-card" onclick="startEditor('likert')"><span class="type-icon">▦</span>Likert</div>
            <div class="type-card" onclick="startEditor('scale')"><span class="type-icon">0–10</span>Skala</div>
        </div>
    </div>

    <div id="editor" class="editor">
        <input id="formTitle" class="form-title" value="<?php echo format_string($instance->name); ?>">
        <div id="questions"></div>
        <div class="add-question-main" onclick="showTypeSelectionForNewQuestion()">
            <span class="plus-circle">+</span> Neue Frage hinzufügen
        </div>
    </div>

</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <div id="modalBody"></div>
        <button
    class="close-modal"
    onclick="closeModal()"
    title="Fenster schließen"
>
    ×
</button>
    </div>
</div>

<script>
let questions = [];
let addMode = false;
let previewQuestionOrder = null;
let surveyTimerInterval = null;
let surveyTimerEnd = null;
let surveyTimerExpired = false;
let surveySettings = {
    startEnabled: false,
    startDateTime: '',
    endEnabled: false,
    endDateTime: '',
    timerEnabled: false,
    timerMinutes: 30,
    randomOrder: false
};
let presentationIndex = 0;

function startEditor(type) {
    document.getElementById('typeSelection').style.display = 'none';
    document.getElementById('editor').style.display = 'block';
    document.getElementById('topActions').style.display = 'flex';
    addQuestion(type);
}

function showTypeSelectionForNewQuestion() {
    addMode = true;
    document.getElementById('editor').style.display = 'none';
    document.getElementById('typeSelection').style.display = 'block';
    document.querySelector('.type-selection h1').textContent = 'Fragetyp auswählen';
}

document.querySelectorAll('.type-card').forEach(card => {
    const old = card.getAttribute('onclick');
    card.setAttribute('onclick', `
        if(addMode){
            ${old.replace('startEditor', 'addQuestion')};
            document.getElementById('typeSelection').style.display='none';
            document.getElementById('editor').style.display='block';
            document.querySelector('.type-selection h1').textContent='Umfrage';
            addMode=false;
        } else {
            ${old};
        }
    `);
});

function addQuestion(type) {
    const q = {
        id: Date.now() + Math.random(),
        type: type,

        title: type === 'scale'
            ? 'Wie wahrscheinlich ist es, dass Sie ...?'
            : 'Frage',

        options: [
            'Option 1',
            'Option 2'
        ],

        ranking: [
            'Option 1',
            'Option 2',
            'Option 3'
        ],

        likertRows: [
            'Aussage 1',
            'Aussage 2'
        ],

        likertCols: [
            'Option 1',
            'Option 2',
            'Option 3',
            'Option 4',
            'Option 5'
        ],

        dates: [
            '',
            ''
        ],

        required: false,
        multiple: false,
        longAnswer: false,
        allowDateSuggestions: false,

        ratingSteps: 5,
        ratingSymbol: '☆',

        scaleLeft: 'Unwahrscheinlich',
        scaleRight: 'Wahrscheinlich',

        visualization: 'normal',
    };

    questions.push(q);
    renderQuestions();
}

function renderQuestions() {
    const container = document.getElementById('questions');
    container.innerHTML = '';

    questions.forEach((q, index) => {
        const card = document.createElement('div');
        card.className = 'question-card';
        card.innerHTML = `
            <div class="card-toolbar">
                <button class="icon-btn" title="Frage kopieren" onclick="duplicateQuestion(${index})">⧉</button>
                <button class="icon-btn" title="Frage löschen" onclick="deleteQuestion(${index})">🗑</button>
                <button class="icon-btn" title="Nach unten verschieben" onclick="moveQuestion(${index}, 1)">↓</button>
                <button class="icon-btn" title="Nach oben verschieben" onclick="moveQuestion(${index}, -1)">↑</button>
            </div>
            <div class="card-body">
                <div class="question-row">
                    <div>${index + 1}.</div>
                    <div>
                        <input class="question-input" value="${escapeAttr(q.title)}" oninput="questions[${index}].title=this.value">
                    </div>
                </div>
                ${renderQuestionContent(q, index)}
            </div>
            <div class="card-footer">
                ${renderFooterSwitches(q, index)}
            </div>
        `;
        container.appendChild(card);
    });
}

function renderQuestionContent(q, index) {
    if (q.type === 'choice') {
        return `
            ${q.options.map((o, i) => `
                <div class="option-row">
                    <input type="${q.multiple ? 'checkbox' : 'radio'}" disabled>
                    <input class="option-input" value="${escapeAttr(o)}" oninput="questions[${index}].options[${i}]=this.value">
                </div>
            `).join('')}
           <span
    class="add-inline"
    onclick="
        const nextNumber = questions[${index}].options.length + 1;
        questions[${index}].options.push('Option ' + nextNumber);
        renderQuestions();
    "
>
    ＋ Option hinzufügen
</span>
            <span class="add-inline" onclick="questions[${index}].options.push('Sonstiges'); renderQuestions()">Option "Sonstiges" hinzufügen</span>
        `;
    }

    if (q.type === 'text') {
        return q.longAnswer
            ? `<textarea class="answer-input" placeholder="Ihre Antwort eingeben"></textarea>`
            : `<input class="answer-input" placeholder="Ihre Antwort eingeben">`;
    }

    if (q.type === 'rating') {
        return `
            <div class="stars">${Array.from({length: Number(q.ratingSteps)}, () => q.ratingSymbol).join('')}</div>
            <div class="select-row">
                <label>Stufen:
                    <select class="small-select" onchange="questions[${index}].ratingSteps=this.value; renderQuestions()">
                        ${[2,3,4,5,6,7,8,9,10].map(n => `<option ${q.ratingSteps==n?'selected':''}>${n}</option>`).join('')}
                    </select>
                </label>
                <label>Symbol:
                    <select class="small-select" onchange="questions[${index}].ratingSymbol=this.value; renderQuestions()">
    <option value="☆" ${q.ratingSymbol==='☆'?'selected':''}>☆ Stern</option>
    <option value="♡" ${q.ratingSymbol==='♡'?'selected':''}>♡ Herz</option>
    <option value="👍" ${q.ratingSymbol==='👍'?'selected':''}>👍 Daumen hoch</option>
    <option value="😊" ${q.ratingSymbol==='😊'?'selected':''}>😊 Smiley</option>
    <option value="🏆" ${q.ratingSymbol==='🏆'?'selected':''}>🏆 Trophäe</option>
    <option value="💡" ${q.ratingSymbol==='💡'?'selected':''}>💡 Glühbirne</option>
    <option value="✅" ${q.ratingSymbol==='✅'?'selected':''}>✅ Haken</option>
    <option value="🔥" ${q.ratingSymbol==='🔥'?'selected':''}>🔥 Flamme</option>
    <option value="📚" ${q.ratingSymbol==='📚'?'selected':''}>📚 Buch</option>
    <option value="🎯" ${q.ratingSymbol==='🎯'?'selected':''}>🎯 Zielscheibe</option>
    <option value="🚀" ${q.ratingSymbol==='🚀'?'selected':''}>🚀 Rakete</option>
    <option value="👏" ${q.ratingSymbol==='👏'?'selected':''}>👏 Applaus</option>
</select>
                </label>
            </div>
        `;
    }

  if (q.type === 'date') {
    return `
        ${q.dates.map((date, i) => `
            <div class="option-row">

                <input
                    type="${q.multiple ? 'checkbox' : 'radio'}"
                    disabled
                >

                <input
                    class="option-input date-input"
                    type="date"
                    value="${date}"
                    onchange="questions[${index}].dates[${i}] = this.value"
                    onclick="this.showPicker && this.showPicker()"
                >

                <button
                    class="icon-btn"
                    title="Datum löschen"
                    onclick="
                        questions[${index}].dates.splice(${i}, 1);
                        renderQuestions();
                    "
                >
                    ×
                </button>

            </div>
        `).join('')}

        <span
            class="add-inline"
            onclick="
                questions[${index}].dates.push('');
                renderQuestions();
            "
        >
            ＋ Datum hinzufügen
        </span>
    `;
}

    if (q.type === 'ranking') {
        return `
            ${q.ranking.map((o, i) => `
                <input class="rank-input" value="${escapeAttr(o)}" oninput="questions[${index}].ranking[${i}]=this.value">
            `).join('')}
            <span
    class="add-inline"
    onclick="
        const nextNumber = questions[${index}].ranking.length + 1;
        questions[${index}].ranking.push('Option ' + nextNumber);
        renderQuestions();
    "
>
    ＋ Option hinzufügen
</span>
        `;
    }

    if (q.type === 'likert') {
    return `
        <table class="likert-table">
            <tr>
                <th></th>

                ${q.likertCols.map((col, colIndex) => `
                    <th>
                        <input
                            type="text"
                            class="likert-input"
                            value="${escapeAttr(col)}"
                            onchange="questions[${index}].likertCols[${colIndex}] = this.value"
                        >
                    </th>
                `).join('')}

                <th>
                    <span
                        class="add-inline"
                        title="Option hinzufügen"
                        onclick="
    const nextNumber = questions[${index}].likertCols.length + 1;
    questions[${index}].likertCols.push('Option ' + nextNumber);
                            renderQuestions();
                        "
                    >
                        ＋
                    </span>
                </th>
            </tr>

            ${q.likertRows.map((row, rowIndex) => `
                <tr>
                    <td>
                        <input
                            type="text"
                            class="likert-input"
                            value="${escapeAttr(row)}"
                            onchange="questions[${index}].likertRows[${rowIndex}] = this.value"
                        >
                    </td>

                    ${q.likertCols.map((col, colIndex) => `
                        <td>
                            <input
                                type="radio"
                                name="likert_${index}_${rowIndex}"
                                disabled
                            >
                        </td>
                    `).join('')}

                    <td></td>
                </tr>
            `).join('')}
        </table>

        <span
            class="add-inline"
            onclick="
                questions[${index}].likertRows.push(
    'Aussage ' + (questions[${index}].likertRows.length + 1)
);
                renderQuestions();
            "
        >
            ＋ Aussage hinzufügen
        </span>
    `;
}

    if (q.type === 'scale') {
        return `
            <div class="scale-row">
                ${[0,1,2,3,4,5,6,7,8,9,10].map(n => `<div class="scale-cell">${n}</div>`).join('')}
            </div>
            <div class="scale-labels">
    <span class="scale-label-left">${q.scaleLeft}</span>
    <span class="scale-label-right">${q.scaleRight}</span>
</div>
        `;
    }
}

function renderFooterSwitches(q, index) {
    let html = '';

    if (q.type === 'choice') {
        html += switchHtml(
            'Mehrere Antworten',
            q.multiple,
            `questions[${index}].multiple = !questions[${index}].multiple; renderQuestions()`
        );
    }

    if (q.type === 'text') {
        html += switchHtml(
            'Lange Antwort',
            q.longAnswer,
            `questions[${index}].longAnswer = !questions[${index}].longAnswer; renderQuestions()`
        );
    }

    if (q.type === 'date') {
        html += switchHtml(
            'Mehrere Antworten',
            q.multiple,
            `questions[${index}].multiple = !questions[${index}].multiple; renderQuestions()`
        );

        html += switchHtml(
            'Eigene Terminvorschläge zulassen',
            q.allowDateSuggestions,
            `questions[${index}].allowDateSuggestions = !questions[${index}].allowDateSuggestions; renderQuestions()`
        );
    }

    html += switchHtml(
        'Erforderlich',
        q.required,
        `questions[${index}].required = !questions[${index}].required; renderQuestions()`
    );

    return html;
}

function switchHtml(label, active, action) {
    return `
        <span class="switch-wrap">
            <span class="switch ${active ? 'active' : ''}" onclick="${action}"></span>
            <span>${label}</span>
        </span>
    `;
}

function duplicateQuestion(index) {
    questions.splice(index + 1, 0, JSON.parse(JSON.stringify(questions[index])));
    renderQuestions();
}

function deleteQuestion(index) {
    questions.splice(index, 1);
    renderQuestions();
}

function moveQuestion(index, direction) {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= questions.length) return;
    [questions[index], questions[newIndex]] = [questions[newIndex], questions[index]];
    renderQuestions();
}

function openModal(type) {
    const body = document.getElementById('modalBody');
    const modalContent = document.querySelector('.modal-content');
    modalContent.classList.remove('presentation-mode');

    if (type === 'settings') {
    body.innerHTML = buildSettingsMenu();
}

    if (type === 'preview') {
        previewQuestionOrder = null;
        surveyTimerEnd = null;
surveyTimerExpired = false;

if (surveyTimerInterval) {
    clearInterval(surveyTimerInterval);
    surveyTimerInterval = null;
}
        body.innerHTML = `<h2>Vorschau</h2>${buildPreview()}`;
        if (surveySettings.timerEnabled) {
    setTimeout(() => {
        startSurveyTimer();
    }, 0);
}
    }

    if (type === 'answers') {
    body.innerHTML = buildVisualizationMenu();
}

    if (type === 'present') {
    presentationIndex = 0;
    modalContent.classList.add('presentation-mode');
    body.innerHTML = buildPresentationMode();
}

       if (type === 'share') {
    body.innerHTML = `
        <h2>Umfrage teilen</h2>
        <p>Teile deine Umfrage über den folgenden Link:</p>

        <input class="answer-input"
               value="http://localhost:8080/mod/umfrage/view.php?id=<?php echo $id; ?>"
               readonly>

        <br><br>

    <button
    id="copyLinkButton"
    class="top-tab"
    onclick="copyShareLink()"
>
    <span id="copyLinkIcon">🔗</span>
    <span id="copyLinkText">Link kopieren</span>
</button>
    `;
}

    document.getElementById('modal').style.display = 'flex';
}

function copyShareLink() {
    const input = document.querySelector('.answer-input');
    const button = document.getElementById('copyLinkButton');
    const icon = document.getElementById('copyLinkIcon');
    const text = document.getElementById('copyLinkText');

    navigator.clipboard.writeText(input.value).then(() => {

        // Erfolgszustand anzeigen.
        button.classList.add('copy-success');
        icon.textContent = '✓';
        text.textContent = 'Link kopiert!';

        // Nach 1,5 Sekunden wieder zurücksetzen.
        setTimeout(() => {
            button.classList.remove('copy-success');
            icon.textContent = '🔗';
            text.textContent = 'Link kopieren';
        }, 1500);
    });
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

function buildSettingsMenu() {
    return `
        <div class="settings-wrapper">

            <h2>Einstellungen</h2>

            <p class="settings-description">
                Lege fest, wann und wie die Umfrage von den Teilnehmenden bearbeitet werden kann.
            </p>


            <!-- STARTDATUM -->
            <div class="settings-card">

                <div class="settings-row">

                    <div>
                        <div class="settings-title">
                            Startdatum
                        </div>

                        <div class="settings-hint">
                            Die Umfrage kann erst ab diesem Zeitpunkt beantwortet werden.
                        </div>
                    </div>

                    <span
                        class="switch ${surveySettings.startEnabled ? 'active' : ''}"
                        onclick="
                            surveySettings.startEnabled = !surveySettings.startEnabled;
                            document.getElementById('modalBody').innerHTML = buildSettingsMenu();
                        "
                    ></span>

                </div>

                ${
                    surveySettings.startEnabled
                    ? `
                        <div class="settings-input-area">

                            <label>
                                Datum und Uhrzeit
                            </label>

                            <input
                                type="datetime-local"
                                class="settings-input"
                                value="${surveySettings.startDateTime}"
                                onchange="surveySettings.startDateTime = this.value"
                            >

                        </div>
                    `
                    : ''
                }

            </div>


            <!-- ENDDATUM -->
            <div class="settings-card">

                <div class="settings-row">

                    <div>
                        <div class="settings-title">
                            Enddatum
                        </div>

                        <div class="settings-hint">
                            Nach diesem Zeitpunkt können keine Antworten mehr abgegeben werden.
                        </div>
                    </div>

                    <span
                        class="switch ${surveySettings.endEnabled ? 'active' : ''}"
                        onclick="
                            surveySettings.endEnabled = !surveySettings.endEnabled;
                            document.getElementById('modalBody').innerHTML = buildSettingsMenu();
                        "
                    ></span>

                </div>

                ${
                    surveySettings.endEnabled
                    ? `
                        <div class="settings-input-area">

                            <label>
                                Datum und Uhrzeit
                            </label>

                            <input
                                type="datetime-local"
                                class="settings-input"
                                value="${surveySettings.endDateTime}"
                                onchange="surveySettings.endDateTime = this.value"
                            >

                        </div>
                    `
                    : ''
                }

            </div>


            <!-- TIMER -->
            <div class="settings-card">

                <div class="settings-row">

                    <div>
                        <div class="settings-title">
                            Zeitdauer
                        </div>

                        <div class="settings-hint">
                            Nach Ablauf der festgelegten Zeit wird die Bearbeitung automatisch beendet.
                        </div>
                    </div>

                    <span
                        class="switch ${surveySettings.timerEnabled ? 'active' : ''}"
                        onclick="
                            surveySettings.timerEnabled = !surveySettings.timerEnabled;
                            document.getElementById('modalBody').innerHTML = buildSettingsMenu();
                        "
                    ></span>

                </div>

                ${
                    surveySettings.timerEnabled
                    ? `
                        <div class="settings-input-area">

                            <label>
                                Bearbeitungszeit in Minuten
                            </label>

                            <div class="timer-input-row">

                                <input
                                    type="number"
                                    min="1"
                                    class="settings-input timer-minutes"
                                    value="${surveySettings.timerMinutes}"
                                    onchange="
                                        surveySettings.timerMinutes = Math.max(1, Number(this.value));
                                    "
                                >

                                <span>Minuten</span>

                            </div>

                        </div>
                    `
                    : ''
                }

            </div>


            <!-- ZUFÄLLIGE REIHENFOLGE -->
            <div class="settings-card">

                <div class="settings-row">

                    <div>
                        <div class="settings-title">
                            Fragen in zufälliger Reihenfolge
                        </div>

                        <div class="settings-hint">
                            Die Reihenfolge der Fragen wird für die Teilnehmenden zufällig angeordnet.
                        </div>
                    </div>

                    <span
                        class="switch ${surveySettings.randomOrder ? 'active' : ''}"
                        onclick="
                            surveySettings.randomOrder = !surveySettings.randomOrder;
                            document.getElementById('modalBody').innerHTML = buildSettingsMenu();
                        "
                    ></span>

                </div>

            </div>

        </div>
    `;
}

function buildVisualizationMenu() {
    if (questions.length === 0) {
        return `<h2>Antworten anzeigen</h2><p>Es wurde noch keine Frage erstellt.</p>`;
    }

    let html = `
        <h2>Antworten anzeigen</h2>
        <p>Wähle für jede Frage eine Visualisierung aus.</p>
    `;

    questions.forEach((q, index) => {
        html += `
            <div class="visual-card">
                <div class="visual-header">
                    <div>
                        <div class="visual-title">${index + 1}. ${escapeHtml(q.title)}</div>
                        <div class="visual-type">Fragetyp: ${getQuestionTypeLabel(q.type)}</div>
                    </div>

                    <select class="visual-select"
        onchange="
            questions[${index}].visualization=this.value;
            updateVisualizationPreview(${index});
        ">

    <option value="normal"
        ${q.visualization==="normal"?"selected":""}>
        Normale Ansicht
    </option>

    <option value="column"
        ${q.visualization==="column"?"selected":""}>
        Säulendiagramm
    </option>

    <option value="bar"
        ${q.visualization==="bar"?"selected":""}>
        Balkendiagramm
    </option>

    <option value="pie"
        ${q.visualization==="pie"?"selected":""}>
        Kreisdiagramm
    </option>

    <option value="wordcloud"
        ${q.visualization==="wordcloud"?"selected":""}>
        Wortwolke
    </option>

</select>
                </div>

                <div id="visualPreview${index}" class="visual-preview">
                    ${renderVisualizationPreview(q)}
                </div>
            </div>
        `;
    });

    return html;
}

function updateVisualizationPreview(index) {
    const target = document.getElementById(`visualPreview${index}`);
    if (target) {
        target.innerHTML = renderVisualizationPreview(questions[index]);
    }
}

function renderVisualizationPreview(q) {

    // Normale Ansicht.
    if (q.visualization === 'normal') {

        if (q.type === 'choice') {
            return `
                <div class="normal-answer-view">
                    ${q.options.map(option => `
                        <div class="normal-answer-row">
                            <span>${escapeHtml(option)}</span>
                            <strong>0 Antworten</strong>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (q.type === 'text') {
            return `
                <div class="answer-card">
                    Noch keine Antworten vorhanden.
                </div>
            `;
        }

        if (q.type === 'rating') {
            return `
                <div class="normal-rating">
                    ${Array.from(
                        {length: Number(q.ratingSteps)},
                        () => q.ratingSymbol
                    ).join('')}
                </div>

                <p>Noch keine Bewertungen vorhanden.</p>
            `;
        }

        if (q.type === 'date') {
            return `
                <div class="normal-answer-view">
                    ${q.dates.map(date => `
                        <div class="normal-answer-row">
                            <span>
                                ${
                                    date
                                    ? new Date(date + 'T00:00:00').toLocaleDateString('de-DE')
                                    : 'Datum nicht festgelegt'
                                }
                            </span>
                            <strong>0 Antworten</strong>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (q.type === 'ranking') {
            return `
                <div class="normal-answer-view">
                    ${q.ranking.map((option, index) => `
                        <div class="normal-answer-row">
                            <span>${index + 1}. ${escapeHtml(option)}</span>
                            <strong>–</strong>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (q.type === 'likert') {
            return `
                <div class="answer-card">
                    Noch keine Antworten für diese Likert-Frage vorhanden.
                </div>
            `;
        }

        if (q.type === 'scale') {
            return `
                <div class="scale-row">
                    ${[0,1,2,3,4,5,6,7,8,9,10]
                        .map(n => `<div class="scale-cell">${n}</div>`)
                        .join('')}
                </div>

                <p>Durchschnitt: –</p>
            `;
        }
    }


    // Säulendiagramm.
    if (q.visualization === 'column') {

        if (q.type === 'choice') {
            return `
                <div class="empty-chart">

                    <div class="empty-columns">
                        ${q.options.map(option => `
                            <div class="empty-column-item">
                                <div class="empty-column"></div>
                                <span>${escapeHtml(option)}</span>
                            </div>
                        `).join('')}
                    </div>

                    <div class="no-data-message">
                        Noch keine Antworten vorhanden
                    </div>

                </div>
            `;
        }

        return noVisualizationData();
    }


    // Balkendiagramm.
    if (q.visualization === 'bar') {

        if (q.type === 'choice') {
            return `
                <div class="empty-bars">

                    ${q.options.map(option => `
                        <div class="empty-bar-row">

                            <span>
                                ${escapeHtml(option)}
                            </span>

                            <div class="empty-bar-track">
                                <div class="empty-bar-value"></div>
                            </div>

                            <strong>0</strong>

                        </div>
                    `).join('')}

                    <div class="no-data-message">
                        Noch keine Antworten vorhanden
                    </div>

                </div>
            `;
        }

        return noVisualizationData();
    }


    // Kreisdiagramm.
    if (q.visualization === 'pie') {

        if (q.type === 'choice') {
            return `
                <div class="empty-pie-container">

                    <div class="empty-pie">
                        <span>0</span>
                    </div>

                    <div class="pie-legend">

                        ${q.options.map(option => `
                            <div class="pie-legend-item">
                                <span class="legend-dot"></span>
                                ${escapeHtml(option)}
                                <strong>0</strong>
                            </div>
                        `).join('')}

                    </div>

                    <div class="no-data-message">
                        Noch keine Antworten vorhanden
                    </div>

                </div>
            `;
        }

        return noVisualizationData();
    }


    // Wortwolke.
    if (q.visualization === 'wordcloud') {

        if (q.type === 'text') {
            return `
                <div class="empty-wordcloud">
                    <span>☁</span>
                    <strong>Noch keine Antworten vorhanden</strong>
                    <small>
                        Sobald Antworten eingehen, werden häufig genannte
                        Begriffe hier als Wortwolke dargestellt.
                    </small>
                </div>
            `;
        }

        return `
            <div class="answer-card">
                Die Wortwolke eignet sich insbesondere für Textantworten.
            </div>
        `;
    }


    return noVisualizationData();
}


function noVisualizationData() {
    return `
        <div class="answer-card">
            Noch keine Antworten vorhanden.
        </div>
    `;
}

function getQuestionTypeLabel(type) {
    const labels = {
        choice: 'Auswahl',
        text: 'Text',
        rating: 'Bewertung',
        date: 'Datum',
        ranking: 'Rangfolge',
        likert: 'Likert',
        scale: 'Skala'
    };

    return labels[type] || type;
}

function buildPresentationMode() {

    if (questions.length === 0) {
        return `
            <div class="presentation-empty">
                <h2>Präsentieren</h2>
                <p>Es wurde noch keine Frage erstellt.</p>
            </div>
        `;
    }

    const q = questions[presentationIndex];

    return `
        <div class="presentation-wrapper">

            <div class="presentation-topbar">

                <div>
                    <div class="presentation-survey-title">
                        ${escapeHtml(document.getElementById('formTitle').value)}
                    </div>

                    <div class="presentation-question-counter-top">
                        Frage ${presentationIndex + 1} von ${questions.length}
                    </div>
                </div>

                <div class="presentation-responses">
                    0 übermittelte Antworten
                </div>

            </div>


            <div class="presentation-main">

                <div class="presentation-question">
                    ${escapeHtml(q.title)}
                </div>

                <div class="presentation-visualization">
                    ${renderVisualizationPreview(q)}
                </div>

            </div>


            <div class="presentation-bottom">

                <div class="presentation-visualization-control">

                    <span>Darstellung:</span>

                    <select
                        class="presentation-select"
                        onchange="changePresentationVisualization(this.value)"
                    >

                        <option value="normal"
                            ${q.visualization === 'normal' ? 'selected' : ''}>
                            Normale Ansicht
                        </option>

                        <option value="column"
                            ${q.visualization === 'column' ? 'selected' : ''}>
                            Säulendiagramm
                        </option>

                        <option value="bar"
                            ${q.visualization === 'bar' ? 'selected' : ''}>
                            Balkendiagramm
                        </option>

                        <option value="pie"
                            ${q.visualization === 'pie' ? 'selected' : ''}>
                            Kreisdiagramm
                        </option>

                        <option value="wordcloud"
                            ${q.visualization === 'wordcloud' ? 'selected' : ''}>
                            Wortwolke
                        </option>

                    </select>

                </div>


                <div class="presentation-navigation">

                    <button
                        class="presentation-nav-button"
                        onclick="navigatePresentation(-1)"
                        ${presentationIndex === 0 ? 'disabled' : ''}
                    >
                        ‹
                    </button>

                    <span>
                        ${presentationIndex + 1} von ${questions.length}
                    </span>

                    <button
                        class="presentation-nav-button"
                        onclick="navigatePresentation(1)"
                        ${presentationIndex === questions.length - 1 ? 'disabled' : ''}
                    >
                        ›
                    </button>

                </div>

            </div>

        </div>
    `;
}


function changePresentationVisualization(value) {

    questions[presentationIndex].visualization = value;

    document.getElementById('modalBody').innerHTML =
        buildPresentationMode();
}


function navigatePresentation(direction) {

    const newIndex = presentationIndex + direction;

    if (
        newIndex < 0 ||
        newIndex >= questions.length
    ) {
        return;
    }

    presentationIndex = newIndex;

    document.getElementById('modalBody').innerHTML =
        buildPresentationMode();
}

function getSurveyAvailability() {

    const now = new Date();

    // Startdatum prüfen
    if (
        surveySettings.startEnabled &&
        surveySettings.startDateTime
    ) {
        const start = new Date(surveySettings.startDateTime);

        if (now < start) {
            return {
                available: false,
                reason: 'notStarted',
                date: start
            };
        }
    }

    // Enddatum prüfen
    if (
        surveySettings.endEnabled &&
        surveySettings.endDateTime
    ) {
        const end = new Date(surveySettings.endDateTime);

        if (now > end) {
            return {
                available: false,
                reason: 'ended',
                date: end
            };
        }
    }

    return {
        available: true
    };
}

function getPreviewQuestions() {

    // Neue Kopie erstellen
    let previewQuestions = [...questions];

    // Wenn keine zufällige Reihenfolge gewünscht ist:
    if (!surveySettings.randomOrder) {
        return previewQuestions;
    }

    // Reihenfolge nur EINMAL erzeugen
    if (
        !previewQuestionOrder ||
        previewQuestionOrder.length !== questions.length
    ) {
        previewQuestionOrder = questions.map((q, index) => index);

        // Fisher-Yates-Shuffle
        for (let i = previewQuestionOrder.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));

            [
                previewQuestionOrder[i],
                previewQuestionOrder[j]
            ] = [
                previewQuestionOrder[j],
                previewQuestionOrder[i]
            ];
        }
    }

    return previewQuestionOrder.map(index => questions[index]);
}

function startSurveyTimer() {

    // Kein Timer aktiviert.
    if (!surveySettings.timerEnabled) {
        return;
    }

    // Bereits abgelaufen.
    if (surveyTimerExpired) {
        return;
    }

    // Endzeit nur beim ersten Start festlegen.
    if (!surveyTimerEnd) {
        const minutes = Math.max(
            1,
            Number(surveySettings.timerMinutes) || 1
        );

        surveyTimerEnd = Date.now() + (minutes * 60 * 1000);
    }

    // Eventuell alten Interval-Timer entfernen.
    if (surveyTimerInterval) {
        clearInterval(surveyTimerInterval);
    }

    updateSurveyTimer();

    surveyTimerInterval = setInterval(() => {
        updateSurveyTimer();
    }, 1000);
}


function updateSurveyTimer() {

    if (!surveyTimerEnd) {
        return;
    }

    const remaining = surveyTimerEnd - Date.now();

    if (remaining <= 0) {

        surveyTimerExpired = true;

        if (surveyTimerInterval) {
            clearInterval(surveyTimerInterval);
            surveyTimerInterval = null;
        }

        const timerElement =
            document.getElementById('surveyTimer');

        if (timerElement) {
            timerElement.textContent = '00:00';
            timerElement.classList.add('expired');
        }

        lockSurveyAfterTimer();

        return;
    }

    const totalSeconds = Math.ceil(remaining / 1000);

    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    const timerElement =
        document.getElementById('surveyTimer');

    if (timerElement) {
        timerElement.textContent =
            String(minutes).padStart(2, '0') +
            ':' +
            String(seconds).padStart(2, '0');
    }
}


function lockSurveyAfterTimer() {

    const preview =
        document.querySelector('.participant-preview');

    if (!preview) {
        return;
    }

    preview.querySelectorAll(
        'input, textarea, select, button'
    ).forEach(element => {

        // X zum Schließen des Vorschaufensters gehört nicht
        // zur Umfrage und wird dadurch nicht betroffen.
        if (!element.classList.contains('close-modal')) {
            element.disabled = true;
        }
    });

    let message =
        document.getElementById('timerExpiredMessage');

    if (!message) {
        message = document.createElement('div');
        message.id = 'timerExpiredMessage';
        message.className = 'timer-expired-message';

        message.innerHTML = `
            <strong>Die Bearbeitungszeit ist abgelaufen.</strong>
            <span>
                Die Umfrage kann nicht mehr bearbeitet werden.
            </span>
        `;

        preview.prepend(message);
    }
}

function buildPreview() {
        const availability = getSurveyAvailability();

    if (!availability.available) {

        if (availability.reason === 'notStarted') {
            return `
                <div class="participant-preview">

                    <div class="participant-header">
                        <h2>${escapeHtml(document.getElementById('formTitle').value)}</h2>
                    </div>

                    <div class="participant-question-card">
                        <h3>Die Umfrage ist noch nicht geöffnet.</h3>

                        <p>
                            Die Teilnahme ist möglich ab:
                            <strong>
                                ${availability.date.toLocaleString('de-DE')}
                            </strong>
                        </p>
                    </div>

                </div>
            `;
        }

        if (availability.reason === 'ended') {
            return `
                <div class="participant-preview">

                    <div class="participant-header">
                        <h2>${escapeHtml(document.getElementById('formTitle').value)}</h2>
                    </div>

                    <div class="participant-question-card">
                        <h3>Die Umfrage ist beendet.</h3>

                        <p>
                            Der Teilnahmezeitraum ist abgelaufen.
                        </p>
                    </div>

                </div>
            `;
        }
    }
    const previewQuestions = getPreviewQuestions();
let html = `
    <div class="participant-preview">

        ${
            surveySettings.timerEnabled
            ? `
                <div class="survey-timer-box">
                    <span class="survey-timer-label">
                        Verbleibende Bearbeitungszeit
                    </span>

                    <strong id="surveyTimer">
                        --:--
                    </strong>
                </div>
            `
            : ''
        }
            <div class="participant-header">
                <h2>${escapeHtml(document.getElementById('formTitle').value)}</h2>
                <p>Bitte beantworte die folgenden Fragen.</p>
            </div>
    `;

    previewQuestions.forEach((q, i) => {
        html += `
            <div class="participant-question-card">
                <div class="participant-question-title">
                    <span class="participant-number">${i + 1}</span>
                    <span>${escapeHtml(q.title)}</span>
                    ${q.required ? '<span class="required-mark">*</span>' : ''}
                </div>
        `;

        if (q.type === 'choice') {
            html += `
                <div class="participant-options">
                    ${q.options.map((option, oi) => `
                        <label class="participant-option">
                            <input
                                type="${q.multiple ? 'checkbox' : 'radio'}"
                                name="preview_choice_${i}"
                            >
                            <span>${escapeHtml(option)}</span>
                        </label>
                    `).join('')}
                </div>
            `;
        }

        if (q.type === 'text') {
            if (q.longAnswer) {
                html += `
                    <textarea
                        class="participant-textarea"
                        placeholder="Antwort eingeben..."
                    ></textarea>
                `;
            } else {
                html += `
                    <input
                        class="participant-text-input"
                        type="text"
                        placeholder="Antwort eingeben..."
                    >
                `;
            }
        }

        if (q.type === 'rating') {
            html += `
                <div class="participant-rating">
                    ${Array.from({length: Number(q.ratingSteps)}, (_, ri) => `
                        <button
                            type="button"
                            class="rating-symbol"
                            onclick="selectRating(this)"
                        >
                            ${q.ratingSymbol}
                        </button>
                    `).join('')}
                </div>
            `;
        }

        if (q.type === 'date') {
            html += `
                <div class="participant-date-options">
                    ${q.dates.map((date, di) => `
                        <label class="participant-option date-option">
                            <input
                                type="${q.multiple ? 'checkbox' : 'radio'}"
                                name="preview_date_${i}"
                            >
                            <span>
                                ${
                                    date
                                        ? new Date(date + 'T00:00:00').toLocaleDateString('de-DE')
                                        : 'Datum noch nicht festgelegt'
                                }
                            </span>
                        </label>
                    `).join('')}
                </div>
            `;

            if (q.allowDateSuggestions) {
                html += `
                    <div class="date-suggestion">
                        <span>Eigener Terminvorschlag</span>
                        <input
                            class="participant-date-input"
                            type="date"
                            onclick="this.showPicker && this.showPicker()"
                        >
                    </div>
                `;
            }
        }

        if (q.type === 'ranking') {
            html += `
                <div class="participant-ranking">
                    ${q.ranking.map((option, ri) => `
                        <div
                            class="ranking-card"
                            draggable="true"
                            ondragstart="rankingDragStart(event)"
                            ondragover="event.preventDefault()"
                            ondrop="rankingDrop(event)"
                        >
                            <span class="drag-handle">☰</span>
                            <span>${escapeHtml(option)}</span>
                        </div>
                    `).join('')}
                </div>

                <div class="participant-hint">
                    Ziehe die Antworten in die gewünschte Reihenfolge.
                </div>
            `;
        }

        if (q.type === 'likert') {
            html += `
                <div class="participant-likert-wrapper">
                    <table class="participant-likert">
                        <thead>
                            <tr>
                                <th></th>
                                ${q.likertCols.map(col => `
                                    <th>${escapeHtml(col)}</th>
                                `).join('')}
                            </tr>
                        </thead>

                        <tbody>
                            ${q.likertRows.map((row, ri) => `
                                <tr>
                                    <td>${escapeHtml(row)}</td>

                                    ${q.likertCols.map((col, ci) => `
                                        <td>
                                            <input
                                                type="radio"
                                                name="preview_likert_${i}_${ri}"
                                            >
                                        </td>
                                    `).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        if (q.type === 'scale') {
            html += `
                <div class="participant-scale">
                    <div class="participant-scale-values">
                        ${[0,1,2,3,4,5,6,7,8,9,10].map(value => `
                            <button
                                type="button"
                                class="scale-button"
                                onclick="selectScaleValue(this)"
                            >
                                ${value}
                            </button>
                        `).join('')}
                    </div>

                    <div class="participant-scale-labels">
                        <span>${escapeHtml(q.scaleLeft)}</span>
                        <span>${escapeHtml(q.scaleRight)}</span>
                    </div>
                </div>
            `;
        }

        html += `
            </div>
        `;
    });

    html += `
        <button type="button" class="participant-submit">
            Antworten absenden
        </button>
    `;

    html += `</div>`;

    return html;
}

function selectRating(button) {
    const container = button.parentElement;
    const buttons = Array.from(container.querySelectorAll('.rating-symbol'));
    const selectedIndex = buttons.indexOf(button);

    buttons.forEach((btn, index) => {
        btn.classList.toggle('selected', index <= selectedIndex);
    });
}

function selectScaleValue(button) {
    const container = button.parentElement;

    container.querySelectorAll('.scale-button').forEach(btn => {
        btn.classList.remove('selected');
    });

    button.classList.add('selected');
}

let draggedRankingItem = null;

function rankingDragStart(event) {
    draggedRankingItem = event.currentTarget;
}

function rankingDrop(event) {
    event.preventDefault();

    const target = event.currentTarget;

    if (
        draggedRankingItem &&
        target !== draggedRankingItem
    ) {
        const container = target.parentElement;

        const items = Array.from(container.children);

        const draggedIndex = items.indexOf(draggedRankingItem);
        const targetIndex = items.indexOf(target);

        if (draggedIndex < targetIndex) {
            target.after(draggedRankingItem);
        } else {
            target.before(draggedRankingItem);
        }
    }
}

function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[m]));
}

function escapeAttr(text) {
    return escapeHtml(text);
}

document.getElementById('modal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal();
    }
});

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {
        closeModal();
        return;
    }

    const modalContent = document.querySelector('.modal-content');

    if (!modalContent.classList.contains('presentation-mode')) {
        return;
    }

    if (event.key === 'ArrowRight') {
        navigatePresentation(1);
    }

    if (event.key === 'ArrowLeft') {
        navigatePresentation(-1);
    }
});

</script>

<?php
echo $OUTPUT->footer();
?>
