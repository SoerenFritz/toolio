<?php
/**
 * KI-Chatbot - Aktivitäts-Hauptseite.
 *
 * - Backend-URL aus Admin-Einstellungen (settings.php).
 * - Rolle (LK / SuS) via Moodle-Capabilities - kein Rollenumschalter nötig.
 * - Alle DOM-IDs entsprechen Bot.html, damit script.js ohne strukturelle
 *   Änderungen funktioniert.
 */
require('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('kichatbot', $id, 0, false, MUST_EXIST);
$course   = get_course($cm->course);
$instance = $DB->get_record('kichatbot', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context   = context_module::instance($cm->id);
$isteacher = has_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/mod/kichatbot/view.php', ['id' => $id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_secondary_navigation(false);
$PAGE->activityheader->disable();

// API-Key und Basis-URL aus den Plugin-Einstellungen
$openaikey  = (string)get_config('mod_kichatbot', 'openaikey');
$apibaseurl = rtrim((string)get_config('mod_kichatbot', 'apibaseurl'), '/');
if ($apibaseurl === '') {
    $apibaseurl = 'https://api.openai.com/v1';
}
$apimodel = (string)get_config('mod_kichatbot', 'apimodel');
if ($apimodel === '') {
    $apimodel = 'google/gemini-2.5-flash:free';
}

echo $OUTPUT->header();
?>
<style>
:root {
    --primary:#f27b13; --primary-light:#fef4ec; --bg:#f8fafc; --sidebar-bg:#fff;
    --card-bg:#fff; --border:#e2e8f0; --text-main:#0f172a; --text-muted:#64748b;
    --teacher:#10b981; --radius:16px;
    --shadow-sm:0 2px 4px rgba(0,0,0,.02);
    --shadow-md:0 12px 24px -4px rgba(15,23,42,.04);
    --transition:all .25s ease;
}
/* Wrapper - passt sich in Moodle-Layout ein */
.kicb-wrap {
    display:flex; height:calc(100vh - 140px); min-height:500px; overflow:hidden;
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
    color:var(--text-main); border:1px solid var(--border);
    border-radius:var(--radius); background:#fff;
}
/* Klassen-Namen wie in Bot.html damit script.js nichts finden muss */
.sidebar {
    width:240px; background:var(--sidebar-bg); border-right:1px solid var(--border);
    padding:28px 20px; display:flex; flex-direction:column; gap:28px;
    box-sizing:border-box; flex-shrink:0;
}
.sidebar h2 {
    font-size:.7rem; text-transform:uppercase; letter-spacing:.08em;
    color:var(--text-muted); margin:0 0 12px; font-weight:700;
}
.btn {
    padding:12px 16px; border:1px solid var(--border); border-radius:var(--radius);
    cursor:pointer; font-weight:500; font-size:.9rem; transition:var(--transition);
    text-align:left; background:#fff; color:var(--text-main); width:100%;
    margin-bottom:8px; display:flex; align-items:center; gap:8px;
    box-shadow:var(--shadow-sm);
}
.btn:hover { background:var(--bg); transform:translateY(-1px); }
.mode-btn.active {
    background:var(--primary-light); font-weight:600;
    border-color:var(--primary); color:var(--primary);
}
.main-chat {
    flex:1; display:flex; flex-direction:column; background:#fff; min-width:0;
}
.chat-header {
    padding:20px 32px; border-bottom:1px solid var(--border);
    display:flex; justify-content:space-between; align-items:center;
}
.chat-header h3 { margin:0; font-size:1rem; font-weight:600; }
.chat-messages {
    flex:1; padding:32px; overflow-y:auto; display:flex;
    flex-direction:column; gap:20px; background:#fafafa;
}
.message {
    max-width:72%; padding:14px 18px; border-radius:var(--radius);
    line-height:1.6; font-size:.93rem; box-shadow:var(--shadow-sm);
}
.message.user {
    background:var(--text-main); color:#fff; align-self:flex-end;
    border-bottom-right-radius:4px;
}
.message.bot {
    background:#fff; color:var(--text-main); align-self:flex-start;
    border-bottom-left-radius:4px; border:1px solid var(--border);
}
.suggested-prompts {
    display:flex; gap:8px; padding:12px 32px; overflow-x:auto;
    background:#fff; border-top:1px solid var(--border);
}
.prompt-chip {
    background:#fff; border:1px solid var(--border); padding:8px 16px;
    border-radius:30px; font-size:.82rem; cursor:pointer; white-space:nowrap;
    transition:var(--transition); color:var(--text-muted); font-weight:500;
}
.prompt-chip:hover { background:var(--bg); color:var(--primary); border-color:var(--primary); }
.chat-input-area {
    padding:16px 32px 24px; border-top:1px solid var(--border);
    display:flex; gap:12px; background:#fff;
}
.chat-input-area input {
    flex:1; padding:12px 16px; border:1px solid var(--border);
    border-radius:var(--radius); font-size:.95rem; outline:none;
    transition:var(--transition); background:var(--bg);
}
.chat-input-area input:focus { border-color:var(--primary); background:#fff; }
/* Rechte LK-Sidebar */
.teacher-tools {
    width:320px; border-left:1px solid var(--border); background:var(--bg);
    padding:24px 20px; display:flex; flex-direction:column; gap:20px;
    overflow-y:auto; box-sizing:border-box; flex-shrink:0;
}
.teacher-tools h3 { margin:0 0 4px; font-size:1rem; font-weight:700; }
.tool-card {
    background:var(--card-bg); padding:20px; border-radius:var(--radius);
    border:1px solid var(--border); box-shadow:var(--shadow-md);
}
.tool-card h4 { margin:0 0 8px; font-size:.88rem; font-weight:600; }
.tool-card p { font-size:.78rem; color:var(--text-muted); margin:0 0 8px; }
.tool-card select,
.tool-card input[type="file"] {
    width:100%; padding:8px 10px; border-radius:8px; border:1px solid var(--border);
    font-family:inherit; font-size:.85rem; background:#fff;
    margin-bottom:8px; box-sizing:border-box;
}
.hidden { display:none !important; }
</style>

<div class="kicb-wrap">

    <!-- Linke Sidebar: Modus-Wahl (Rollenumschalter entfaellt in Moodle) -->
    <div class="sidebar">
        <div>
            <h2>Chat-Modus</h2>
            <div class="chat-modes">
                <button class="btn mode-btn active" id="btn-single"
                        onclick="switchMode('single')">Einzelchat (KI)</button>
                <button class="btn mode-btn" id="btn-group"
                        onclick="switchMode('group')">Gruppenchat</button>
            </div>
        </div>
    </div>

    <!-- Haupt-Chat (DOM-IDs wie Bot.html) -->
    <div class="main-chat">
        <div class="chat-header">
            <h3 id="chat-title">KI-Lernassistent</h3>
            <span id="role-badge"
                  style="background:var(--bg);border:1px solid var(--border);
                         padding:5px 14px;border-radius:20px;font-size:.78rem;
                         font-weight:600;">
                <?php echo $isteacher ? 'Lehrkraft' : s(fullname($USER)); ?>
            </span>
        </div>
        <div class="chat-messages" id="chat-messages">
            <div class="message bot">
                <?php if ($isteacher): ?>
Hallo! Lade rechts eine Datei hoch, um mich mit Unterrichtsmaterial zu versorgen.
                <?php else: ?>
Hallo! Ich bin dein KI-Lernassistent. Sobald deine Lehrkraft mich mit einer Datei versorgt hat, helfe ich dir beim Lernen!
                <?php endif; ?>
            </div>
        </div>
        <div class="suggested-prompts" id="suggested-prompts" style="display:none">
            <div class="prompt-chip"
                 onclick="usePrompt('Erklaere mir das Thema noch einmal vereinfacht.')">
                Thema vereinfachen
            </div>
            <div class="prompt-chip"
                 onclick="usePrompt('Stimmt das wirklich, was du behauptet hast?')">
                Faktencheck
            </div>
            <div class="prompt-chip"
                 onclick="usePrompt('Fasse das bisher Besprochene zusammen.')">
                Zusammenfassung
            </div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="user-input"
                   placeholder="Nachricht eingeben ..."
                   onkeypress="handleKeyPress(event)">
            <button class="btn" onclick="sendMessage()"
                    style="background:var(--text-main);color:#fff;width:auto;
                           margin:0;font-weight:600;padding:12px 22px;">
                Senden
            </button>
        </div>
    </div>

    <!-- Rechte Sidebar: nur fuer Lehrkraefte (DOM-IDs wie Bot.html) -->
    <div class="teacher-tools<?php echo $isteacher ? '' : ' hidden'; ?>"
         id="teacher-sidebar">
        <h3>LK-Dashboard</h3>

        <div class="tool-card" style="border-top:4px solid var(--primary);">
            <h4>Bot-Rolle</h4>
            <select id="bot-role-select" onchange="changeBotRole(this.value)">
                <option value="standard">Standard (Fakten-Bot)</option>
                <option value="lerncoach">Lerncoach (sokratisch)</option>
                <option value="einfach">Einfache Sprache</option>
            </select>
        </div>

        <div class="tool-card" style="border-top:4px solid var(--teacher);">
            <h4>Material hochladen</h4>
            <p style="font-size:.78rem;color:var(--text-muted);margin:0 0 8px;">TXT oder PDF (kein Scan) wird im Browser gelesen.</p>
            <form id="uploadForm" onsubmit="uploadTeacherFile(event)">
                <input type="file" id="teacher-file" name="file"
                       accept=".pdf,.txt" required>
                <button type="submit" id="upload-submit-btn" class="btn"
                        style="background:var(--teacher);color:#fff;border:none;
                               font-weight:600;margin:4px 0 0;">
                    Datei laden
                </button>
            </form>
            <p id="kicb-upload-status" style="font-size:.75rem;margin:6px 0 0;"></p>
        </div>

        <div class="tool-card">
            <h4>Werkzeuge</h4>
            <button class="btn" onclick="triggerTool('ergebnissicherung')">
                Protokoll erstellen
            </button>
            <button class="btn" onclick="triggerTool('lernzieltracker')">
                Lernziele
            </button>
            <button class="btn" onclick="triggerTool('livequiz')">
                Quiz starten
            </button>
        </div>
    </div>

</div>

<script>
/* Moodle-injizierte Konfiguration */
window.CHATBOT_IS_TEACHER = <?php echo $isteacher ? 'true' : 'false'; ?>;
window.CHATBOT_API_KEY    = <?php echo json_encode($openaikey); ?>;
window.CHATBOT_API_BASE   = <?php echo json_encode($apibaseurl); ?>;
window.CHATBOT_API_MODEL  = <?php echo json_encode($apimodel); ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous"></script>
<script src="<?php echo (new moodle_url('/mod/kichatbot/script.js'))->out(false); ?>"></script>
<script>
/* Initialen Rollenstatus setzen (ersetzt onclick-Klick aus Standalone-Prototyp) */
if (typeof currentRole !== 'undefined') {
    currentRole = window.CHATBOT_IS_TEACHER ? 'teacher' : 'student';
}
</script>

<?php echo $OUTPUT->footer(); ?>
