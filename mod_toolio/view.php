<?php
/**
 * Haupteinstieg von mod_toolio — originalgetreue Umsetzung des Klick-Prototyps
 * (ideas/toolio-click-prototype/index.html).
 *
 * Aufbau: fester 4:3-Karten-Rahmen mit kontextueller Fußleiste. Der Moodle-Switch
 * (Bearbeiten AN/AUS) bildet die Prototyp-Phasen ab:
 *   🟢 LK ON  = Erstellen : Methoden-Globus → Vorbereiten (Material · Tool · Sozialform)
 *   🔵 LK OFF = Durchführen: Gruppen-Grid + Timer/Bearbeiten-Sichern
 *   🟡 SuS    = Arbeiten  : Tool-Platzhalter oder Warte-Minispiel
 *
 * Persistenz (ADR-0003): »Starten« schreibt den Zyklus über save.php in die DB.
 * Es gibt bewusst KEINE Zyklusbar mehr (der Prototyp kennt keine).
 */

require('../../config.php');

$id     = required_param('id', PARAM_INT);
$cm     = get_coursemodule_from_id('toolio', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/toolio/view.php', ['id' => $id]);
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_secondary_navigation(false);
$PAGE->activityheader->disable();

// Ansicht (Switch) zentral ermitteln — siehe classes/view_mode.php.
$view      = \mod_toolio\view_mode::detect($context, $PAGE);
$isteacher = \mod_toolio\view_mode::is_teacher($view);

// Bearbeitungsmodus per URL umschalten (?edit=1|0&sesskey=…) — Einstellungs-/Switch-Handling.
$edit = optional_param('edit', -1, PARAM_BOOL);
if ($edit !== -1 && $isteacher && confirm_sesskey()) {
    $USER->editing = (int) $edit;
    redirect($PAGE->url);
}
$editonurl  = $isteacher
    ? (new moodle_url('/mod/toolio/view.php', ['id' => $id, 'edit' => 1, 'sesskey' => sesskey()]))->out(false) : '';
$editoffurl = $isteacher
    ? (new moodle_url('/mod/toolio/view.php', ['id' => $id, 'edit' => 0, 'sesskey' => sesskey()]))->out(false) : '';

// Zyklus-Rückgrat (ADR-0003): LK legt Standard-Zyklus bei Bedarf an, SuS lesen nur.
$toolioid = (int) $cm->instance;
$cycleid  = $isteacher
    ? \mod_toolio\store::ensure_default_cycle($toolioid)
    : \mod_toolio\store::get_cycle($toolioid);
$state = ($cycleid !== null) ? \mod_toolio\store::load_gruppentool((int) $cycleid) : null;

// Echte Kursteilnehmer (Lernende) statt Zufallsnamen: eingeschriebene Nutzer ohne
// Verwaltungsrecht sind die Lernenden; Moodle-Kursgruppen liefern die Gruppenaufteilung.
$teacherids = array_keys(get_users_by_capability($context, 'moodle/course:manageactivities', 'u.id'));
$enrolled   = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);
$learners   = [];                       // userid => Anzeigename
foreach ($enrolled as $u) {
    if (in_array((int) $u->id, array_map('intval', $teacherids), true)) {
        continue;
    }
    $learners[(int) $u->id] = fullname($u);
}

$coursegroups = groups_get_all_groups($course->id);
$groups       = [];
$grouped      = [];                      // userid => erste Gruppenbezeichnung
foreach ($coursegroups as $g) {
    $members = groups_get_members($g->id, 'u.id');
    $names   = [];
    $ids     = [];
    foreach ($members as $m) {
        if (!isset($learners[(int) $m->id])) {
            continue;
        }
        $names[] = $learners[(int) $m->id];
        $ids[]   = (int) $m->id;
        if (!isset($grouped[(int) $m->id])) {
            $grouped[(int) $m->id] = format_string($g->name);
        }
    }
    if (!empty($names)) {
        $groups[] = ['id' => 'g' . (int) $g->id, 'name' => format_string($g->name),
            'students' => $names, 'studentids' => $ids];
    }
}

// Flache Teilnehmerliste (alle Lernenden, mit Gruppenlabel) fuer Einzel-/Partnerarbeit.
$participants = [];
foreach ($learners as $uid => $name) {
    $participants[] = ['id' => (int) $uid, 'name' => $name, 'group' => $grouped[$uid] ?? '—'];
}

// Fallback: keine Moodle-Gruppen definiert -> alle Lernenden als eine Gruppe.
if (empty($groups) && !empty($learners)) {
    $groups[] = ['id' => 'all', 'name' => 'Kurs', 'students' => array_values($learners),
        'studentids' => array_map('intval', array_keys($learners))];
}

$boardbaseurl = trim((string) get_config('mod_toolio', 'boardurl'));

// Uebermittlung ERST nach dem Speichern: LK OFF/Schueler zeigen ausschliesslich den
// gespeicherten Zyklus-Store ($state). Die Live-Engine (toolio_gt_*) wird hier NICHT
// mehr eingespielt — der beim "Aenderungen speichern" in save.php eingefrorene Snapshot
// ist maßgeblich. Das LK-ON-Board laedt seinen Live-Stand weiterhin ueber die Engine.

// Defensive Raum-Registrierung: falls der gespeicherte Store bereits Board-Raeume
// enthaelt (Board ist das aktive Werkzeug), stellen wir sicher, dass diese in der DB
// registriert sind — sonst liefert der Storage-Endpoint 404. So funktioniert das Board
// auch nach einem Upgrade/Import, ohne dass erneut gespeichert werden muss.
if (!empty($state['tool']) && $state['tool'] === 'board' && !empty($state['boardrooms'])) {
    foreach ($state['boardrooms'] as $room) {
        if (!empty($room['roomid']) && isset($room['groupid'])) {
            \mod_toolio\board::register_room((string) $room['roomid'], (int) $id, (int) $room['groupid']);
        }
    }
}

$boot = [
    'view'         => $view,
    'cmid'         => $id,
    'sesskey'      => sesskey(),
    'saveurl'      => (new moodle_url('/mod/toolio/save.php'))->out(false),
    'viewurl'      => (new moodle_url('/mod/toolio/view.php', ['id' => $id]))->out(false),
    'sseurl'       => (new moodle_url('/mod/toolio/sse.php', ['id' => $id]))->out(false),
    'pollurl'      => (new moodle_url('/mod/toolio/poll.php', ['id' => $id]))->out(false),
    'editOnUrl'    => $editonurl,
    'editOffUrl'   => $editoffurl,
    'state'        => $state,
    'groups'       => $groups,
    'participants' => $participants,
    'me'           => fullname($USER),
    'meid'         => (int) $USER->id,
    'board'        => [
      'enabled' => ($boardbaseurl !== ''),
      'baseurl' => $boardbaseurl,
    ],
];

// Gruppentool-Engine (1:1-Port): eigene Endpunkte + Assets + GM_MOODLE-Konfiguration.
$gtediting = $isteacher && $PAGE->user_is_editing();
$boot['gt'] = [
    'assetbase' => (new moodle_url('/mod/toolio/tools/gruppentool/public/'))->out(false),
    'rev'       => (string) get_config('mod_toolio', 'version'),
    'gm' => [
        'cmid'          => $id,
        'userid'        => (int) $USER->id,
        'canmanage'     => $gtediting,
        'isstudentview' => !$isteacher,
        'sesskey'       => sesskey(),
        'ajaxurl'       => (new moodle_url('/mod/toolio/tools/gruppentool/ajax.php', ['id' => $id]))->out(false),
        'sseurl'        => (new moodle_url('/mod/toolio/tools/gruppentool/sse.php', ['id' => $id]))->out(false),
    ],
];

$cmname = format_string($cm->name);

echo $OUTPUT->header();
?>
<style>
.tio-root { --ink:#0f172a; --sub:#475569; --line:#e2e8f0; --card:#ffffff; --stage:#f8fafc;
    font-family:"Segoe UI","Helvetica Neue",system-ui,sans-serif; color:var(--ink); }
.tio-root *, .tio-root *::before, .tio-root *::after { box-sizing:border-box; }
.tio-root.mode-create, .tio-root.mode-live { background:#ecfdf5; }
.tio-root.mode-student { background:#ffffff; }
.tio-wrap { max-width:1180px; margin:0 auto; padding:12px 8px 16px; }

/* 4:3-Karten-Rahmen */
.tio-card { max-width:1180px; margin:0 auto; overflow:hidden; border:1px solid var(--line);
    border-radius:16px; background:var(--card); box-shadow:0 1px 2px rgba(15,23,42,.06); }
.tio-aspect { aspect-ratio:4/3; }
.tio-frame { display:flex; height:100%; flex-direction:column; }
.tio-stage { position:relative; min-height:0; flex:1; overflow:hidden; }
.tio-foot { height:80px; flex-shrink:0; border-top:1px solid var(--line);
    background:#f8fafc; padding:0 28px; }
.tio-foot-in { display:flex; height:100%; align-items:center; justify-content:space-between;
    gap:12px; font-size:.75rem; font-weight:600; color:#334155; }

/* schwarze Pille */
.tio-pill { border:1px solid #000; background:#000; color:#fff; border-radius:999px;
    padding:10px 24px; font-size:.85rem; font-weight:800; cursor:pointer;
    transition:transform .15s ease, box-shadow .15s ease; }
.tio-pill:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(15,23,42,.18); }

/* ── Methoden-Globus ── */
.tio-canvas { position:absolute; inset:0; overflow:hidden; background:var(--stage);
    perspective:760px; }
.tio-links { position:absolute; inset:0; width:100%; height:100%; pointer-events:none; z-index:1; }
.tio-links line { stroke:rgba(100,116,139,0); stroke-width:1;
    transition:opacity .14s ease, stroke .14s ease; }
.tio-node { position:absolute; border:0; background:transparent; color:#1f2937;
    font-weight:400; cursor:pointer; white-space:nowrap; z-index:2; padding:0;
    transform:translate(-50%,-50%) translateZ(var(--nz,0px)) rotateX(var(--nrx,0deg)) rotateY(var(--nry,0deg)) scale(var(--ns,1));
    text-shadow:0 1px 0 rgba(248,250,252,.9), 0 0 10px rgba(15,23,42,.14);
    transition:transform .2s ease, color .2s ease, opacity .3s ease; will-change:transform,opacity; }
.tio-node:hover, .tio-node.is-active {
    transform:translate(-50%,-50%) translateZ(calc(var(--nz,0px) + 12px)) rotateX(var(--nrx,0deg)) rotateY(var(--nry,0deg)) scale(calc(var(--ns,1) * 1.12));
    color:#020617; }
.tio-node.is-active { font-weight:600; text-shadow:0 1px 0 #fff, 0 0 16px rgba(15,23,42,.24); }
.tio-node.k-method { color:#0f172a; }
.tio-node.k-social { color:#475569; }
.tio-node.k-tool { color:#334155; }
.tio-node.sz-lg { font-size:1.55rem; }
.tio-node.sz-md { font-size:1.2rem; }
.tio-node.sz-sm { font-size:1rem; }
.tio-node.is-muted { color:#94a3b8; text-shadow:none; }

/* ── Vorbereiten-Schritte ── */
.tio-prep { height:100%; width:100%; padding:14px; }
.tio-drop { display:flex; height:100%; min-height:0; flex-direction:column; align-items:center;
    justify-content:center; gap:6px; border:2px dashed #94a3b8; border-radius:14px;
    background:#f8fafc; padding:24px; text-align:center; }
.tio-drop.drag { border-color:#0f172a; background:#eef2ff; }
.tio-drop h3 { margin:0; font-size:1.05rem; font-weight:700; color:#1e293b; }
.tio-drop p { margin:2px 0 0; font-size:.82rem; color:#475569; max-width:34rem; }
.tio-drop-actions { display:flex; flex-direction:column; gap:8px; margin-top:14px; }
.tio-ghostbtn { border:1px solid #334155; background:#fff; color:#1e293b; border-radius:999px;
    padding:8px 16px; font-size:.82rem; font-weight:700; cursor:pointer; }
.tio-ghostbtn:hover { background:#f1f5f9; }
.tio-matbox { margin-top:18px; width:100%; max-width:640px; border:1px solid var(--line);
    background:#fff; border-radius:12px; padding:10px 12px; text-align:left; box-shadow:0 1px 2px rgba(15,23,42,.05); }
.tio-matbox h4 { margin:0 0 8px; font-size:.7rem; font-weight:800; letter-spacing:.05em;
    text-transform:uppercase; color:#475569; }
.tio-matlist { list-style:none; margin:0; padding:0; max-height:8rem; overflow:auto;
    display:flex; flex-direction:column; gap:4px; }
.tio-matlist li { display:flex; align-items:center; gap:8px; border:1px solid var(--line);
    background:#f8fafc; border-radius:8px; padding:5px 8px; font-size:.83rem; font-weight:600; }
.tio-matlist li span.t { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tio-matlist button { border:0; background:transparent; color:#64748b; cursor:pointer;
    font-size:.9rem; line-height:1; padding:2px 6px; border-radius:6px; }
.tio-matlist button:hover { background:#e2e8f0; color:#0f172a; }

.tio-tools { display:grid; grid-template-columns:repeat(3,1fr); height:100%; }
.tio-tool { padding:24px; cursor:pointer; border-right:1px solid var(--line); }
.tio-tool:last-child { border-right:0; }
.tio-tool h3 { display:inline-block; margin:0; padding-bottom:4px; font-size:1.5rem;
    font-weight:900; letter-spacing:-.01em; line-height:1; color:#64748b;
    border-bottom:3px solid transparent; }
.tio-tool.on h3 { color:#0f172a; border-bottom-color:#0284c7; }
.tio-tool .pitch { margin:16px 0 0; font-size:.92rem; font-weight:600; color:#64748b; }
.tio-tool.on .pitch { color:#334155; }
.tio-tool ul { list-style:none; margin:16px 0 0; padding:0; display:flex; flex-direction:column;
    gap:7px; font-size:.82rem; color:#64748b; }
.tio-tool.on ul { color:#334155; }
.tio-tool ul li { display:flex; gap:8px; }
.tio-tool ul li::before { content:"•"; color:#94a3b8; }
.tio-tool .res { margin:16px 0 0; font-size:.92rem; font-weight:600; color:#64748b; }
.tio-tool.on .res { color:#334155; }

/* ── Abfrage-Editor (MS-Forms-Stil) ── */
.tio-abf { display:flex; flex-direction:column; height:100%; background:#f3f3f3; overflow:hidden; }
.tio-abf-titlebar { display:flex; align-items:center; gap:8px; padding:8px 14px 6px; background:#fff;
    border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.tio-abf-maintitle { flex:1; border:none; border-bottom:2px solid #7b3fa8; padding:5px 2px;
    font-size:1.1rem; font-weight:700; color:#1e293b; outline:none; background:transparent; }
.tio-abf-maintitle::placeholder { color:#94a3b8; font-weight:400; }
.tio-abf-savebtn { border:none; background:#7b3fa8; color:#fff; border-radius:6px;
    padding:6px 14px; font-size:.78rem; font-weight:700; cursor:pointer; white-space:nowrap; }
.tio-abf-savebtn:hover { background:#6b2f98; }
.tio-abf-savebtn:disabled { opacity:.55; cursor:wait; }
.tio-abf-feedback { font-size:.74rem; color:#475569; min-width:76px; }

.tio-abf-qlist { flex:1; overflow-y:auto; padding:10px 14px 4px; display:flex; flex-direction:column; gap:0; }

/* Fragenkarte */
.tio-qcard { background:#fff; border-radius:4px; border-top:5px solid #7b3fa8;
    margin-bottom:16px; box-shadow:0 1px 3px rgba(15,23,42,.08); }
.tio-qcard-toolbar { display:flex; justify-content:flex-end; padding:8px 12px 4px; }
.tio-qcard-iconbtn { border:none; background:transparent; color:#94a3b8; font-size:1rem;
    cursor:pointer; padding:2px 6px; border-radius:4px; line-height:1; }
.tio-qcard-iconbtn:hover { color:#7b3fa8; background:#f3e8ff; }
.tio-qcard-body { padding:0 20px 14px; }
.tio-qcard-qrow { display:grid; grid-template-columns:28px 1fr; align-items:center; gap:10px; margin-bottom:14px; }
.tio-qcard-num { font-size:.8rem; font-weight:800; color:#7b3fa8; text-align:center; }
.tio-qcard-qinput { width:100%; border:1px solid #d8d8d8; border-radius:4px; background:#fff;
    padding:10px 12px; font-size:1rem; font-weight:600; outline:none; transition:.15s; }
.tio-qcard-qinput:focus { border-color:#7b3fa8; box-shadow:0 0 0 2px rgba(123,63,168,.15); }

/* Optionen */
.tio-qcard-opts { padding-left:32px; display:flex; flex-direction:column; gap:6px; }
.tio-qcard-optrow { display:flex; align-items:center; gap:8px; }
.tio-qcard-bullet { color:#7b3fa8; font-size:1rem; min-width:16px; }
.tio-qcard-optinput { flex:1; border:none; border-bottom:1px solid #d8d8d8; padding:6px 4px;
    font-size:.9rem; outline:none; background:transparent; transition:.15s; }
.tio-qcard-optinput:focus { border-bottom-color:#7b3fa8; }
.tio-qcard-optdel { border:none; background:transparent; color:#cbd5e1; font-size:1rem;
    cursor:pointer; padding:2px 5px; border-radius:4px; }
.tio-qcard-optdel:hover { color:#dc2626; background:#fee2e2; }
.tio-qcard-addopt { color:#5b2d8a; font-weight:700; cursor:pointer; font-size:.85rem;
    margin-top:4px; display:inline-block; padding:4px 0; }
.tio-qcard-addopt:hover { color:#7b3fa8; }
.tio-qcard-hint { color:#94a3b8; font-size:.82rem; font-style:italic; padding-left:32px; margin-bottom:4px; }
.tio-qcard-stars { color:#7b3fa8; font-size:1.4rem; letter-spacing:6px; padding-left:32px; margin-bottom:4px; }

/* Karten-Footer */
.tio-qcard-footer { border-top:1px solid #ececec; padding:10px 20px; display:flex;
    align-items:center; gap:16px; flex-wrap:wrap; }
.tio-qcard-sel { border:none; background:#f3f3f3; border-radius:4px; padding:5px 10px;
    font-size:.78rem; font-weight:600; cursor:pointer; outline:none; color:#475569; }
.tio-qcard-req { display:flex; align-items:center; gap:5px; font-size:.78rem; font-weight:600;
    color:#64748b; cursor:pointer; margin-left:auto; }
.tio-qcard-req input[type="checkbox"] { accent-color:#7b3fa8; width:15px; height:15px; cursor:pointer; }

/* Frage hinzufügen */
.tio-abf-addarea { padding:4px 0 12px; }
.tio-abf-addlabel { display:inline-flex; align-items:center; gap:8px; font-size:.9rem;
    font-weight:700; color:#5b2d8a; margin-bottom:10px; }
.tio-abf-addplus { background:#7b3fa8; color:#fff; width:22px; height:22px; border-radius:50%;
    display:inline-flex; align-items:center; justify-content:center; font-size:1rem; line-height:1; }
.tio-abf-addtypes { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
.tio-abf-typecard { border:1.5px solid #d8b4fe; border-radius:6px; background:#fff;
    padding:10px 8px; display:flex; flex-direction:column; align-items:center; gap:4px;
    cursor:pointer; font-size:.75rem; font-weight:700; color:#5b2d8a;
    transition:background .15s, border-color .15s; }
.tio-abf-typecard:hover { background:#f3e8ff; border-color:#7b3fa8; }
.tio-abf-typeicon { font-size:1.2rem; }

/* ── Abfrage LK OFF ── */
.tio-abf-lkoff { display:flex; flex-direction:column; height:100%; padding:14px; gap:10px; overflow:auto; background:#faf5ff; }
.tio-abf-lkoff-hdr { display:flex; align-items:center; gap:10px; background:#7b3fa8; color:#fff; border-radius:10px; padding:10px 16px; }
.tio-abf-lkoff-hdr .icon { font-size:1.4rem; }
.tio-abf-lkoff-hdr .ttl { flex:1; font-size:1rem; font-weight:800; }
.tio-abf-lkoff-hdr .cnt { font-size:.8rem; background:rgba(255,255,255,.2); border-radius:999px; padding:3px 10px; }
.tio-abf-lkoff-empty { color:#7b3fa8; font-size:.9rem; text-align:center; padding:20px; }
.tio-abf-lkoff-list { display:flex; flex-direction:column; gap:6px; flex:1; min-height:0; overflow-y:auto; }
.tio-abf-lkoff-card { background:#fff; border:1px solid #e9d5ff; border-radius:8px; padding:8px 12px;
    display:flex; align-items:flex-start; gap:8px; flex-wrap:wrap; }
.tio-abf-lkoff-card .num { font-size:.75rem; font-weight:800; color:#7b3fa8; min-width:18px; padding-top:1px; }
.tio-abf-lkoff-card .qtext { flex:1; font-size:.85rem; font-weight:600; }
.tio-abf-lkoff-card .qtype { font-size:.7rem; background:#f3e8ff; color:#7b3fa8; border-radius:999px;
    padding:2px 8px; font-weight:600; white-space:nowrap; }
.tio-abf-lkoff-opts { list-style:none; margin:4px 0 0 18px; padding:0; width:100%;
    display:flex; flex-wrap:wrap; gap:4px; }
.tio-abf-lkoff-opts li { font-size:.75rem; background:#f3e8ff; border-radius:6px; padding:2px 8px; }
.tio-abf-lkoff-foot { text-align:center; font-size:.78rem; color:#7b3fa8; padding:4px; background:#f3e8ff; border-radius:8px; }

/* ── Abfrage SuS ── */
.tio-abf-sus { display:flex; flex-direction:column; height:100%; padding:14px; overflow:auto; }
.tio-abf-sus-wait { color:#64748b; font-size:1rem; text-align:center; margin:auto; }
.tio-abf-sus-title { font-size:1.05rem; font-weight:800; color:#0f172a; margin:0 0 12px; }
.tio-abf-sus-form { display:flex; flex-direction:column; gap:14px; }
.tio-abf-sus-field { display:flex; flex-direction:column; gap:6px; }
.tio-abf-sus-qlabel { display:flex; gap:6px; font-size:.88rem; font-weight:700; color:#1e293b; }
.tio-abf-sus-qlabel .num { color:#7b3fa8; }
.tio-abf-sus-qlabel .req { color:#dc2626; }
.tio-abf-sus-opt { display:flex; align-items:center; gap:6px; font-size:.85rem; cursor:pointer;
    padding:5px 8px; border:1px solid #e2e8f0; border-radius:6px; }
.tio-abf-sus-opt:has(input:checked) { border-color:#7b3fa8; background:#faf5ff; }
.tio-abf-sus-txt { border:1px solid #cbd5e1; border-radius:8px; padding:8px; font-size:.85rem;
    resize:vertical; font-family:inherit; outline:none; }
.tio-abf-sus-txt:focus { border-color:#7b3fa8; }
.tio-abf-sus-stars { display:flex; gap:4px; }
.tio-abf-sus-stars .star { font-size:1.5rem; color:#cbd5e1; cursor:pointer; }
.tio-abf-sus-stars .star:has(input:checked) ~ .star { color:#cbd5e1; }
.tio-abf-sus-stars:has(input:checked) .star:first-child { color:#f59e0b; }
.tio-abf-sus-scale { display:flex; gap:4px; flex-wrap:wrap; }
.tio-abf-sus-scale .scl { font-size:.8rem; font-weight:700; border:1px solid #e2e8f0; border-radius:6px;
    padding:4px 8px; cursor:pointer; }
.tio-abf-sus-scale .scl:has(input:checked) { border-color:#7b3fa8; background:#7b3fa8; color:#fff; }
.tio-abf-sus-submit { border:none; background:#7b3fa8; color:#fff; border-radius:999px;
    padding:10px 28px; font-size:.9rem; font-weight:800; cursor:pointer; align-self:flex-start; margin-top:4px; }
.tio-abf-sus-submit:hover { background:#6b2f98; }
.tio-abf-sus-submit:disabled { opacity:.6; cursor:default; }

/* Gruppentool-Whiteboard (Schritt 3 im Erstellen-Flow) */
.tio-gt { display:flex; height:100%; min-height:0; flex-direction:column; gap:10px; }
.tio-gt-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.tio-gt-seg { display:inline-flex; border:1px solid var(--line); border-radius:8px; overflow:hidden; background:#fff; }
.tio-gt-seg button { border:0; background:transparent; padding:6px 13px; font-size:.8rem; font-weight:700;
    color:#475569; cursor:pointer; }
.tio-gt-seg button.on { background:#0f172a; color:#fff; }
.tio-gt-num { font-size:.82rem; font-weight:700; color:#475569; min-width:74px; text-align:center; }
.tio-gt-count { width:30px; height:30px; border:1px solid var(--line); background:#fff; color:#0f172a;
    border-radius:8px; font-size:1.1rem; font-weight:700; line-height:1; cursor:pointer; }
.tio-gt-count:hover { border-color:#0f172a; }
.tio-gt-reset { margin-left:auto; border:1px solid var(--line); background:#fff; color:#475569;
    border-radius:8px; padding:6px 12px; font-size:.78rem; font-weight:700; cursor:pointer; }
.tio-gt-reset:hover { border-color:#bf4c44; color:#bf4c44; }
.tio-gt-body { flex:1; min-height:0; display:flex; gap:10px; }
.tio-gt-dockbtn { align-self:flex-start; width:26px; height:36px; border:1px solid var(--line);
    background:#f8fafc; color:#475569; border-radius:8px; font-size:1rem; font-weight:700; cursor:pointer; }
.tio-gt-dockbtn:hover { border-color:#0f172a; color:#0f172a; }
.tio-gt-dock { width:196px; flex-shrink:0; border:1px solid var(--line); border-radius:12px;
    background:#f8fafc; padding:10px; display:flex; flex-direction:column; gap:8px; overflow:auto; }
.tio-gt-dock-hd { display:flex; align-items:center; gap:6px; font-size:.78rem; font-weight:800; color:#0f172a; }
.tio-gt-dock-hd > span:first-child { flex:1; }
.tio-gt-dock-hd .cnt { min-width:20px; text-align:center; font-size:.72rem; font-weight:700;
    color:#475569; background:#e2e8f0; border-radius:999px; padding:1px 7px; }
.tio-gt-dock-hd .col { border:0; background:transparent; color:#94a3b8; font-size:1rem; line-height:1;
    cursor:pointer; padding:0 2px; }
.tio-gt-dock-hd .col:hover { color:#0f172a; }
.tio-gt-dock-sub { font-size:.72rem; font-weight:700; color:#94a3b8; margin-top:2px; }
.tio-gt-pool { display:flex; flex-direction:column; gap:0; min-height:44px; border-radius:8px; padding:2px; }
.tio-gt-pool.drag { background:#eef2ff; outline:2px dashed #94a3b8; }
.tio-gt-hint { font-size:.74rem; color:#94a3b8; font-style:italic; padding:4px 2px; }
/* Teilnehmer-Liste: Personen-Avatar + Name + Auge (Anwesenheit) */
.tio-gt-li { display:flex; align-items:center; gap:8px; padding:7px 4px; border-bottom:1px solid var(--line);
    font-size:.84rem; font-weight:600; color:#0f172a; cursor:grab; }
.tio-gt-li:last-child { border-bottom:0; }
.tio-gt-li:hover { background:rgba(79,110,145,.06); }
.tio-gt-av { width:22px; height:22px; padding:3px; border-radius:50%; background:#e8eef5;
    border:1px solid #d2dce8; color:#3f5874; flex-shrink:0; }
.tio-gt-li .nm { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tio-gt-li .eye { width:26px; height:26px; border:0; border-radius:999px; background:transparent;
    color:#94a3b8; display:inline-grid; place-items:center; cursor:pointer; flex-shrink:0; }
.tio-gt-li .eye svg { width:15px; height:15px; display:block; }
.tio-gt-li .eye:hover { background:rgba(79,110,145,.12); color:#0f172a; }
.tio-gt-li.off { cursor:default; opacity:.7; background:rgba(90,112,137,.05); }
.tio-gt-li.off .nm { text-decoration:line-through; }
.tio-gt-canvas { position:relative; flex:1; min-width:0; border:1px solid var(--line);
    border-radius:12px; background:#fff; overflow:hidden; }
/* Blumen-Anordnung: Konnektoren (SVG), Gruppen-Kern (Pille), Ring-Mitglieder (Kreis+Label) */
.tio-gt-fsvg { position:absolute; inset:0; pointer-events:none; z-index:1; }
.tio-gt-conn { stroke:#cbd5e1; stroke-width:2; stroke-linecap:round; }
.tio-gt-core { position:absolute; transform:translate(-50%,-50%); min-width:84px; max-width:150px;
    display:flex; align-items:center; justify-content:center; gap:6px;
    background:#0f172a; color:#fff; border-radius:999px; padding:5px 12px;
    box-shadow:0 4px 14px rgba(15,23,42,.2); cursor:grab; z-index:5; }
.tio-gt-core.drag { outline:3px solid #94a3b8; outline-offset:2px; }
.tio-gt-core-name { max-width:96px; border:0; background:transparent; color:#fff; text-align:center;
    font-size:.72rem; font-weight:800; padding:0; }
.tio-gt-core-name:focus { outline:none; }
.tio-gt-core .cnt { font-size:.64rem; font-weight:700; color:#cbd5e1; }
.tio-gt-member { position:absolute; transform:translate(-50%,-50%); display:grid; justify-items:center;
    gap:3px; width:74px; cursor:grab; z-index:3;
    animation:tio-gt-bloom .34s cubic-bezier(.22,1,.36,1) both; }
.tio-gt-member .dot { width:36px; height:36px; border-radius:50%; border:2px solid rgba(255,255,255,.85);
    color:#fff; font-size:.66rem; font-weight:800; display:grid; place-items:center;
    box-shadow:0 3px 10px rgba(15,23,42,.28); }
.tio-gt-member .lab { max-width:74px; text-align:center; font-size:.68rem; font-weight:700; color:#334155;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tio-gt-member:hover .dot { box-shadow:0 0 0 3px rgba(148,163,184,.4), 0 3px 10px rgba(15,23,42,.28); }
@keyframes tio-gt-bloom {
  from { opacity:0; transform:translate(calc(-50% + var(--dx,0px)), calc(-50% + var(--dy,0px))) scale(.35); }
  to   { opacity:1; transform:translate(-50%,-50%) scale(1); }
}
.tio-gt-empty { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    text-align:center; padding:20px; font-size:.85rem; line-height:1.5; color:#94a3b8; pointer-events:none; }

/* Step-Dots */
.tio-steps { display:flex; align-items:center; gap:2px; height:24px; }
.tio-step { position:relative; display:flex; width:40px; height:24px; align-items:center; justify-content:center; }
.tio-step button { position:relative; width:20px; height:20px; border-radius:999px; border:1px solid;
    background:transparent; padding:0; cursor:pointer; transition:all .15s ease; }
.tio-step .dot { position:absolute; left:50%; top:50%; width:12px; height:12px;
    transform:translate(-50%,-50%); border-radius:999px; }
.tio-step .cap { position:absolute; left:50%; top:calc(100% + 4px); width:64px;
    transform:translateX(-50%); text-align:center; font-size:9px; line-height:1.05; color:#475569; }
.tio-step-sep { display:flex; width:16px; height:24px; align-items:center; justify-content:center; }
.tio-step-sep span { display:block; width:12px; height:1px; background:#000; }

/* ── LIVE Gruppen-Grid ── */
.tio-live { height:100%; width:100%; padding:12px; }
.tio-live-in { position:relative; height:100%; min-height:0; overflow:hidden; border-radius:16px;
    background:#f8fafc; padding:6px; }
.tio-live-scroll { height:100%; overflow-y:auto; padding-bottom:12px; }
.tio-live-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px; }
.tio-gcard { aspect-ratio:4/3; width:100%; overflow:hidden; border-radius:16px; background:#fff;
    box-shadow:0 1px 2px rgba(15,23,42,.06); border:1px solid var(--line); cursor:pointer;
    text-align:left; position:relative; transition:transform .15s ease, box-shadow .15s ease; }
.tio-gcard:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(15,23,42,.1); }
.tio-gcard .gt { position:absolute; left:12px; right:12px; top:10px; font-size:.72rem;
    font-weight:700; color:#334155; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tio-gcard .ph { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; }
.tio-chip { border:1px solid var(--line); background:#fff; border-radius:10px; padding:8px 14px;
    font-size:.72rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#334155; }
/* Live-Board-Miniatur in der LK-OFF-Karte (nicht interaktiv, Klick oeffnet grosses Board) */
.tio-gcard-board { padding:0; }
.tio-gcard-live { position:absolute; inset:0; overflow:hidden; border-radius:16px; background:#f8fafc; }
.tio-gcard-live iframe { position:absolute; inset:0; width:100%; height:100%;
    border:0; pointer-events:none; }
.tio-gcard-live .ph { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; }
.tio-gcard-hit { position:absolute; inset:0; z-index:3; cursor:pointer; background:transparent; }
.tio-gcard-board .gt { z-index:4; pointer-events:none; background:rgba(255,255,255,.85);
    border-radius:8px; padding:2px 6px; left:8px; right:8px; top:8px; }
.tio-board-open { display:inline-flex; align-items:center; justify-content:center; border:1px solid #0f766e;
  color:#0f766e; background:#ecfeff; border-radius:10px; padding:8px 14px; font-size:.8rem;
  font-weight:800; letter-spacing:.02em; cursor:pointer; }
.tio-board-open:hover { background:#cffafe; }
.tio-overlay { position:absolute; inset:0; z-index:10; border-radius:16px; background:#fff;
    box-shadow:0 12px 32px rgba(15,23,42,.18); border:1px solid var(--line); }
.tio-overlay .gt { right:48px; font-size:.85rem; }
.tio-overlay-close { position:absolute; right:12px; top:10px; width:30px; height:30px;
    display:grid; place-items:center; border:1px solid var(--line); background:#fff;
    border-radius:999px; cursor:pointer; color:#334155; font-size:.9rem; }
.tio-overlay-actions { position:absolute; bottom:18px; left:50%; transform:translateX(-50%); }

/* Eingebettetes Board (Excalidraw-iframe) — deckt die Toolio-Karte voll aus */
.tio-boardframe { position:absolute; inset:0; z-index:20; border-radius:16px; overflow:hidden;
    background:#fff; box-shadow:0 12px 32px rgba(15,23,42,.22); border:1px solid var(--line);
    display:flex; flex-direction:column; }
.tio-boardframe-bar { flex:0 0 auto; display:flex; align-items:center; justify-content:space-between;
    gap:8px; padding:8px 12px; border-bottom:1px solid var(--line); background:#f8fafc; }
.tio-boardframe-bar .t { font-size:.8rem; font-weight:800; color:#0f766e; overflow:hidden;
    text-overflow:ellipsis; white-space:nowrap; }
.tio-boardframe iframe { flex:1 1 auto; width:100%; height:100%; border:0; display:block; }
.tio-boardframe-close { flex:0 0 auto; width:30px; height:30px; display:grid; place-items:center;
    border:1px solid var(--line); background:#fff; border-radius:999px; cursor:pointer;
    color:#334155; font-size:.9rem; }
.tio-board-missing { padding:24px; font-size:.85rem; color:#b45309; }
.tio-susboard { position:absolute; inset:0; z-index:15; border-radius:16px; overflow:hidden; background:#fff; }
.tio-susboard iframe.tio-susboard-frame { width:100%; height:100%; border:0; display:block; }

.tio-locked { display:flex; height:100%; width:100%; align-items:center; justify-content:center; padding:24px; }
.tio-locked-card { width:min(640px,100%); border-radius:18px; background:#f8fafc;
    padding:32px 28px; text-align:center; border:1px solid var(--line); }
.tio-locked-card .k { font-size:.72rem; font-weight:700; letter-spacing:.06em;
    text-transform:uppercase; color:#64748b; }
.tio-locked-card h3 { margin:8px 0 0; font-size:1.15rem; font-weight:700; color:#0f172a; }
.tio-locked-card p { margin:8px 0 0; font-size:.85rem; color:#475569; }
.tio-locked-card a { display:inline-block; margin-top:18px; border-radius:10px; background:#0f172a;
    color:#fff; padding:9px 18px; font-size:.82rem; font-weight:700; text-decoration:none; }

/* Timer / Toggle in LIVE-Footer */
.tio-timer { display:flex; height:40px; align-items:center; border:1px solid #000;
    background:#fff; border-radius:999px; padding:0 6px; gap:0; }
.tio-timer .t-clock { display:grid; place-items:center; width:24px; height:28px; border:0;
  position:relative; margin:0 6px; color:#0f172a; }
.tio-timer .t-clock svg { width:14px; height:14px; display:block; }
.tio-timer .sep { width:1px; height:28px; background:#94a3b8; margin:0; }
.tio-timer .grp { display:flex; align-items:center; gap:4px; }
.tio-timer .grp.l { margin-left:10px; }
.tio-timer .grp.r { margin-right:10px; }
.tio-timer .step { border:0; background:transparent; cursor:pointer; padding:0; width:16px; height:20px;
    display:inline-flex; align-items:center; justify-content:center; line-height:1;
    color:#475569; font-weight:600; font-size:12px; }
.tio-timer .step.sm { color:#94a3b8; font-weight:500; font-size:10px; }
.tio-timer .val { min-width:36px; width:auto; text-align:center; font-size:14px; font-weight:900;
    line-height:1; font-variant-numeric:tabular-nums; color:#0f172a; border:0; background:transparent; cursor:pointer; padding:0 2px; margin:0; }
.tio-timer .val-input { width:44px; text-align:center; font-size:14px; font-weight:900;
    line-height:1; font-variant-numeric:tabular-nums; color:#0f172a; border:0; background:transparent;
    outline:none; padding:0; margin:0; appearance:none; -moz-appearance:textfield; }
.tio-timer .pp { width:24px; height:28px; border:0; background:transparent; cursor:pointer;
  position:relative; color:#0f172a; display:grid; place-items:center; margin:0 6px; }
.tio-timer .pp svg { width:14px; height:14px; display:block; }
.tio-savetoggle { display:flex; height:40px; align-items:center; gap:8px; }
.tio-savetoggle .lab { border:0; background:transparent; padding:0; box-shadow:none; cursor:pointer;
    line-height:1; font-size:.62rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#64748b; }
.tio-savetoggle .lab.on { color:#0f172a; }
.tio-savetoggle .sw { position:relative; width:44px; height:20px; border-radius:999px;
    border:1px solid #000; background:#fff; cursor:pointer; padding:0; }
.tio-savetoggle .sw .k { position:absolute; top:2px; left:2px; width:14px; height:14px;
    border-radius:999px; background:#000; transition:left .15s ease; }
.tio-savetoggle .sw.on .k { left:26px; }

/* ── Student ── */
.tio-stud { display:flex; height:100%; width:100%; align-items:center; justify-content:center; padding:14px; position:relative; }
.tio-stud-chip { border:1px solid var(--line); background:#f8fafc; border-radius:14px;
    padding:12px 22px; font-size:.85rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#334155; }
.tio-stud-foot { display:flex; align-items:center; gap:8px; border:1px solid var(--line);
    background:#fff; border-radius:999px; padding:6px 12px; font-weight:600; color:#334155; max-width:100%; }
.tio-stud-foot .ico { display:grid; place-items:center; width:16px; height:16px; }
.tio-stud-foot .ico svg { width:16px; height:16px; display:block; }
.tio-stud-foot .lbl { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tio-stud-timer { display:flex; align-items:center; gap:8px; border:1px solid var(--line);
    background:#fff; border-radius:999px; padding:6px 12px; color:#1e293b; }
.tio-stud-timer .ic { display:grid; place-items:center; width:16px; height:16px; }
.tio-stud-timer .ic svg { width:16px; height:16px; display:block; }
.tio-stud-timer .t { font-size:.95rem; font-weight:900; font-variant-numeric:tabular-nums; }

/* Warte-Minispiel */
.tio-game { width:100%; max-width:720px; }
.tio-game-sky { position:relative; width:100%; aspect-ratio:16/6; border-radius:16px; overflow:hidden;
    background:linear-gradient(180deg,#0f172a 0%,#1e293b 70%,#334155 100%); border:1px solid #0f172a; }
.tio-star { position:absolute; width:2px; height:2px; background:#e2e8f0; border-radius:999px; opacity:.8; }
.tio-ground { position:absolute; left:0; right:0; bottom:0; height:20%; background:#0b1220;
    border-top:2px solid #334155; }
.tio-ham { position:absolute; }
.tio-px { position:absolute; }
.tio-wheel { position:absolute; border:3px solid #64748b; border-radius:999px; }
.tio-wheel span { position:absolute; left:50%; top:50%; width:2px; height:50%; background:#475569;
    transform-origin:top center; }
.tio-obst { position:absolute; background:#94a3b8; border-radius:2px; }
.tio-game-hud { display:flex; justify-content:space-between; margin-top:8px; font-size:.75rem;
    font-weight:700; color:#475569; }
.tio-game-over { position:absolute; inset:0; display:flex; flex-direction:column; gap:8px;
    align-items:center; justify-content:center; background:rgba(15,23,42,.72); color:#fff; }
.tio-game-over button { border:0; border-radius:10px; background:#fff; color:#0f172a;
    padding:8px 18px; font-weight:800; cursor:pointer; }
</style>

<div class="tio-root mode-<?php echo $view === 'sus' ? 'student' : ($view === 'lk_on' ? 'create' : 'live'); ?>">
  <div class="tio-wrap">
    <div class="tio-card">
      <div class="tio-aspect">
        <div class="tio-frame">
          <div class="tio-stage" id="tio-stage"></div>
          <div class="tio-foot" id="tio-foot" style="display:none;">
            <div class="tio-foot-in" id="tio-foot-in"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DEBUG: JSON-Vorschau des aktuellen Toolio-State – wird vor Release entfernt -->
<div id="tio-debug-panel" style="margin:8px 0;font-family:monospace;">
  <details>
    <summary style="cursor:pointer;font-size:.72rem;color:#94a3b8;padding:4px 8px;user-select:none;background:#0f172a;border-radius:4px;display:inline-block;">
      🔍 DEBUG · State-JSON
    </summary>
    <pre id="tio-debug-json" style="font-size:.68rem;background:#0f172a;color:#7dd3fc;border-radius:0 4px 4px 4px;padding:10px;overflow-x:auto;max-height:260px;overflow-y:auto;margin:0;white-space:pre;"></pre>
  </details>
</div>

<script>
(function () {
  "use strict";
  var BOOT = <?php echo json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  var stageEl = document.getElementById('tio-stage');
  var footEl = document.getElementById('tio-foot');
  var footInEl = document.getElementById('tio-foot-in');

  /* ── Datenmodell (aus dem Prototyp) ── */
  var METHODS = [
    { id:"placemat", label:"Placemat", summary:"Perspektiven sammeln und in vier Feldern strukturieren.", tools:["groups","board"], size:"lg" },
    { id:"thinkPairShare", label:"Think-Pair-Share", summary:"Erst einzeln denken, dann paarweise, dann im Plenum.", tools:["groups","board"], size:"md" },
    { id:"jigsaw", label:"Gruppenpuzzle", summary:"Expertengruppen bilden und Wissen zusammenführen.", tools:["groups","board"], size:"md" },
    { id:"gallerywalk", label:"Gallery Walk", summary:"Gruppenergebnisse sichten und rückmelden.", tools:["groups","board"], size:"md" },
    { id:"rollenspiel", label:"Rollenspiel", summary:"Positionen einnehmen und argumentativ prüfen.", tools:["chat","groups"], size:"lg" },
    { id:"fallanalyse", label:"Fallanalyse", summary:"Konkreten Fall schrittweise analysieren und bewerten.", tools:["chat","board"], size:"md" },
    { id:"brainstorm", label:"Brainstorming", summary:"Ideen schnell sammeln und clustern.", tools:["board"], size:"sm" },
    { id:"mindmap", label:"Mindmap", summary:"Begriffe hierarchisch strukturieren.", tools:["board"], size:"sm" },
    { id:"debatte", label:"Debatte", summary:"Pro und Contra gegeneinander abwägen.", tools:["board","poll"], size:"sm" },
    { id:"blitzlicht", label:"Blitzlicht", summary:"Schnelle Momentaufnahme der Klasse.", tools:["poll"], size:"sm" },
    { id:"peerFeedback", label:"Peer-Feedback", summary:"Rückmeldungen zwischen Gruppen nach Kriterien.", tools:["groups","board"], size:"md" }
  ];
  var SOCIALS = [
    { id:"social-individual", label:"Einzelarbeit", social:"individual", size:"sm" },
    { id:"social-partner", label:"Partnerarbeit", social:"partner", size:"sm" },
    { id:"social-group", label:"Gruppenarbeit", social:"group", size:"sm" }
  ];
  var TOOLNODES = [
    { id:"tool-board", label:"Board", toolKey:"board", size:"sm" },
    { id:"tool-chat", label:"KI-Chatbot", toolKey:"chat", size:"sm" },
    { id:"tool-poll", label:"Abfrage", toolKey:"poll", size:"sm" }
  ];
  var SOCIAL_BY_METHOD = {
    placemat:"group", thinkPairShare:"partner", jigsaw:"group", gallerywalk:"group",
    rollenspiel:"group", fallanalyse:"group", brainstorm:"group", mindmap:"group",
    debatte:"group", blitzlicht:"individual", peerFeedback:"group"
  };
  var TOOL_PITCH = {
    chat: { label:"KI-Chatbot", pitch:"Dialogische Unterstützung mit Rollenperspektive.",
      keys:["Persona statt Suchmaschine","Antworten nur mit LK-Material","Adaptive Nachfragen","Individuelle Lernwege","Live-Einblick für Lehrkraft"],
      res:"Klarere Fragen und bessere Vorarbeit" },
    board: { label:"Board", pitch:"Gemeinsamer Arbeitsraum in Echtzeit.",
      keys:["Alle arbeiten gleichzeitig","Gruppenräume plus Tafelbild","Visualisieren statt nur Text","Zwischenschritte bleiben sichtbar","Ergebnisse direkt sichern"],
      res:"Sichtbares, gemeinsames Produkt" },
    poll: { label:"Abfrage", pitch:"Sofortdiagnose für Lernstand und Richtung.",
      keys:["Live-Übersicht in Sekunden","Anonym oder offen","Verschiedene Fragetypen","Checkpoint spontan einschiebbar","Entscheidungshilfe für den nächsten Schritt"],
      res:"Schnelle, belastbare Unterrichtsentscheidung" }
  };
  var DEBUG_FILES = ["Beispielauftrag.pdf","Kriterienraster.docx","Datensatz_Umsatz.csv"];

  var NODES = [];
  METHODS.forEach(function (m) { NODES.push({ id:m.id, label:m.label, summary:m.summary, tools:m.tools, kind:"method", size:m.size, depth:1, opacity:.9 }); });
  SOCIALS.forEach(function (s) { NODES.push({ id:s.id, label:s.label, social:s.social, kind:"social", size:s.size, depth:.75, opacity:.76 }); });
  TOOLNODES.forEach(function (t) { NODES.push({ id:t.id, label:t.label, toolKey:t.toolKey, kind:"tool", size:t.size, depth:.62, opacity:.78 }); });

  var EDGES = [];
  METHODS.forEach(function (m) {
    m.tools.forEach(function (t) { EDGES.push([m.id, "tool-" + t]); });
    if (m.id === "thinkPairShare") EDGES.push([m.id, "social-partner"]);
    if (m.id === "jigsaw" || m.id === "placemat" || m.id === "gallerywalk" || m.id === "peerFeedback") EDGES.push([m.id, "social-group"]);
    if (m.id === "blitzlicht") EDGES.push([m.id, "social-individual"]);
    if (m.id === "debatte" || m.id === "rollenspiel") EDGES.push([m.id, "social-group"]);
  });

  /* ── State ── */
  var st = {
    materials: [],
    preparing: null,   // {id,label,summary,kind}
    prepStep: 1,
    prepTool: "none",  // none|chat|board|poll
    prepSocial: "individual",
    hovered: null,
    graphTime: 0,
    orbit: { x:0, y:0 },
    orbitTarget: { x:0, y:0 },
    // live/student
    frozen: false,
    timerSeconds: 0,
    timerRunning: false,
    focusedGroup: null,
    dockCollapsed: false,
    activityReady: !!BOOT.state
  };

  var GROUPS = (BOOT.groups || []).map(function (g) {
    return { id:g.id, name:g.name, students:(g.students || []).slice(), studentids:(g.studentids || []).slice() };
  });
  var PARTICIPANTS = (BOOT.participants || []).map(function (p) {
    return { id:(typeof p.id !== "undefined" ? p.id : null), name:p.name, group:p.group || "—" };
  });
  if (BOOT.state && BOOT.state.timer) {
    st.timerSeconds = BOOT.state.timer.seconds | 0;
    st.timerRunning = !!BOOT.state.timer.running;
  }
  if (BOOT.state && typeof BOOT.state.frozen !== "undefined") {
    st.frozen = !!BOOT.state.frozen;
  }

  /* ══════════════════════════════════════════════════════════
     Gruppentool-Whiteboard (Schritt 3, 🟢 LK ON)
     Board-Modell: Teilnehmende werden per Drag & Drop Gruppen zugeteilt
     oder frei auf dem Canvas platziert. Modus Gruppen/Partner, Würfeln,
     Anzahl ±, Umbenennen, Zusammenführen, Abwesend, Zurücksetzen.
     Der komplette Board-Zustand (inkl. Positionen x/y) wird als JSON im
     bestehenden versionierten State persistiert (keine neuen Tabellen) und
     per SSE live an LK OFF / Schüleransicht gepusht.
     ══════════════════════════════════════════════════════════ */
  var tioDrag = null;       // participantId beim Ziehen einer Person
  var tioDragGroup = null;  // groupId beim Ziehen einer Gruppe (Merge)

  function tioAllStudents() {
    if (PARTICIPANTS.length) {
      return PARTICIPANTS.map(function (p) { return { id:p.id, name:p.name }; });
    }
    var out = [];
    GROUPS.forEach(function (g) {
      g.students.forEach(function (nm, i) { out.push({ id:(g.studentids || [])[i], name:nm }); });
    });
    return out;
  }

  function initBoard() {
    var b = (BOOT.state && BOOT.state.board) || null;
    if (b && b.participants && b.participants.length) {
      return {
        mode: (b.mode === "partner") ? "partner" : "groups",
        groupCount: Math.max(0, Math.min(50, b.groupCount | 0)),
        labels: (b.labels && typeof b.labels === "object") ? b.labels : {},
        anchors: (b.anchors && typeof b.anchors === "object") ? b.anchors : {},
        parts: b.participants.map(function (p) {
          return { id:p.id, name:p.name, active:(p.active !== false),
            groupId:(p.groupId || null),
            x:(p.x == null ? null : +p.x), y:(p.y == null ? null : +p.y) };
        })
      };
    }
    return {
      mode: "groups", groupCount: 0, labels: {}, anchors: {},
      parts: tioAllStudents().map(function (s) {
        return { id:s.id, name:s.name, active:true, groupId:null, x:null, y:null };
      })
    };
  }
  var BOARD = initBoard();

  function boardGroups() {
    var gs = [], map = {};
    for (var i = 1; i <= BOARD.groupCount; i++) {
      var id = "group-" + i;
      var label = BOARD.labels[id] || (BOARD.mode === "partner" ? ("Paar " + i) : ("Gruppe " + i));
      var g = { id:id, label:label, members:[] };
      gs.push(g); map[id] = g;
    }
    BOARD.parts.forEach(function (p) {
      if (p.active && p.groupId && map[p.groupId]) { map[p.groupId].members.push(p); }
    });
    return gs;
  }
  function findPart(id) {
    for (var i = 0; i < BOARD.parts.length; i++) {
      if (String(BOARD.parts[i].id) === String(id)) { return BOARD.parts[i]; }
    }
    return null;
  }
  function ensureCountFor(gid) {
    var m = /^group-(\d+)$/.exec(gid || "");
    if (m) { var n = +m[1]; if (n > BOARD.groupCount) { BOARD.groupCount = Math.min(50, n); } }
  }
  function assignPart(id, gid) { var p = findPart(id); if (!p) return; ensureCountFor(gid); p.groupId = gid; p.x = null; p.y = null; refreshBoard(); }
  function unassignPart(id) { var p = findPart(id); if (!p) return; p.groupId = null; p.x = null; p.y = null; refreshBoard(); }
  function placePart(id, x, y) { var p = findPart(id); if (!p) return; p.groupId = null; p.x = x; p.y = y; refreshBoard(); }
  function toggleActive(id) { var p = findPart(id); if (!p) return; p.active = !p.active; if (!p.active) { p.groupId = null; p.x = null; p.y = null; } refreshBoard(); }
  function setGroupCount(n) { BOARD.groupCount = Math.max(0, Math.min(50, n | 0)); refreshBoard(); }
  function renameGroup(gid, label) { label = String(label || "").slice(0, 80).trim(); if (label) { BOARD.labels[gid] = label; } else { delete BOARD.labels[gid]; } refreshBoard(); }
  function setBoardMode(m) { BOARD.mode = (m === "partner") ? "partner" : "groups"; refreshBoard(); }
  function resetBoard() { BOARD.parts.forEach(function (p) { p.groupId = null; p.x = null; p.y = null; }); refreshBoard(); }
  function mergeGroups(src, tgt) {
    if (!src || !tgt || src === tgt) { return; }
    BOARD.parts.forEach(function (p) { if (p.groupId === src) { p.groupId = tgt; p.x = null; p.y = null; } });
    refreshBoard();
  }
  function autoAssign() {
    var active = BOARD.parts.filter(function (p) { return p.active; });
    for (var i = active.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1)), t = active[i]; active[i] = active[j]; active[j] = t;
    }
    if (BOARD.mode === "partner") { BOARD.groupCount = active.length > 1 ? Math.floor(active.length / 2) : 0; }
    else if (BOARD.groupCount === 0) { BOARD.groupCount = Math.max(1, Math.min(8, Math.round(active.length / 3) || 1)); }
    var gc = BOARD.groupCount;
    BOARD.parts.forEach(function (p) { p.x = null; p.y = null; if (!p.active) { p.groupId = null; } });
    active.forEach(function (p, idx) { p.groupId = gc > 0 ? ("group-" + ((idx % gc) + 1)) : null; });
    refreshBoard();
  }

  /* Board → GROUPS (für LK OFF / Schüleransicht) + Sozialform spiegeln */
  function syncGroupsFromBoard() {
    GROUPS = boardGroups().map(function (g) {
      return { id:g.id, name:g.label,
        students:g.members.map(function (m) { return m.name; }),
        studentids:g.members.map(function (m) { return m.id; }) };
    });
    st.prepSocial = (BOARD.mode === "partner") ? "partner" : "group";
  }
  function serializeBoard() {
    return { mode:BOARD.mode, groupCount:BOARD.groupCount, labels:BOARD.labels, anchors:BOARD.anchors,
      participants:BOARD.parts.map(function (p) {
        return { id:p.id, name:p.name, active:p.active, groupId:p.groupId, x:p.x, y:p.y };
      }) };
  }
  function boardPayload() {
    var toolMap = { none:"none", board:"board", chat:"chatbot", poll:"abfrage" };
    var socMap = { individual:"einzel", partner:"paar", group:"gruppe" };
    return {
      methodid: (st.preparing && st.preparing.id) || "no-template",
      methodlabel: (st.preparing && st.preparing.label) || "Ohne Vorlage",
      methodsummary: (st.preparing && st.preparing.summary) || "",
      materials: st.materials.slice(),
      tool: toolMap[st.prepTool] || "none",
      sozialform: socMap[st.prepSocial] || "gruppe",
      count: GROUPS.length,
      groups: GROUPS.map(function (g) { return { name:g.name, members:g.students.slice(), memberids:(g.studentids || []).slice() }; }),
      board: serializeBoard()
    };
  }

  /* Debounced Autosave — jede Board-Änderung landet live (SSE) bei LK OFF/SuS. */
  var boardSaveTimer = null;
  function scheduleBoardSave() {
    if (BOOT.view !== "lk_on") { return; }
    if (boardSaveTimer) { clearTimeout(boardSaveTimer); }
    boardSaveTimer = setTimeout(pushBoard, 500);
  }
  function pushBoard() {
    var body = new URLSearchParams();
    body.set("id", BOOT.cmid); body.set("tool", "gruppen");
    body.set("sesskey", BOOT.sesskey); body.set("payload", JSON.stringify(boardPayload()));
    fetch(BOOT.saveurl, { method:"POST", headers:{ "Content-Type":"application/x-www-form-urlencoded" }, body:body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (res) { if (res && res.ok) { st.activityReady = true; BOOT.state = res.state; } })
      .catch(function () {});
  }
  function refreshBoard() { syncGroupsFromBoard(); scheduleBoardSave(); render(); renderDebugJson(); }

  /* ── Board-Rendering ── */
  function gtInitials(name) {
    var s = String(name || "").trim(); if (!s) { return "?"; }
    var w = s.split(/\s+/).filter(Boolean);
    return (w.length === 1 ? w[0].slice(0, 1) : (w[0].slice(0, 1) + w[1].slice(0, 1))).toUpperCase();
  }
  function gtColor(id) {
    var s = String(id), h = 0;
    for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) >>> 0; }
    return "hsl(" + (h % 360) + "," + (60 + (h >>> 8) % 20) + "%," + (46 + (h >>> 16) % 8) + "%)";
  }
  var GT_AV = '<svg class="tio-gt-av" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-4.42 0-8 2.02-8 4.5V21h16v-2.5c0-2.48-3.58-4.5-8-4.5Z" fill="currentColor"/></svg>';
  var GT_EYE = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7-10-7-10-7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
  var GT_EYEOFF = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 5c4.8 0 8.6 3 10 7-.53 1.51-1.45 2.87-2.66 3.98M9.88 7.1c.69-.2 1.4-.3 2.12-.3 4.8 0 8.6 3 10 7-1.4 4-5.2 7-10 7-4.8 0-8.6-3-10-7 .56-1.6 1.55-3.03 2.86-4.16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  // Teilnehmer-Listeneintrag: Personen-Avatar + Name + Auge (Anwesenheit)
  function tioChipEl(p) {
    var inactive = !p.active;
    var row = el('<div class="tio-gt-li' + (inactive ? " off" : "") + '"' + (inactive ? "" : ' draggable="true"') +
      ' title="' + (inactive ? "Abwesend" : "Ziehen zum Zuteilen") + '"></div>');
    row.innerHTML = GT_AV + '<span class="nm">' + esc(p.name) + '</span>';
    var eye = el('<button type="button" class="eye" title="' +
      (inactive ? "Als anwesend markieren" : "Als abwesend markieren") + '">' + (inactive ? GT_EYEOFF : GT_EYE) + '</button>');
    eye.addEventListener("click", function (e) { e.stopPropagation(); toggleActive(p.id); });
    row.appendChild(eye);
    if (!inactive) { row.addEventListener("dragstart", function () { tioDrag = p.id; tioDragGroup = null; }); }
    return row;
  }
  /* ── Blumen-Layout: zentraler Gruppen-Kern + Mitglieder auf einem Ring + Konnektoren ── */
  var GT_CORE_R = 30, GT_MEMBER_SPACING = 74;
  function gtRingRadius(n, maxR) {
    if (n <= 1) { return Math.min(48, maxR); }
    var r = GT_MEMBER_SPACING / (2 * Math.sin(Math.PI / n));
    return Math.max(46, Math.min(maxR, r));
  }
  function gtMemberAngle(i, n) {
    if (n === 2) { return i === 0 ? Math.PI : 0; }        // Paar: horizontal
    return (-Math.PI / 2) + (i / n) * Math.PI * 2;         // Ring ab oben
  }
  function gtGroupCenters(groups, W, H) {
    var K = groups.length;
    var cols = Math.ceil(Math.sqrt(K)), rows = Math.ceil(K / cols);
    return groups.map(function (g, k) {
      var a = BOARD.anchors[g.id];
      if (a && isFinite(a.x) && isFinite(a.y)) { return { g:g, x:a.x * W, y:a.y * H }; }
      var col = k % cols, row = Math.floor(k / cols);
      return { g:g, x:(W / cols) * (col + 0.5), y:(H / rows) * (row + 0.5) };
    });
  }
  function tioSetAnchor(gid, x, y) {
    BOARD.anchors[gid] = { x:Math.max(0.06, Math.min(0.94, x)), y:Math.max(0.08, Math.min(0.92, y)) };
    refreshBoard();
  }
  function tioMemberEl(p) {
    var c = el('<div class="tio-gt-member" draggable="true" title="' + esc(p.name) + '"></div>');
    var dot = el('<div class="dot">' + esc(gtInitials(p.name)) + '</div>');
    dot.style.background = gtColor(p.id);
    c.appendChild(dot);
    c.appendChild(el('<div class="lab">' + esc(p.name) + '</div>'));
    c.addEventListener("dragstart", function () { tioDrag = p.id; tioDragGroup = null; });
    return c;
  }
  function tioCoreEl(g) {
    var core = el('<div class="tio-gt-core" draggable="true" title="Ziehen: verschieben · auf andere Gruppe ziehen: zusammenführen"></div>');
    var input = el('<input class="tio-gt-core-name" value="' + esc(g.label) + '">');
    input.setAttribute("draggable", "false");
    input.addEventListener("change", function () { renameGroup(g.id, input.value); });
    input.addEventListener("keydown", function (e) { if (e.key === "Enter") { input.blur(); } });
    input.addEventListener("mousedown", function (e) { e.stopPropagation(); });
    core.appendChild(input);
    core.appendChild(el('<span class="cnt">' + g.members.length + '</span>'));
    core.addEventListener("dragstart", function () { tioDragGroup = g.id; tioDrag = null; });
    core.addEventListener("dragover", function (e) { e.preventDefault(); core.classList.add("drag"); });
    core.addEventListener("dragleave", function () { core.classList.remove("drag"); });
    core.addEventListener("drop", function (e) {
      e.preventDefault(); e.stopPropagation(); core.classList.remove("drag");
      if (tioDragGroup != null && tioDragGroup !== g.id) { mergeGroups(tioDragGroup, g.id); tioDragGroup = null; return; }
      if (tioDrag != null) { assignPart(tioDrag, g.id); tioDrag = null; }
    });
    return core;
  }
  var SVGNS = "http://www.w3.org/2000/svg";
  function layoutFlower(canvas) {
    if (!canvas || !canvas.isConnected) { return; }
    var W = canvas.clientWidth, H = canvas.clientHeight;
    if (W < 40 || H < 40) { return; }
    var old = canvas.querySelectorAll(".tio-gt-core, .tio-gt-member, .tio-gt-fsvg");
    for (var i = 0; i < old.length; i++) { old[i].remove(); }
    var groups = boardGroups();
    if (!groups.length) { return; }
    var svg = document.createElementNS(SVGNS, "svg");
    svg.setAttribute("class", "tio-gt-fsvg");
    svg.setAttribute("width", W); svg.setAttribute("height", H);
    canvas.appendChild(svg);
    var maxR = Math.min(W, H) * 0.18;
    gtGroupCenters(groups, W, H).forEach(function (gc) {
      var g = gc.g, n = g.members.length, ringR = gtRingRadius(n, maxR);
      g.members.forEach(function (p, idx) {
        var ang = gtMemberAngle(idx, n);
        var mx = gc.x + Math.cos(ang) * ringR, my = gc.y + Math.sin(ang) * ringR;
        var line = document.createElementNS(SVGNS, "line");
        line.setAttribute("x1", gc.x + Math.cos(ang) * (GT_CORE_R + 6));
        line.setAttribute("y1", gc.y + Math.sin(ang) * (GT_CORE_R + 6));
        line.setAttribute("x2", gc.x + Math.cos(ang) * (ringR - 18));
        line.setAttribute("y2", gc.y + Math.sin(ang) * (ringR - 18));
        line.setAttribute("class", "tio-gt-conn");
        svg.appendChild(line);
        var chip = tioMemberEl(p);
        chip.style.left = mx + "px"; chip.style.top = my + "px";
        chip.style.setProperty("--dx", (gc.x - mx) + "px");   // aus dem Kern "aufbluehen"
        chip.style.setProperty("--dy", (gc.y - my) + "px");
        chip.style.animationDelay = (idx * 45) + "ms";
        canvas.appendChild(chip);
      });
      var core = tioCoreEl(g);
      core.style.left = gc.x + "px"; core.style.top = gc.y + "px";
      canvas.appendChild(core);
    });
  }
  if (!window.__tioFlowerResize) {
    window.__tioFlowerResize = true;
    window.addEventListener("resize", function () {
      var cv = document.querySelector(".tio-gt-canvas");
      if (cv) { layoutFlower(cv); }
    });
  }
  // ── Gruppentool: 1:1-Host der Original-Engine (public/teacher.js) ──
  // Die Engine ist ein eigenstaendiges Skript, das window.io() (Adapter) nutzt und an
  // feste DOM-IDs bindet. Wir bauen die exakte Teacher-Shell EINMAL, cachen den Knoten
  // (damit die Bindings von teacher.js ueber SPA-Rerenders erhalten bleiben) und laden
  // Adapter + teacher.js genau einmal.
  var TIO_GT_SHELL = ''
    + '<div class="teacher-shell">'
    +   '<header class="topbar">'
    +     '<div class="topbar-left participants-header-control">'
    +       '<button id="participantsToggleButton" class="button button-secondary participants-header-button" type="button" aria-label="Teilnehmende anzeigen" aria-expanded="true" aria-controls="participantsPanel">'
    +         '<svg class="panel-toggle-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3Zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.95 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5Z" fill="currentColor"/></svg>'
    +         '<span id="participantsHeaderLabel" class="participants-header-label">Teilnehmende 0/50</span>'
    +       '</button>'
    +     '</div>'
    +     '<div class="topbar-center" hidden><p id="sessionInfo" class="session-pill"></p></div>'
    +   '</header>'
    +   '<p id="errorMessage" class="error-text" hidden></p>'
    +   '<main class="whiteboard-main" aria-label="Gruppen Whiteboard"><section class="whiteboard-section" aria-label="Gruppenansicht"><div id="whiteboardCanvas" class="whiteboard-canvas"><div id="whiteboardGroupLayer" class="whiteboard-group-layer"></div><div id="whiteboardConnectorLayer" class="whiteboard-connector-layer"></div><div id="whiteboardLooseLayer" class="whiteboard-loose-layer"></div><div id="whiteboardEmptyState" class="whiteboard-empty-state">Noch keine Teilnehmenden im Kurs.</div>'
    +     '<div class="gt-canvas-controls" aria-label="Gruppensteuerung">'
    +       '<button id="groupModeSwitchButton" class="mode-switch-button mode-switch-button-inline" type="button" role="switch" aria-checked="false" aria-label="Zwischen Partner und Gruppen wechseln"><span id="groupModeSwitchPairs" class="mode-switch-option">Partner</span><span class="mode-switch-track" aria-hidden="true"><span class="mode-switch-thumb"></span></span><span id="groupModeSwitchGroups" class="mode-switch-option">Gruppen</span></button>'
    +       '<div class="group-count-pill"><button id="groupMinusButton" class="group-control-btn" type="button" aria-label="Weniger Gruppen">-</button><button id="groupCountButton" class="group-count-btn" type="button" disabled>0 Gruppen</button><button id="groupPlusButton" class="group-control-btn" type="button" aria-label="Mehr Gruppen">+</button></div>'
    +       '<button id="autoAssignButton" class="auto-assign-dice-button gt-dice-fab" type="button" aria-label="Zufaellig zuordnen"><svg class="button-icon-svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="9" width="10" height="10" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.7" /><rect x="11" y="3" width="10" height="10" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.7" /><circle cx="6.6" cy="12.6" r="1" fill="currentColor" /><circle cx="9.4" cy="15.4" r="1" fill="currentColor" /><circle cx="14.6" cy="6.6" r="1" fill="currentColor" /><circle cx="17.4" cy="9.4" r="1" fill="currentColor" /></svg></button>'
    +     '</div>'
    +   '</div></section></main>'
    +   '<aside id="participantsDock" class="participants-dock" aria-label="Teilnehmende Bereich"><aside id="participantsPanel" class="side-panel participants-panel is-open" aria-label="Teilnehmende"><div class="participants-list-wrap"><ul id="participantsList" class="participants-list" aria-live="polite"></ul></div><div class="participants-panel-footer"></div></aside></aside>'
    + '</div>';

  var tioGtNode = null;

  // Student-Shell (Schueler-Ansicht) — DOM-IDs, an die public/student.js bindet.
  var TIO_GT_STUDENT_SHELL = ''
    + '<div class="student-shell">'
    +   '<header class="topbar student-topbar"><div class="topbar-left"></div><div class="topbar-center" hidden><p id="sessionInfo" class="session-pill"></p></div></header>'
    +   '<main class="student-main"><section class="student-card"><section id="studentCardSurface" class="card stack student-card-surface"><p id="errorMessage" class="error-text" hidden></p><section id="studentGroupSection" class="student-group-box" aria-label="Gruppe" hidden><p id="studentGroupState" class="student-group-value"></p><ul id="studentGroupMembers" class="student-group-members-list" aria-live="polite"></ul></section></section></section></main>'
    + '</div>';

  function tioLoadGtEngine() {
    if (window.__tioGtEngineLoaded) { return; }
    window.__tioGtEngineLoaded = true;
    var gt = (BOOT.gt || {});
    var base = gt.assetbase || '';
    var rev = gt.rev ? ('?v=' + gt.rev) : '';
    window.GM_MOODLE = gt.gm || {};
    // Original-Stylesheet (unter .gruppentool-app gescoped), danach das helle Toolio-Reskin.
    if (!document.getElementById('tio-gt-style')) {
      var link = document.createElement('link');
      link.id = 'tio-gt-style'; link.rel = 'stylesheet'; link.href = base + 'style.css' + rev;
      document.head.appendChild(link);
      var theme = document.createElement('link');
      theme.id = 'tio-gt-theme'; theme.rel = 'stylesheet'; theme.href = base + 'theme-light.css' + rev;
      document.head.appendChild(theme);
    }
    // Adapter zuerst (definiert window.io), dann die Engine.
    var adapter = document.createElement('script');
    adapter.src = base + 'moodle-socket-adapter.js' + rev;
    adapter.onload = function () {
      var engine = document.createElement('script');
      engine.src = base + (window.GM_MOODLE.isstudentview ? 'student.js' : 'teacher.js') + rev;
      document.body.appendChild(engine);
    };
    document.body.appendChild(adapter);
  }

  function buildGruppentool() {
    if (!tioGtNode) {
      var isstudent = !!((BOOT.gt && BOOT.gt.gm && BOOT.gt.gm.isstudentview));
      tioGtNode = el('<div class="gruppentool-app"></div>');
      tioGtNode.innerHTML = isstudent ? TIO_GT_STUDENT_SHELL : TIO_GT_SHELL;
      setTimeout(tioLoadGtEngine, 0);
    }
    return tioGtNode;
  }

  function iconSvg(name) {
    if (name === "trash") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><line x1="10" y1="11" x2="10" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line><line x1="14" y1="11" x2="14" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line></svg>';
    }
    if (name === "close") {
      return '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path><path d="M12 4L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>';
    }
    if (name === "clock") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"></circle><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    }
    if (name === "play") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 5l11 7-11 7V5z" fill="currentColor"></path></svg>';
    }
    if (name === "pause") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 5h4v14H7zM13 5h4v14h-4z" fill="currentColor"></path></svg>';
    }
    if (name === "single") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="1.8"></circle><path d="M6.5 18c0-3 2.6-4.8 5.5-4.8s5.5 1.8 5.5 4.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
    }
    if (name === "pair") {
      return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="2.5" stroke="currentColor" stroke-width="1.8"></circle><circle cx="15.2" cy="8.6" r="2.3" stroke="currentColor" stroke-width="1.6"></circle><path d="M4.5 18c0-2.8 2.3-4.4 4.9-4.4 1 0 2 .2 2.8.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><path d="M11.8 14.4c.8-.5 1.7-.7 2.6-.7 2.4 0 4.6 1.4 4.6 4.1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path></svg>';
    }
    return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="7.6" r="2.5" stroke="currentColor" stroke-width="1.8"></circle><circle cx="6.9" cy="9" r="2.1" stroke="currentColor" stroke-width="1.5"></circle><circle cx="17.1" cy="9" r="2.1" stroke="currentColor" stroke-width="1.5"></circle><path d="M8 18c0-2.7 1.9-4.3 4-4.3s4 1.6 4 4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><path d="M3.8 17.8c0-2.2 1.5-3.4 3.1-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path><path d="M20.2 17.8c0-2.2-1.5-3.4-3.1-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>';
  }

  function socKey(mode) {
    return { einzel:"individual", paar:"partner", gruppe:"group", plenum:"group",
      individual:"individual", partner:"partner", group:"group" }[mode] || "group";
  }
  function socialIcon(mode) {
    var k = socKey(mode);
    return iconSvg(k === "individual" ? "single" : (k === "partner" ? "pair" : "group"));
  }

  var stateSource = null;
  var _rtVersion = null;   // zuletzt angewandte Realtime-Version (Store+Engine kombiniert)
  var _pollTimer = null;
  function applyRemoteState(payload) {
    if (!payload) { return; }
    BOOT.state = payload;
    st.activityReady = !!payload;
    if (payload.timer && BOOT.view === "sus") {
      st.timerSeconds = payload.timer.seconds | 0;
      st.timerRunning = !!payload.timer.running;
    }
    // Lehrergesteuerter Lock (Bearbeiten/Sichern-Switch): SuS uebernehmen den Stand,
    // damit ihr Board bei "Sichern" read-only wird (und sie selbst nicht raus koennen).
    if (BOOT.view === "sus") {
      st.frozen = !!payload.frozen;
    }
    if (BOOT.view === "lk_on") {
      var toolBack = { none:"none", board:"board", chatbot:"chat", abfrage:"poll", gruppen:"none" };
      var socBack = { einzel:"individual", paar:"partner", gruppe:"group", plenum:"group" };
      st.materials = (payload.materials && payload.materials.slice()) || [];
      st.preparing = { id:payload.methodid || "no-template", label:payload.methodlabel || "Ohne Vorlage", summary:payload.methodsummary || "", kind:"method" };
      st.prepTool = toolBack[payload.tool] || "none";
      st.prepSocial = socBack[payload.sozialform] || "individual";
      st.prepStep = 3;
    }
    render();
  }

  function connectRealtime() {
    // Realtime laeuft ausschliesslich ueber Polling (poll.php). SSE (sse.php) wird auf
    // diesem Server (Apache + php-fpm hinter Proxy) gepuffert -> Updates kaemen erst beim
    // Reload an; zudem liefert der Host teils 404 auf den Stream. Kein EventSource mehr.
    startPolling();
  }

  function startPolling() {
    if (_pollTimer || !BOOT.pollurl) { return; }
    // LK ON ist der Produzent (editiert das Board live) — kein Re-Render von aussen,
    // damit die Interaktion nicht unterbrochen wird. Nur LK OFF / Schueler pollen.
    if (BOOT.view === "lk_on") { return; }
    _pollTimer = setInterval(function () {
      fetch(BOOT.pollurl, { credentials: "same-origin", cache: "no-store" })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data || typeof data.state === "undefined") { return; }
          if (_rtVersion !== null && data.version === _rtVersion) { return; }
          _rtVersion = data.version;
          applyRemoteState(data.state);
        })
        .catch(function () {});
    }, 2000);
  }

  /* ── Helpers ── */
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
    return { "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" }[c]; }); }
  function el(html) { var d = document.createElement("div"); d.innerHTML = html.trim(); return d.firstChild; }
  function fmt(sec) { var m = Math.floor(sec / 60), s = sec % 60;
    return (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s; }
  function pickTool(tools) {
    if (tools.indexOf("poll") >= 0) return "poll";
    if (tools.indexOf("chat") >= 0) return "chat";
    if (tools.indexOf("board") >= 0) return "board";
    return "none";
  }
  function toolPlaceholder() {
    var t = (BOOT.state && BOOT.state.tool) || "none";
    if (t === "board") return "Board";
    if (t === "abfrage") return "Abfrage";
    if (t === "chatbot") return "KI";
    if (t === "gruppen") return "Gruppentool";
    return "Kein Tool";
  }
  function boardRooms() {
    var rs = (BOOT.state && BOOT.state.boardrooms) || [];
    return Array.isArray(rs) ? rs : [];
  }
  function boardLaunchUrl(room, mode, locked) {
    var base = (BOOT.board && BOOT.board.baseurl) || "";
    if (!base || !room || !room.roomid || !room.roomkey) { return ""; }
    var me = BOOT.me || "";
    var q = "?username=" + encodeURIComponent(me);
    if (mode) { q += "&toolio=" + encodeURIComponent(mode); }
    if (locked) { q += "&lock=1"; }
    return base.replace(/\/+$/, "") + "/app" + q
      + "#room=" + encodeURIComponent(room.roomid) + "," + encodeURIComponent(room.roomkey);
  }
  function roomForGroup(group, index) {
    var rooms = boardRooms();
    if (!rooms.length) { return null; }

    var gid = String((group && group.id) || "");
    var m = /^group-(\d+)$/.exec(gid);
    if (m) {
      var target = parseInt(m[1], 10);
      for (var i = 0; i < rooms.length; i++) {
        if ((rooms[i].groupid | 0) === target) { return rooms[i]; }
      }
    }

    if (group && Array.isArray(group.studentids) && group.studentids.length) {
      var ids = group.studentids.map(function (x) { return parseInt(x, 10); })
        .filter(function (x) { return !isNaN(x); })
        .sort(function (a, b) { return a - b; });
      for (var r = 0; r < rooms.length; r++) {
        var mids = Array.isArray(rooms[r].memberids)
          ? rooms[r].memberids.map(function (x) { return parseInt(x, 10); })
            .filter(function (x) { return !isNaN(x); })
            .sort(function (a, b) { return a - b; })
          : [];
        if (mids.length !== ids.length) { continue; }
        var equal = true;
        for (var j = 0; j < ids.length; j++) {
          if (ids[j] !== mids[j]) { equal = false; break; }
        }
        if (equal) { return rooms[r]; }
      }
    }

    return rooms[index] || null;
  }
  // Bettet das Board als iframe (Excalidraw-Frontend) in ein Host-Element der Toolio-Karte
  // ein. Kein window.open: das Board bleibt Teil der Toolio-Oberflaeche.
  // opts.closable=false blendet den Schliessen-Button aus, opts.bar=false die ganze Kopfleiste
  // (Schuelersicht), opts.mode setzt den Excalidraw-UI-Modus per ?toolio=... (clean/mini).
  function boardFrame(group, index, hostEl, opts) {
    opts = opts || {};
    var closable = opts.closable !== false;
    var showbar = opts.bar !== false;
    var url = boardLaunchUrl(roomForGroup(group, index), opts.mode, opts.locked);
    var title = previewTitle(group) || (group && group.name) || "Board";
    var frame = el('<div class="tio-boardframe"></div>');
    if (showbar) {
      var bar = el('<div class="tio-boardframe-bar"><span class="t"></span></div>');
      bar.querySelector(".t").textContent = title;
      if (closable) {
        var closeBtn = el('<button type="button" class="tio-boardframe-close" title="Schließen"></button>');
        closeBtn.innerHTML = iconSvg("close");
        closeBtn.addEventListener("click", function () {
          frame.remove();
          if (opts.onClose) { opts.onClose(); }
        });
        bar.appendChild(closeBtn);
      }
      frame.appendChild(bar);
    }
    if (url) {
      var ifr = document.createElement("iframe");
      ifr.setAttribute("allow", "clipboard-read; clipboard-write; fullscreen");
      ifr.setAttribute("title", title);
      ifr.src = url;
      frame.appendChild(ifr);
    } else {
      var miss = el('<div class="tio-board-missing"></div>');
      miss.textContent = (BOOT.board && BOOT.board.enabled)
        ? "Für diese Gruppe ist noch kein Board-Raum vorhanden. Bitte die Aktivität erneut speichern."
        : "Die Board-URL ist noch nicht konfiguriert (Website-Administration → Plugins → Aktivitäten → Toolio).";
      frame.appendChild(miss);
    }
    hostEl.appendChild(frame);
    return frame;
  }
  function openBoardForGroup(group, index, hostEl, opts) {
    if (!hostEl) { return false; }
    boardFrame(group, index, hostEl, opts);
    return true;
  }
  // Nicht-interaktive Live-Miniatur des Gruppen-Boards fuer die LK-OFF-Karten. Zeigt den
  // realen (per WebSocket synchronisierten) Board-Stand ohne Werkzeuge (?toolio=mini);
  // Klicks fangen wir ab, damit die Karte selbst das grosse Board oeffnet.
  function boardMini(group, index) {
    var url = boardLaunchUrl(roomForGroup(group, index), "mini");
    var mini = el('<span class="tio-gcard-live"></span>');
    if (url) {
      var ifr = document.createElement("iframe");
      ifr.setAttribute("tabindex", "-1");
      ifr.setAttribute("title", "Board-Vorschau");
      ifr.src = url;
      mini.appendChild(ifr);
    } else {
      mini.appendChild(el('<span class="ph"><span class="tio-chip">Board</span></span>'));
    }
    return mini;
  }

  // ───────────────────────────────────────────────────────────────────────
  // Persistentes SuS-Board: wird EINMAL geladen und ueberlebt render() (siehe
  // render(): der Host wird nicht mitgeloescht). Der lehrergesteuerte Lock wird
  // per postMessage umgeschaltet, OHNE das iframe neu zu laden. Ein gleichzeitiger
  // Reload aller Gruppen-Peers wuerde sonst die geteilte Board-Szene loeschen.
  var susBoard = null; // { host, iframe, roomid }
  function ensureSusHost() {
    if (susBoard && susBoard.host) { return; }
    var host = el('<div class="tio-susboard"></div>');
    stageEl.appendChild(host);
    susBoard = { host: host, iframe: null, roomid: null };
  }
  // Lock-Status (Bearbeiten/Sichern) live an das bestehende Board schicken -> read-only
  // (nur Zoom/Pan, keine Werkzeuge, kein Verschieben). Kein Reload, kein Datenverlust.
  function pushBoardLock() {
    if (!susBoard || !susBoard.iframe || !susBoard.iframe.contentWindow) { return; }
    var origin = "*";
    try { var b = (BOOT.board && BOOT.board.baseurl) || ""; if (b) { origin = new URL(b).origin; } }
    catch (e) { origin = "*"; }
    susBoard.iframe.contentWindow.postMessage({ toolio: "viewmode", on: !!st.frozen }, origin);
  }
  function susBoardApply(group, index) {
    ensureSusHost();
    var room = roomForGroup(group, index);
    var roomid = (room && room.roomid) ? room.roomid : null;
    susBoard.host.style.display = "";
    if (!susBoard.iframe || susBoard.roomid !== roomid) {
      // (Neu)aufbau nur bei Erstaufbau/Raumwechsel = Einzel-Join (kein Massen-Reload).
      susBoard.host.innerHTML = "";
      susBoard.iframe = null;
      susBoard.roomid = roomid;
      var url = boardLaunchUrl(room, "clean", st.frozen);
      if (url) {
        var ifr = document.createElement("iframe");
        ifr.setAttribute("allow", "clipboard-read; clipboard-write; fullscreen");
        ifr.setAttribute("title", "Board");
        ifr.className = "tio-susboard-frame";
        ifr.addEventListener("load", function () { pushBoardLock(); });
        ifr.src = url;
        susBoard.host.appendChild(ifr);
        susBoard.iframe = ifr;
      } else {
        var miss = el('<div class="tio-board-missing"></div>');
        miss.textContent = (BOOT.board && BOOT.board.enabled)
          ? "Für diese Gruppe ist noch kein Board-Raum vorhanden. Bitte die Aktivität erneut speichern."
          : "Die Board-URL ist noch nicht konfiguriert (Website-Administration → Plugins → Aktivitäten → Toolio).";
        susBoard.host.appendChild(miss);
      }
    }
    pushBoardLock();
  }
  function susBoardRemove() {
    if (susBoard && susBoard.host && susBoard.host.parentNode) {
      susBoard.host.parentNode.removeChild(susBoard.host);
    }
    susBoard = null;
  }

  /* ══════════════════════════════════════════════════════════
     ERSTELLEN (LK ON)
     ══════════════════════════════════════════════════════════ */
  var globeTimer = null;
  var nodeEls = {}, lineEls = [];

  function stopGlobe() { if (globeTimer) { clearInterval(globeTimer); globeTimer = null; } }

  function applyMethod(node) {
    var tool = "none", social = "group";
    if (node.kind === "method") { tool = pickTool(node.tools || []); social = SOCIAL_BY_METHOD[node.id] || "group"; }
    else if (node.kind === "tool") { tool = node.toolKey; social = "group"; }
    else if (node.kind === "social") { tool = "none"; social = node.social; }
    st.preparing = { id:node.id, label:node.label, summary:node.summary || "", kind:node.kind };
    st.prepTool = tool; st.prepSocial = social; st.prepStep = 1;
    render();
  }

  function startBlank() {
    st.materials = [];
    st.preparing = { id:"no-template", label:"Ohne Vorlage", summary:"", kind:"tool" };
    st.prepTool = "none"; st.prepSocial = "individual"; st.prepStep = 1;
    render();
  }

  function computeNodes() {
    var t = st.graphTime, n = NODES.length;
    var cx = 0.5, cy = 0.435, rx = 0.34, ry = 0.245;
    var rotY = t * 0.0048 + st.orbit.x * 0.78;
    var rotX = 0.42 + st.orbit.y * 0.52;
    var tiltZ = -0.3, rotZ = t * 0.0016;
    var ga = Math.PI * (3 - Math.sqrt(5));
    var out = [];
    for (var i = 0; i < n; i++) {
      var node = NODES[i];
      var tt = n > 1 ? i / (n - 1) : 0.5;
      var yS = 1 - tt * 2;
      var radial = Math.sqrt(Math.max(0, 1 - yS * yS));
      var theta = i * ga;
      var x0 = Math.cos(theta) * radial, y0 = yS, z0 = Math.sin(theta) * radial;
      var x1 = x0 * Math.cos(rotY) + z0 * Math.sin(rotY);
      var z1 = -x0 * Math.sin(rotY) + z0 * Math.cos(rotY);
      var yTilt = y0 * Math.cos(rotX) - z1 * Math.sin(rotX);
      var z2 = y0 * Math.sin(rotX) + z1 * Math.cos(rotX);
      var xTilt = x1 * Math.cos(tiltZ) - yTilt * Math.sin(tiltZ);
      var yPre = x1 * Math.sin(tiltZ) + yTilt * Math.cos(tiltZ);
      var x2 = xTilt * Math.cos(rotZ) - yPre * Math.sin(rotZ);
      var y1 = xTilt * Math.sin(rotZ) + yPre * Math.cos(rotZ);
      var d = node.depth;
      var z = z2 * 34 * d;
      var isFocus = st.hovered === node.id;
      var isNeighbor = st.hovered && neighborsOf(st.hovered).indexOf(node.id) >= 0 && !isFocus;
      var nx = Math.min(0.92, Math.max(0.08, cx + x2 * rx));
      var ny = Math.min(0.84, Math.max(0.12, cy + y1 * ry));
      out.push({
        id:node.id,
        nx:nx, ny:ny,
        nz:z + (isFocus ? 44 : isNeighbor ? 14 : 0),
        nrx:(-y1 * 6) * d, nry:(x2 * 8) * d,
        ns:1 + (isFocus ? 0.3 : isNeighbor ? 0.08 : 0),
        zLayer:Math.round(200 + z2 * 120) + (isFocus ? 600 : isNeighbor ? 300 : 0),
        opacity:(function () {
          if (st.hovered) {
            if (isFocus) return 1;
            if (isNeighbor) return Math.min(.95, node.opacity + .06);
            return Math.max(.24, node.opacity * .5);
          }
          return node.opacity;
        })(),
        muted:!!st.hovered && !isFocus && !isNeighbor
      });
    }
    return out;
  }

  var _adj = null;
  function neighborsOf(id) {
    if (!_adj) {
      _adj = {};
      EDGES.forEach(function (e) {
        (_adj[e[0]] = _adj[e[0]] || []).push(e[1]);
        (_adj[e[1]] = _adj[e[1]] || []).push(e[0]);
      });
    }
    return _adj[id] || [];
  }

  function buildGlobe() {
    var canvas = el('<div class="tio-canvas" id="tio-canvas"></div>');
    var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("class", "tio-links");
    lineEls = EDGES.map(function (e) {
      var ln = document.createElementNS("http://www.w3.org/2000/svg", "line");
      ln.dataset.from = e[0]; ln.dataset.to = e[1];
      svg.appendChild(ln); return ln;
    });
    canvas.appendChild(svg);
    nodeEls = {};
    NODES.forEach(function (node) {
      var b = el('<button class="tio-node k-' + node.kind + ' sz-' + node.size + '" type="button">' + esc(node.label) + '</button>');
      b.addEventListener("mouseenter", function () { st.hovered = node.id; });
      b.addEventListener("mouseleave", function () { st.hovered = null; });
      b.addEventListener("focus", function () { st.hovered = node.id; });
      b.addEventListener("blur", function () { st.hovered = null; });
      b.addEventListener("click", function () { applyMethod(node); });
      nodeEls[node.id] = b;
      canvas.appendChild(b);
    });
    stageEl.appendChild(canvas);

    stopGlobe();
    globeTimer = setInterval(function () {
      if (!st.hovered) st.graphTime += 1;
      st.orbit.x += (st.orbitTarget.x - st.orbit.x) * 0.08;
      st.orbit.y += (st.orbitTarget.y - st.orbit.y) * 0.08;
      paintGlobe();
    }, 60);
    paintGlobe();
  }

  function paintGlobe() {
    var pos = {}, arr = computeNodes();
    arr.forEach(function (p) {
      pos[p.id] = p;
      var b = nodeEls[p.id];
      if (!b) return;
      b.style.left = (p.nx * 100) + "%";
      b.style.top = (p.ny * 100) + "%";
      b.style.opacity = p.opacity;
      b.style.zIndex = p.zLayer;
      b.style.setProperty("--ns", p.ns);
      b.style.setProperty("--nz", p.nz + "px");
      b.style.setProperty("--nrx", p.nrx + "deg");
      b.style.setProperty("--nry", p.nry + "deg");
      b.classList.toggle("is-active", st.hovered === p.id);
      b.classList.toggle("is-muted", p.muted);
    });
    lineEls.forEach(function (ln) {
      var a = pos[ln.dataset.from], c = pos[ln.dataset.to];
      if (!a || !c) { ln.style.opacity = 0; return; }
      var show = st.hovered && (ln.dataset.from === st.hovered || ln.dataset.to === st.hovered);
      ln.setAttribute("x1", (a.nx * 100) + "%"); ln.setAttribute("y1", (a.ny * 100) + "%");
      ln.setAttribute("x2", (c.nx * 100) + "%"); ln.setAttribute("y2", (c.ny * 100) + "%");
      ln.style.opacity = show ? 0.5 : 0;
      ln.style.stroke = show ? "rgba(100,116,139,0.7)" : "rgba(100,116,139,0)";
    });
  }

  /* Vorbereiten-Screens */
  function matLabel(item) {
    return item.replace(/^Upload:\s*/, "").replace(/^Moodle-Auswahl:\s*/, "").replace(/^Debug-Datei:\s*/, "");
  }
  function addMaterials(items) {
    items.forEach(function (it) { if (st.materials.indexOf(it) < 0) st.materials.push(it); });
    render();
  }

  /* ══════════════════════════════════════════════════════════
     ABFRAGE-EDITOR (Schritt 4 – Abfrage-Einstellungen)
     ══════════════════════════════════════════════════════════ */
  // Arbeitskopie der Abfrage — wird aus BOOT.state.abfrage initialisiert.
  var abfrageState = (function () {
    var s = (BOOT.state && BOOT.state.abfrage) || {};
    return {
      title:     s.title     || '',
      questions: (s.questions || []).map(function (q) { return JSON.parse(JSON.stringify(q)); }),
      settings:  s.settings  || { anonymous: false, timer: 0 },
      active:    !!s.active
    };
  })();

  var _abfrageTypeLabels = { choice:'Auswahl', text:'Freitext', rating:'Bewertung', scale:'Skala' };
  var _abfrageVizLabels  = { bar:'Balken', pie:'Torte', wordcloud:'Wortwolke', none:'Ohne' };
  var _abfrageVizFor     = { choice:['bar','pie'], text:['wordcloud','none'], rating:['bar'], scale:['bar'] };

  function abfrageNewQuestion() {
    return { type:'choice', text:'', required: false, options:[''], viz:'bar' };
  }

  function abfrageSave(onDone) {
    var body = new URLSearchParams();
    body.set('id', BOOT.cmid);
    body.set('tool', 'abfrage');
    body.set('sesskey', BOOT.sesskey);
    body.set('payload', JSON.stringify(abfrageState));
    fetch(BOOT.saveurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.ok) {
          if (!BOOT.state) BOOT.state = {};
          BOOT.state.abfrage = JSON.parse(JSON.stringify(abfrageState));
          st.activityReady = true;
        }
        if (onDone) onDone(res && res.ok);
      })
      .catch(function () { if (onDone) onDone(false); });
  }

  function buildAbfrageEditor() {
    var wrap = el('<div class="tio-abf"></div>');

    // Titel-Bereich
    var titleWrap = el('<div class="tio-abf-titlebar"></div>');
    var titleIn = el('<input type="text" class="tio-abf-maintitle" placeholder="Titel der Abfrage …" maxlength="200">');
    titleIn.value = abfrageState.title;
    titleIn.addEventListener('input', function () { abfrageState.title = this.value; renderDebugJson(); });
    var saveBtn = el('<button type="button" class="tio-abf-savebtn">Speichern</button>');
    var saveFeedback = el('<span class="tio-abf-feedback"></span>');
    saveBtn.addEventListener('click', function () {
      saveBtn.disabled = true;
      saveFeedback.textContent = '…';
      abfrageSave(function (ok) {
        saveBtn.disabled = false;
        saveFeedback.textContent = ok ? '✓ gespeichert' : '✗ Fehler';
        setTimeout(function () { saveFeedback.textContent = ''; }, 2500);
      });
    });
    titleWrap.appendChild(titleIn);
    titleWrap.appendChild(saveBtn);
    titleWrap.appendChild(saveFeedback);
    wrap.appendChild(titleWrap);

    // Fragenliste
    var qlist = el('<div class="tio-abf-qlist"></div>');
    wrap.appendChild(qlist);

    function renderQuestions() {
      qlist.innerHTML = '';
      abfrageState.questions.forEach(function (q, qi) {
        var card = el('<div class="tio-qcard"></div>');

        // Toolbar
        var toolbar = el('<div class="tio-qcard-toolbar"></div>');
        var delBtn = el('<button type="button" class="tio-qcard-iconbtn" title="Frage entfernen">🗑</button>');
        delBtn.addEventListener('click', function () { abfrageState.questions.splice(qi, 1); renderQuestions(); renderDebugJson(); });
        toolbar.appendChild(delBtn);
        card.appendChild(toolbar);

        // Body
        var body = el('<div class="tio-qcard-body"></div>');

        // Fragezeile: Nummer + Input
        var qrow = el('<div class="tio-qcard-qrow"></div>');
        var num = el('<span class="tio-qcard-num">' + (qi + 1) + '</span>');
        var textIn = el('<input type="text" class="tio-qcard-qinput" placeholder="Frage eingeben …" maxlength="500">');
        textIn.value = q.text;
        textIn.addEventListener('input', function () { abfrageState.questions[qi].text = this.value; renderDebugJson(); });
        qrow.appendChild(num);
        qrow.appendChild(textIn);
        body.appendChild(qrow);

        // Options (choice)
        if (q.type === 'choice') {
          var optList = el('<div class="tio-qcard-opts"></div>');
          (q.options.length ? q.options : ['']).forEach(function (opt, oi) {
            var optRow = el('<div class="tio-qcard-optrow"></div>');
            optRow.innerHTML = '<span class="tio-qcard-bullet">○</span>';
            var optIn = el('<input type="text" class="tio-qcard-optinput" placeholder="Option ' + (oi + 1) + '" maxlength="200">');
            optIn.value = opt;
            optIn.addEventListener('input', function () { abfrageState.questions[qi].options[oi] = this.value; renderDebugJson(); });
            var rmOpt = el('<button type="button" class="tio-qcard-optdel" title="Entfernen">×</button>');
            rmOpt.addEventListener('click', function () {
              abfrageState.questions[qi].options.splice(oi, 1);
              if (!abfrageState.questions[qi].options.length) abfrageState.questions[qi].options = [''];
              renderQuestions(); renderDebugJson();
            });
            optRow.appendChild(optIn);
            optRow.appendChild(rmOpt);
            optList.appendChild(optRow);
          });
          var addOpt = el('<span class="tio-qcard-addopt">+ Option hinzufügen</span>');
          addOpt.addEventListener('click', function () { abfrageState.questions[qi].options.push(''); renderQuestions(); renderDebugJson(); });
          optList.appendChild(addOpt);
          body.appendChild(optList);
        }

        // Text-Hint
        if (q.type === 'text') {
          body.appendChild(el('<div class="tio-qcard-hint">Freitextantwort (kein Editor nötig)</div>'));
        }
        if (q.type === 'rating') {
          body.appendChild(el('<div class="tio-qcard-stars">★ ★ ★ ★ ★</div>'));
        }
        if (q.type === 'scale') {
          body.appendChild(el('<div class="tio-qcard-hint">Skala 0 – 10</div>'));
        }

        card.appendChild(body);

        // Footer: Typ + Viz + Pflicht
        var footer = el('<div class="tio-qcard-footer"></div>');

        var typeSel = el('<select class="tio-qcard-sel" title="Fragetyp">');
        Object.keys(_abfrageTypeLabels).forEach(function (k) {
          var o = el('<option value="' + k + '">' + _abfrageTypeLabels[k] + '</option>');
          if (k === q.type) o.selected = true;
          typeSel.appendChild(o);
        });
        typeSel.addEventListener('change', function () {
          var nk = this.value;
          abfrageState.questions[qi].type = nk;
          abfrageState.questions[qi].viz = (_abfrageVizFor[nk] || ['none'])[0];
          if (nk === 'choice' && !abfrageState.questions[qi].options.length) abfrageState.questions[qi].options = [''];
          if (nk !== 'choice') abfrageState.questions[qi].options = [];
          renderQuestions(); renderDebugJson();
        });

        var vizSel = el('<select class="tio-qcard-sel tio-qcard-viz" title="Visualisierung">');
        (_abfrageVizFor[q.type] || ['none']).forEach(function (k) {
          var o = el('<option value="' + k + '">' + _abfrageVizLabels[k] + '</option>');
          if (k === q.viz) o.selected = true;
          vizSel.appendChild(o);
        });
        vizSel.addEventListener('change', function () { abfrageState.questions[qi].viz = this.value; renderDebugJson(); });

        var reqWrap = el('<label class="tio-qcard-req" title="Pflichtfeld"><input type="checkbox"' + (q.required ? ' checked' : '') + '> Pflicht</label>');
        reqWrap.querySelector('input').addEventListener('change', function () { abfrageState.questions[qi].required = this.checked; renderDebugJson(); });

        footer.appendChild(typeSel);
        footer.appendChild(vizSel);
        footer.appendChild(reqWrap);
        card.appendChild(footer);

        qlist.appendChild(card);
      });

      // "Frage hinzufügen" — Typ-Kacheln
      var addArea = el('<div class="tio-abf-addarea"></div>');
      var addLabel = el('<span class="tio-abf-addlabel"><span class="tio-abf-addplus">＋</span> Frage hinzufügen</span>');
      addArea.appendChild(addLabel);
      var addTypes = el('<div class="tio-abf-addtypes"></div>');
      Object.keys(_abfrageTypeLabels).forEach(function (k) {
        var icons = { choice:'☑', text:'✏️', rating:'★', scale:'↔' };
        var b = el('<button type="button" class="tio-abf-typecard">' +
          '<span class="tio-abf-typeicon">' + (icons[k] || '?') + '</span>' +
          '<span>' + _abfrageTypeLabels[k] + '</span></button>');
        b.addEventListener('click', function () {
          var nq = { type:k, text:'', required:false,
            options: k === 'choice' ? [''] : [],
            viz: (_abfrageVizFor[k] || ['none'])[0] };
          abfrageState.questions.push(nq);
          renderQuestions(); renderDebugJson();
          // Zur neuen Karte scrollen
          var cards = qlist.querySelectorAll('.tio-qcard');
          if (cards.length) cards[cards.length - 1].scrollIntoView({ behavior:'smooth', block:'nearest' });
        });
        addTypes.appendChild(b);
      });
      addArea.appendChild(addTypes);
      qlist.appendChild(addArea);
    }

    renderQuestions();
    return wrap;
  }

  function renderDebugJson() {
    var dbgEl = document.getElementById('tio-debug-json');
    if (!dbgEl) return;
    try {
      var snap;
      if (BOOT.view === 'lk_on') {
        // Vollständiger Live-Stand: boardGroups() liest direkt aus BOARD.parts (immer aktuell),
        // kein syncGroupsFromBoard()-Aufruf nötig. serializeBoard() liefert den Roh-Zustand.
        var socMap = { individual:'einzel', partner:'paar', group:'gruppe' };
        var toolMap = { none:'none', board:'board', chat:'chatbot', poll:'abfrage' };
        var liveGrps = boardGroups().map(function(g) {
          return { id:g.id, name:g.label,
            members:g.members.map(function(m) { return m.name; }),
            memberids:g.members.map(function(m) { return m.id; }) };
        });
        snap = {
          methodid:      (st.preparing && st.preparing.id)      || 'no-template',
          methodlabel:   (st.preparing && st.preparing.label)   || 'Ohne Vorlage',
          methodsummary: (st.preparing && st.preparing.summary) || '',
          materials:     (st.materials || []).slice(),
          tool:          toolMap[st.prepTool] || 'none',
          sozialform:    BOARD.mode === 'partner' ? 'paar'
                         : (socMap[st.prepSocial] || 'gruppe'),
          groups:        liveGrps,
          board:         serializeBoard(),
          abfrage:       JSON.parse(JSON.stringify(abfrageState))
        };
      } else {
        snap = BOOT.state || {};
      }
      dbgEl.textContent = JSON.stringify(snap, null, 2);
    } catch(e) { dbgEl.textContent = '(Fehler: ' + e.message + ')'; }
  }

  function buildPrepare() {
    var wrap = el('<div class="tio-prep"></div>');
    if (st.prepStep === 1) {
      var drop = el('<div class="tio-drop"><h3>Dateien hier ablegen</h3>' +
        '<p>Ziehe Dateien in diese Fläche oder wähle unten eine Quelle aus.</p></div>');
      drop.addEventListener("dragover", function (e) { e.preventDefault(); drop.classList.add("drag"); });
      drop.addEventListener("dragleave", function () { drop.classList.remove("drag"); });
      drop.addEventListener("drop", function (e) {
        e.preventDefault(); drop.classList.remove("drag");
        var files = Array.prototype.slice.call(e.dataTransfer.files || []);
        if (files.length) addMaterials(files.map(function (f) { return "Upload: " + f.name; }));
      });
      var actions = el('<div class="tio-drop-actions"></div>');
      var up = el('<button type="button" class="tio-ghostbtn">Dateien vom Gerät hochladen</button>');
      var input = el('<input type="file" multiple style="display:none">');
      up.addEventListener("click", function () { input.click(); });
      input.addEventListener("change", function () {
        var files = Array.prototype.slice.call(input.files || []);
        if (files.length) addMaterials(files.map(function (f) { return "Upload: " + f.name; }));
      });
      var mo = el('<button type="button" class="tio-ghostbtn">Material aus Moodle-Kurs auswählen</button>');
      mo.addEventListener("click", function () { addMaterials(["Moodle-Auswahl: Material aus Kurs"]); });
      actions.appendChild(up); actions.appendChild(input); actions.appendChild(mo);
      drop.appendChild(actions);
      if (st.materials.length) {
        var box = el('<div class="tio-matbox"><h4>Material der Einheit</h4><ul class="tio-matlist"></ul></div>');
        var ul = box.querySelector("ul");
        st.materials.forEach(function (item) {
          var li = el('<li><span class="t">' + esc(matLabel(item)) + '</span><button type="button" title="Entfernen">' + iconSvg("trash") + '</button></li>');
          li.querySelector("button").addEventListener("click", function () {
            st.materials = st.materials.filter(function (x) { return x !== item; }); render();
          });
          ul.appendChild(li);
        });
        drop.appendChild(box);
      }
      wrap.appendChild(drop);
    } else if (st.prepStep === 2) {
      var tools = el('<div class="tio-tools"></div>');
      ["chat", "board", "poll"].forEach(function (key) {
        var t = TOOL_PITCH[key], on = st.prepTool === key;
        var col = el('<div class="tio-tool ' + (on ? "on" : "") + '">' +
          '<h3>' + esc(t.label) + '</h3>' +
          '<p class="pitch">' + esc(t.pitch) + '</p>' +
          '<ul>' + t.keys.map(function (k) { return "<li>" + esc(k) + "</li>"; }).join("") + '</ul>' +
          '<p class="res">' + esc(t.res) + '</p></div>');
        col.addEventListener("click", function () {
          st.prepTool = (st.prepTool === key) ? "none" : key; render();
        });
        tools.appendChild(col);
      });
      wrap.appendChild(tools);
    } else if (st.prepStep === 3) {
      // Schritt 3: Sozialform = Gruppentool-Whiteboard
      wrap.appendChild(buildGruppentool());
    } else {
      // Schritt 4: Individuelle Tool-Einstellungen
      if (st.prepTool === 'poll') {
        wrap.appendChild(buildAbfrageEditor());
      } else {
        var toolLabels = { chat:"KI-Chatbot", board:"Board" };
        var selTool = toolLabels[st.prepTool] || null;
        var settingsWrap = el('<div style="padding:8px 0;"></div>');
        if (!selTool) {
          settingsWrap.innerHTML = '<p style="color:#64748b;font-size:.9rem;">Kein Tool ausgewählt — keine weiteren Einstellungen.</p>';
        } else {
          settingsWrap.innerHTML = '<h3 style="font-size:1rem;font-weight:700;margin:0 0 8px;">' + esc(selTool) + '-Einstellungen</h3>' +
            '<p style="color:#64748b;font-size:.88rem;">Individuelle Konfiguration für <strong>' + esc(selTool) + '</strong> folgt hier.</p>';
        }
        wrap.appendChild(settingsWrap);
      }
    }
    return wrap;
  }

  /* Fußleiste ERSTELLEN */
  function pillLabel() {
    if (!st.preparing) return "Ohne Vorlage";
    if (st.prepStep === 1 && st.materials.length === 0) return "Weiter ohne Material";
    if (st.prepStep === 2 && st.prepTool === "none") return "Weiter ohne Tool";
    if (st.prepStep === 3) return "Weiter zu Einstellungen";
    if (st.prepStep === 4) return st.activityReady ? "Änderungen speichern" : "Starten";
    return "Weiter";
  }
  function pillClick() {
    if (!st.preparing) { startBlank(); return; }
    if (st.prepStep >= 4) { release(); return; }
    st.prepStep = Math.min(4, st.prepStep + 1); render();
  }

  function buildCreateFooter() {
    var left = el('<div style="display:flex;align-items:center;gap:8px;flex:1;"></div>');
    var right = el('<div style="display:flex;align-items:center;gap:10px;"></div>');
    // Schritt-Kette immer anzeigen (Startseite = Schritt 0 "Methoden" aktiv)
    var currentStep = st.preparing ? st.prepStep : 0;
    var step4label = { chat:"KI-Einstellungen", board:"Board-Einstellungen", poll:"Abfrage-Einstellungen" }[st.prepTool] || "Einstellungen";
    var defs = [
      ["#1e293b", "Methoden"],
      ["#d3a221", "Material"],
      ["#2f5f96", "Werkzeug"],
      ["#bf4c44", "Sozialform"],
      ["#7b3fa8", step4label]
    ];
    var steps = el('<div class="tio-steps"></div>');
    defs.forEach(function (d, idx) {
      var active = currentStep === idx, done = currentStep > idx;
      var step = el('<div class="tio-step"></div>');
      var b = el('<button type="button" title="' + esc(d[1]) + '"></button>');
      b.style.borderColor = d[0];
      b.style.borderWidth = (active || done) ? "2px" : "1px";
      b.style.background = active ? (d[0] + "14") : "transparent";
      b.style.boxShadow = active ? ("0 0 0 2px " + d[0] + "3d") : "none";
      b.innerHTML = '<span class="dot" style="background:' + (done ? d[0] : (active ? d[0] : d[0] + "44")) + '"></span>';
      b.addEventListener("click", function () {
        if (idx === 0) {
          st.preparing = false;
        } else {
          if (!st.preparing) {
            st.preparing = { id:"no-template", label:"Ohne Vorlage", summary:"", kind:"method" };
          }
          st.prepStep = idx;
        }
        render();
      });
      step.appendChild(b);
      step.appendChild(el('<span class="cap" style="color:' + (active ? "#0f172a" : (done ? "#475569" : "#94a3b8")) + '">' + esc(d[1]) + '</span>'));
      steps.appendChild(step);
      if (idx < defs.length - 1) steps.appendChild(el('<div class="tio-step-sep"><span></span></div>'));
    });
    left.appendChild(steps);
    var pill = el('<button type="button" class="tio-pill">' + esc(pillLabel()) + '</button>');
    pill.addEventListener("click", pillClick);
    right.appendChild(pill);
    footInEl.innerHTML = "";
    footInEl.appendChild(left);
    footInEl.appendChild(right);
    footEl.style.display = "";
  }

  /* Freigabe → save.php → LK OFF */
  function release() {
    syncGroupsFromBoard();
    var payload = boardPayload();
    var body = new URLSearchParams();
    body.set("id", BOOT.cmid); body.set("tool", "gruppen");
    body.set("sesskey", BOOT.sesskey); body.set("payload", JSON.stringify(payload));
    var pill = footInEl.querySelector(".tio-pill");
    if (pill) { pill.textContent = "Speichern…"; pill.disabled = true; }
    fetch(BOOT.saveurl, { method:"POST", headers:{ "Content-Type":"application/x-www-form-urlencoded" }, body:body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.ok) { window.location.href = BOOT.editOffUrl || BOOT.viewurl; }
        else { alert("Speichern fehlgeschlagen: " + ((res && res.error) || "unbekannt")); if (pill) { pill.disabled = false; render(); } }
      })
      .catch(function () { alert("Netzwerkfehler beim Speichern."); if (pill) { pill.disabled = false; render(); } });
  }

  /* ══════════════════════════════════════════════════════════
     ABFRAGE-ANSICHTEN (LK OFF + SuS)
     ══════════════════════════════════════════════════════════ */
  function buildAbfrageLkOff() {
    var abf = (BOOT.state && BOOT.state.abfrage) || {};
    var questions = abf.questions || [];
    var title = abf.title || '';
    var wrap = el('<div class="tio-abf-lkoff"></div>');

    var hdr = el('<div class="tio-abf-lkoff-hdr">' +
      '<span class="icon">❓</span>' +
      '<span class="ttl">' + (title ? esc(title) : 'Abfrage') + '</span>' +
      '<span class="cnt">' + questions.length + ' Frage' + (questions.length !== 1 ? 'n' : '') + '</span>' +
      '</div>');
    wrap.appendChild(hdr);

    if (questions.length === 0) {
      wrap.appendChild(el('<p class="tio-abf-lkoff-empty">Noch keine Fragen konfiguriert. Schalte in LK ON und richte die Abfrage ein.</p>'));
    } else {
      var list = el('<div class="tio-abf-lkoff-list"></div>');
      questions.forEach(function (q, i) {
        var typeLabel = { choice:'Auswahl', text:'Freitext', rating:'Bewertung', scale:'Skala' }[q.type] || q.type;
        var card = el('<div class="tio-abf-lkoff-card">' +
          '<span class="num">' + (i + 1) + '</span>' +
          '<span class="qtext">' + esc(q.text || '(kein Text)') + '</span>' +
          '<span class="qtype">' + esc(typeLabel) + '</span>' +
          '</div>');
        if (q.type === 'choice' && q.options && q.options.length) {
          var opts = el('<ul class="tio-abf-lkoff-opts"></ul>');
          q.options.forEach(function (o) { opts.appendChild(el('<li>' + esc(o) + '</li>')); });
          card.appendChild(opts);
        }
        list.appendChild(card);
      });
      wrap.appendChild(list);
    }

    var footer = el('<div class="tio-abf-lkoff-foot"><span>🟡 Schüler:innen können jetzt antworten</span></div>');
    wrap.appendChild(footer);
    return wrap;
  }

  function buildAbfrageSus() {
    var abf = (BOOT.state && BOOT.state.abfrage) || {};
    var questions = abf.questions || [];
    var title = abf.title || '';
    var wrap = el('<div class="tio-abf-sus"></div>');

    if (questions.length === 0) {
      wrap.appendChild(el('<p class="tio-abf-sus-wait">⏳ Abfrage wird vorbereitet…</p>'));
      return wrap;
    }

    var hdr = el('<h2 class="tio-abf-sus-title">' + (title ? esc(title) : 'Abfrage') + '</h2>');
    wrap.appendChild(hdr);

    var form = el('<form class="tio-abf-sus-form" onsubmit="return false;"></form>');
    questions.forEach(function (q, qi) {
      var fieldset = el('<div class="tio-abf-sus-field"></div>');
      var lbl = el('<label class="tio-abf-sus-qlabel">' +
        '<span class="num">' + (qi + 1) + '.</span>' +
        '<span>' + esc(q.text || '…') + '</span>' +
        (q.required ? '<span class="req">*</span>' : '') +
        '</label>');
      fieldset.appendChild(lbl);

      if (q.type === 'choice') {
        (q.options || []).forEach(function (opt, oi) {
          var r = el('<label class="tio-abf-sus-opt"><input type="radio" name="q' + qi + '" value="' + oi + '"> ' + esc(opt) + '</label>');
          fieldset.appendChild(r);
        });
      } else if (q.type === 'text') {
        fieldset.appendChild(el('<textarea class="tio-abf-sus-txt" rows="3" placeholder="Deine Antwort…"></textarea>'));
      } else if (q.type === 'rating') {
        var stars = el('<div class="tio-abf-sus-stars"></div>');
        [1,2,3,4,5].forEach(function (v) {
          var s = el('<label class="star"><input type="radio" name="q' + qi + '" value="' + v + '"> ★</label>');
          stars.appendChild(s);
        });
        fieldset.appendChild(stars);
      } else if (q.type === 'scale') {
        var scale = el('<div class="tio-abf-sus-scale"></div>');
        for (var v = 0; v <= 10; v++) {
          var btn = el('<label class="scl"><input type="radio" name="q' + qi + '" value="' + v + '"> ' + v + '</label>');
          scale.appendChild(btn);
        }
        fieldset.appendChild(scale);
      }
      form.appendChild(fieldset);
    });

    var submit = el('<button type="submit" class="tio-abf-sus-submit">Abschicken</button>');
    submit.addEventListener('click', function () {
      submit.textContent = '✓ Antwort abgeschickt';
      submit.disabled = true;
      form.style.opacity = '.5';
      form.style.pointerEvents = 'none';
    });
    form.appendChild(submit);
    wrap.appendChild(form);
    return wrap;
  }

  /* ══════════════════════════════════════════════════════════
     DURCHFÜHREN (LK OFF)
     ══════════════════════════════════════════════════════════ */
  // Effektive Gruppen: bevorzugt der gespeicherte/live-gepushte Zustand (state.groups,
  // Feld »members«), sonst die Moodle-Kursgruppen aus dem Boot. So schlagen — wie im
  // Prototyp — Gruppenänderungen der LK (per SSE) bis in LK OFF und die Schüleransicht durch.
  function effectiveGroups() {
    var sg = (BOOT.state && BOOT.state.groups) || [];
    if (sg.length) {
      return sg.map(function (g, i) {
        return { id:g.id || ("g" + (i + 1)), name:g.name || ("Gruppe " + (i + 1)),
          students:((g.members || g.students) || []).slice(),
          studentids:((g.memberids || g.studentids) || []).slice() };
      });
    }
    return GROUPS;
  }
  // Echte Gruppen vorhanden? Der reine "Kurs"-Fallback (id "all", alle Lernenden in einem
  // Topf) zaehlt NICHT als Gruppierung — dann ist es Einzelarbeit.
  function hasRealGroups() {
    var sg = (BOOT.state && BOOT.state.groups) || [];
    if (sg.length) { return true; }
    return GROUPS.length > 1 || (GROUPS.length === 1 && GROUPS[0].id !== "all");
  }
  // Effektive Sozialform: 0 echte Gruppen => Einzelarbeit (jede:r den eigenen Screen),
  // niemals der "Kurs"-Sammeltopf. Sonst die gespeicherte/gepushte Sozialform.
  function liveSocial() {
    var stateSoc = BOOT.state && BOOT.state.sozialform;
    return hasRealGroups() ? socKey(stateSoc || "gruppe") : "individual";
  }
  function liveGroups() {
    var soc = liveSocial();
    var base = effectiveGroups();
    if (soc === "individual") {
      var flat = [];
      base.forEach(function (g) {
        (g.students || []).forEach(function (name, i) {
          flat.push({ name:name, id:(g.studentids || [])[i] });
        });
      });
      return flat.map(function (s, i) {
        return { id:"s" + (i + 1), name:s.name, students:[s.name],
          studentids:(s.id != null ? [s.id] : []) };
      });
    }
    return base;
  }
  function previewTitle(g) {
    if (!g) return "";
    if (!g.students || g.students.length <= 1) return g.name;
    return g.name + ": " + g.students.join(", ");
  }

  function buildLive() {
    if (!st.activityReady) {
      var lock = el('<div class="tio-locked"><div class="tio-locked-card">' +
        '<div class="k">LK OFF</div><h3>Hier gibt es noch nichts zu steuern.</h3>' +
        '<p>Richte zuerst eine Aktivität ein, dann kannst du in LK OFF moderieren.</p>' +
        '<a href="' + esc(BOOT.editOnUrl) + '">Aktivität einrichten</a></div></div>');
      stageEl.appendChild(lock);
      footEl.style.display = "none";
      return;
    }
    // LK OFF zeigt nur das ERGEBNIS der Gruppenbildung (read-only), nicht das interaktive
    // Gruppentool. Das Gruppentool gehoert ausschliesslich in LK ON (Sozialform bearbeiten).
    // Gruppenaenderungen der LK spiegeln sich hier weiterhin per SSE ueber liveGroups().
    var groups = liveGroups(), ph = toolPlaceholder();
    var boardselected = ((BOOT.state && BOOT.state.tool) === "board");
    var abfrageselected = ((BOOT.state && BOOT.state.tool) === "abfrage");

    // LK OFF: Abfrage-Moderationsansicht
    if (abfrageselected) {
      var abfLkOff = buildAbfrageLkOff();
      stageEl.appendChild(abfLkOff);
      buildLiveFooter();
      return;
    }

    var wrap = el('<div class="tio-live"><div class="tio-live-in"><div class="tio-live-scroll"><div class="tio-live-grid"></div></div></div></div>');
    var grid = wrap.querySelector(".tio-live-grid");
    groups.forEach(function (g, idx) {
      if (boardselected) {
        // Live-Miniatur: jede Karte zeigt den echten (WS-synchronisierten) Board-Stand
        // der Gruppe. Klick oeffnet das grosse Board. Kein <button>, da iframe darin ungueltig.
        var bcard = el('<div class="tio-gcard tio-gcard-board" role="button" tabindex="0">' +
          '<span class="gt" title="' + esc(previewTitle(g)) + '">' + esc(previewTitle(g)) + '</span></div>');
        bcard.appendChild(boardMini(g, idx));
        var hit = el('<span class="tio-gcard-hit" title="Board öffnen"></span>');
        var openFull = function () {
          openBoardForGroup(g, idx, wrap.querySelector(".tio-live-in"), { closable: true });
        };
        hit.addEventListener("click", openFull);
        bcard.addEventListener("keydown", function (e) {
          if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openFull(); }
        });
        bcard.appendChild(hit);
        grid.appendChild(bcard);
        return;
      }
      var card = el('<button type="button" class="tio-gcard"><span class="gt" title="' + esc(previewTitle(g)) + '">' +
        esc(previewTitle(g)) + '</span><span class="ph"><span class="tio-chip">' + esc(ph) + '</span></span></button>');
      card.addEventListener("click", function () {
        st.focusedGroup = g.id;
        render();
      });
      grid.appendChild(card);
    });
    if (!boardselected && st.focusedGroup) {
      var fg = null;
      var fgidx = -1;
      for (var i = 0; i < groups.length; i++) {
        if (groups[i].id === st.focusedGroup) {
          fg = groups[i];
          fgidx = i;
          break;
        }
      }
      if (fg) {
        var ov = el('<div class="tio-overlay"><span class="gt" title="' + esc(previewTitle(fg)) + '">' +
          esc(previewTitle(fg)) + '</span><button type="button" class="tio-overlay-close" title="Schließen">' + iconSvg("close") + '</button>' +
          '<span class="ph"><span class="tio-chip">' + esc(ph) + '</span></span></div>');
        ov.querySelector(".tio-overlay-close").addEventListener("click", function () { st.focusedGroup = null; render(); });
        wrap.querySelector(".tio-live-in").appendChild(ov);
      }
    }
    stageEl.appendChild(wrap);
    buildLiveFooter();
  }

  // Timer-Zustand der LK (LK OFF) an alle verteilen: über denselben versionierten State,
  // damit SuS per SSE denselben Countdown sehen. Leicht entprellt gegen Klick-Spam.
  var _timerPushT = null;
  function pushTimer() {
    if (BOOT.view !== "lk_off") { return; }
    if (_timerPushT) { clearTimeout(_timerPushT); }
    _timerPushT = setTimeout(function () {
      var body = new URLSearchParams();
      body.set("id", BOOT.cmid); body.set("tool", "timer"); body.set("sesskey", BOOT.sesskey);
      body.set("payload", JSON.stringify({ running: st.timerRunning, seconds: st.timerSeconds }));
      fetch(BOOT.saveurl, { method:"POST", headers:{ "Content-Type":"application/x-www-form-urlencoded" }, body:body.toString() }).catch(function () {});
    }, 120);
  }

  // Bearbeiten/Sichern-Switch (LK OFF) an alle verteilen: schreibt den frozen-Stand in
  // den versionierten State, damit SuS ihn per Poll erhalten und ihr Board sperren.
  function pushFrozen() {
    if (BOOT.view !== "lk_off") { return; }
    var body = new URLSearchParams();
    body.set("id", BOOT.cmid); body.set("tool", "frozen"); body.set("sesskey", BOOT.sesskey);
    body.set("payload", JSON.stringify({ frozen: !!st.frozen }));
    fetch(BOOT.saveurl, { method:"POST", headers:{ "Content-Type":"application/x-www-form-urlencoded" }, body:body.toString() }).catch(function () {});
  }

  function buildLiveFooter() {
    footInEl.innerHTML = "";
    var timer = el('<div class="tio-timer" title="Timersteuerung">' +
      '<span class="t-clock">' + iconSvg("clock") + '</span><span class="sep"></span>' +
      '<div class="grp l">' +
        '<button class="step sm" data-d="-300" title="5 Min abziehen">-5</button>' +
        '<button class="step" data-d="-60" title="1 Min abziehen">-1</button>' +
      '</div>' +
      '<button class="val" title="Zeit bearbeiten">' + fmt(st.timerSeconds) + '</button>' +
      '<div class="grp r">' +
        '<button class="step" data-d="60" title="1 Min hinzufügen">+1</button>' +
        '<button class="step sm" data-d="300" title="5 Min hinzufügen">+5</button>' +
      '</div>' +
      '<span class="sep"></span>' +
      '<button class="pp" title="Timer starten/pausieren">' + (st.timerRunning ? iconSvg("pause") : iconSvg("play")) + '</button></div>');
    timer.querySelectorAll(".step").forEach(function (b) {
      b.addEventListener("click", function () {
        st.timerSeconds = Math.max(0, st.timerSeconds + parseInt(b.dataset.d, 10)); pushTimer(); render();
      });
    });
    timer.querySelector(".val").addEventListener("click", function () {
      var valBtn = this;
      var input = el('<input class="val-input" type="text" inputmode="numeric" maxlength="5" aria-label="Timerzeit bearbeiten" title="Format MM:SS oder nur Minuten" />');
      input.value = fmt(st.timerSeconds);
      valBtn.replaceWith(input);
      input.focus(); input.select();
      var done = false;
      function commit() {
        if (done) return; done = true;
        var v = input.value.trim(), sec = null;
        if (/^\d+$/.test(v)) sec = parseInt(v, 10) * 60;
        else { var m = v.match(/^(\d{1,3}):([0-5]\d)$/); if (m) sec = parseInt(m[1], 10) * 60 + parseInt(m[2], 10); }
        if (sec != null) { st.timerSeconds = sec; pushTimer(); }
        render();
      }
      input.addEventListener("blur", commit);
      input.addEventListener("keydown", function (e) {
        if (e.key === "Enter") { e.preventDefault(); commit(); }
        else if (e.key === "Escape") { e.preventDefault(); done = true; render(); }
      });
    });
    timer.querySelector(".pp").addEventListener("click", function () { st.timerRunning = !st.timerRunning; pushTimer(); render(); });
    var toggle = el('<div class="tio-savetoggle">' +
      '<button class="lab ' + (!st.frozen ? "on" : "") + '">Bearbeiten</button>' +
      '<button class="sw ' + (st.frozen ? "on" : "") + '"><span class="k"></span></button>' +
      '<button class="lab ' + (st.frozen ? "on" : "") + '">Sichern</button></div>');
    var labs = toggle.querySelectorAll(".lab");
    labs[0].addEventListener("click", function () { st.frozen = false; pushFrozen(); render(); });
    labs[1].addEventListener("click", function () { st.frozen = true; pushFrozen(); render(); });
    toggle.querySelector(".sw").addEventListener("click", function () { st.frozen = !st.frozen; pushFrozen(); render(); });
    footInEl.appendChild(timer);
    footInEl.appendChild(toggle);
    footEl.style.display = "";
  }

  /* Timer-Tick (LK OFF steuert lokal + pusht; SuS zählen synchron mit) */
  setInterval(function () {
    if (!st.timerRunning || st.timerSeconds <= 0) { return; }
    st.timerSeconds -= 1;
    if (BOOT.view === "lk_off") {
      var v = footInEl.querySelector(".tio-timer .val");
      if (v) v.textContent = fmt(st.timerSeconds);
      if (st.timerSeconds === 0) { pushTimer(); render(); }
    } else if (BOOT.view === "sus") {
      var sv = footInEl.querySelector(".tio-stud-timer .t");
      if (sv) sv.textContent = fmt(st.timerSeconds);
      if (st.timerSeconds === 0) render();
    }
  }, 1000);

  /* ══════════════════════════════════════════════════════════
     ARBEITEN (SuS)
     ══════════════════════════════════════════════════════════ */
  function buildStudent() {
    if (!st.activityReady) {
      var g = el('<div class="tio-stud"></div>');
      g.appendChild(buildGame());
      stageEl.appendChild(g);
      footEl.style.display = "none";
      return;
    }
    // Schueler sehen nur das ERGEBNIS (eigene Gruppe/Sozialform), nicht das interaktive
    // Gruppentool. LK-Aenderungen kommen weiterhin per SSE ueber liveGroups() an.

    // SuS: Abfrage-Formular
    if ((BOOT.state && BOOT.state.tool) === 'abfrage') {
      var abfSus = buildAbfrageSus();
      stageEl.appendChild(abfSus);
      footEl.style.display = "none";
      return;
    }

    var stage = el('<div class="tio-stud"><span class="tio-stud-chip">' + esc(toolPlaceholder()) + '</span></div>');
    stageEl.appendChild(stage);
    // Student-Footer: Sozialform + Teilnehmer der eigenen Gruppe — aus dem gespeicherten
    // Zustand abgeleitet, damit LK-Änderungen (per SSE) hier in Echtzeit ankommen.
    var soc = liveSocial();
    var socIcon = socialIcon(soc);
    var me = BOOT.me || (PARTICIPANTS[0] && PARTICIPANTS[0].name) || "";
    var meid = (typeof BOOT.meid !== "undefined") ? BOOT.meid : null;
    var groupsNow = liveGroups();
    var myGroup = null;
    var myGroupIndex = -1;
    for (var i = 0; i < groupsNow.length; i++) {
      var g = groupsNow[i];
      if (meid != null && (g.studentids || []).indexOf(meid) >= 0) {
        myGroup = g;
        myGroupIndex = i;
        break;
      }
      if ((g.students || []).indexOf(me) >= 0) {
        myGroup = g;
        myGroupIndex = i;
        break;
      }
    }
    if ((BOOT.state && BOOT.state.tool) === 'board' && myGroup) {
      // Persistentes Board (ueberlebt render()): der Lehrer-Lock wird per postMessage
      // ohne iframe-Reload umgeschaltet -> "Sichern" laedt nicht neu und loescht nichts.
      susBoardApply(myGroup, myGroupIndex);
    } else {
      susBoardRemove();
    }
    var members = (myGroup && myGroup.students) || [me];
    var label = (soc === "group" && myGroup && myGroup.name)
      ? (myGroup.name + ": " + members.join(", "))
      : members.join(", ");
    footInEl.innerHTML = "";
    footInEl.appendChild(el('<div class="tio-stud-foot"><span class="ico">' + socIcon + '</span>' +
      '<span style="width:1px;height:14px;background:#cbd5e1"></span>' +
      '<span class="lbl" title="' + esc(label) + '">' + esc(label) + '</span></div>'));
    if (st.timerRunning) {
      footInEl.appendChild(el('<div class="tio-stud-timer"><span class="ic">' + iconSvg("clock") + '</span>' +
        '<span class="t">' + fmt(st.timerSeconds) + '</span></div>'));
    } else {
      footInEl.appendChild(el('<span></span>'));
    }
    footEl.style.display = "";
  }

  /* Warte-Minispiel (Endless-Runner mit Pixel-Hamster) */
  var HAM = {
    a:["......#.#.","..######..",".########.","##########","##########","########x.","##########",".########.",".#.##.#..."],
    b:["......#.#.","..######..",".########.","##########","##########","########x.","##########",".########.","..#.##.#.."]
  };
  function buildGame() {
    var wrap = el('<div class="tio-game"><div class="tio-game-sky"></div>' +
      '<div class="tio-game-hud"><span class="sc">Score 0</span><span>Leertaste / Tippen = Springen</span></div></div>');
    var sky = wrap.querySelector(".tio-game-sky");
    var scEl = wrap.querySelector(".sc");
    [[8,12],[16,26],[30,10],[44,20],[58,8],[70,24],[82,14],[90,30],[24,34],[64,36]].forEach(function (s) {
      var star = el('<span class="tio-star"></span>');
      star.style.left = s[0] + "%"; star.style.top = s[1] + "%"; sky.appendChild(star);
    });
    sky.appendChild(el('<div class="tio-ground"></div>'));
    var wheel = el('<div class="tio-wheel"></div>'); sky.appendChild(wheel);
    for (var i = 0; i < 6; i++) { var sp = el('<span></span>'); sp.style.transform = "translate(-50%,0) rotate(" + (i * 60) + "deg)"; wheel.appendChild(sp); }
    var ham = el('<div class="tio-ham"></div>'); sky.appendChild(ham);

    var G = { over:false, jump:-1, score:0, obst:[], spawn:30, gap:18, spin:0, frame:0, spd:2.0 };
    var ARC = [0,4,8,12,16,19,21,22,22,21,19,16,13,10,7,4,2,0];
    var running = true;

    function drawHam(rows) {
      ham.innerHTML = "";
      var w = 7, hgt = 15, bottom = 20, py = G.jump >= 0 ? ARC[G.jump] : 0;
      ham.style.left = "24%"; ham.style.width = w + "%"; ham.style.height = hgt + "%";
      ham.style.bottom = (bottom + py) + "%";
      var cols = rows[0].length, cw = 100 / cols, ch = 100 / rows.length;
      rows.forEach(function (row, y) {
        for (var x = 0; x < row.length; x++) {
          var c = row[x];
          if (c === "#" || c === "x") {
            var px = el('<span class="tio-px"></span>');
            px.style.left = (x * cw) + "%"; px.style.top = (y * ch) + "%";
            px.style.width = cw + "%"; px.style.height = ch + "%";
            px.style.background = c === "x" ? "#0f172a" : "#f8fafc";
            ham.appendChild(px);
          }
        }
      });
    }
    function jump() { if (G.jump === -1 && !G.over) G.jump = 0; }
    function reset() { G.over = false; G.jump = -1; G.score = 0; G.obst = []; G.spawn = 30; G.gap = 18; running = true; loop(); }

    function loop() {
      if (over) return;
      G.frame = G.frame ? 0 : 1;
      G.score += 1; scEl.textContent = "Score " + G.score;
      G.spd = 2.0 + Math.min(4.5, G.score * 0.0018);
      // Sprung fortschreiten
      if (G.jump >= 0) { G.jump += 1; if (G.jump >= ARC.length) G.jump = -1; }
      // Hindernisse
      G.spawn -= 1;
      if (G.spawn <= 0) { G.obst.push({ x:100, w:3, h:8 + Math.random() * 8 }); G.spawn = 40 + Math.floor(Math.random() * 40); }
      G.obst.forEach(function (o) { o.x -= G.spd; });
      G.obst = G.obst.filter(function (o) { return o.x > -6; });
      // Rad + Kollision
      G.spin += 12; wheel.style.transform = "rotate(" + G.spin + "deg)";
      var playerY = G.jump >= 0 ? ARC[G.jump] : 0;
      var hit = false;
      G.obst.forEach(function (o) {
        if (o.x < 31 && o.x + o.w > 24 && playerY < o.h) hit = true;
      });
      if (hit) { G.gap -= 7; } else { G.gap = Math.min(30, G.gap + 0.5); }
      if (G.gap <= 0) { gameOver(); return; }
      paint();
    }
    var over = false;
    function paint() {
      drawHam(G.frame ? HAM.a : HAM.b);
      // Rad
      var wpx = 60;
      wheel.style.width = wpx + "px"; wheel.style.height = wpx + "px";
      wheel.style.left = "calc(" + (24 - G.gap) + "% - " + wpx + "px)";
      wheel.style.bottom = "20%";
      // Hindernisse zeichnen
      Array.prototype.slice.call(sky.querySelectorAll(".tio-obst")).forEach(function (n) { n.remove(); });
      G.obst.forEach(function (o) {
        var ob = el('<div class="tio-obst"></div>');
        ob.style.left = o.x + "%"; ob.style.bottom = "20%";
        ob.style.width = o.w + "%"; ob.style.height = o.h + "%"; sky.appendChild(ob);
      });
    }
    function gameOver() {
      over = true; running = false;
      var go = el('<div class="tio-game-over"><div>Game Over · Score ' + G.score + '</div>' +
        '<button type="button">Nochmal</button></div>');
      go.querySelector("button").addEventListener("click", function () { go.remove(); over = false; reset(); });
      sky.appendChild(go);
    }

    var ticker = setInterval(function () { if (running && !over) loop(); }, 40);
    function onKey(e) { if (e.code === "Space" || e.code === "ArrowUp") { e.preventDefault(); if (over) return; jump(); } }
    window.addEventListener("keydown", onKey);
    sky.addEventListener("click", function () { if (!over) jump(); });
    wrap._cleanup = function () { clearInterval(ticker); window.removeEventListener("keydown", onKey); };
    drawHam(HAM.a); paint();
    return wrap;
  }

  /* ══════════════════════════════════════════════════════════
     Render-Weiche
     ══════════════════════════════════════════════════════════ */
  function render() {
    stopGlobe();
    var oldGame = stageEl.querySelector(".tio-game");
    if (oldGame && oldGame._cleanup) oldGame._cleanup();
    // Persistentes SuS-Board bewahren: NICHT via innerHTML loeschen, sonst wird das iframe
    // neu geladen. Bei gleichzeitigem Reload aller Peers ginge die Board-Szene verloren.
    var _keepBoard = (susBoard && susBoard.host) || null;
    var _kids = Array.prototype.slice.call(stageEl.childNodes);
    for (var _ki = 0; _ki < _kids.length; _ki++) {
      if (_kids[_ki] !== _keepBoard) { stageEl.removeChild(_kids[_ki]); }
    }
    footInEl.innerHTML = "";
    footEl.style.display = "none";

    if (BOOT.view === "lk_on") {
      if (st.preparing) { stageEl.appendChild(buildPrepare()); }
      else { buildGlobe(); }
      buildCreateFooter();
    } else if (BOOT.view === "lk_off") {
      buildLive();
    } else {
      buildStudent();
    }

    renderDebugJson(); // DEBUG: State-JSON live (alle Views)
  }

  // LK ON mit bereits freigegebenem Zyklus → direkt in Vorbereiten (Schritt 3) fortsetzen.
  if (BOOT.view === "lk_on" && BOOT.state) {
    var s = BOOT.state;
    var toolBack = { none:"none", board:"board", chatbot:"chat", abfrage:"poll", gruppen:"none" };
    var socBack = { einzel:"individual", paar:"partner", gruppe:"group", plenum:"group" };
    st.materials = (s.materials && s.materials.slice()) || [];
    st.preparing = { id:s.methodid || "no-template", label:s.methodlabel || "Ohne Vorlage", summary:s.methodsummary || "", kind:"method" };
    st.prepTool = toolBack[s.tool] || "none";
    st.prepSocial = socBack[s.sozialform] || "individual";
    st.prepStep = 3;
  }

  connectRealtime();
  render();
}());
</script>
<?php
echo $OUTPUT->footer();
