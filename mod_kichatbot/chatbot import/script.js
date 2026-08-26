let currentRole = 'teacher';
let currentMode = 'single';
let participantId = null;

const API_BASE = `http://${window.location.hostname}:8000`;

// LEHRERBEREICH NUR MIT PASSWORT
const TEACHER_PASSWORD = "moodle";

function attemptTeacherAccess() {
    const input = prompt("Bitte Passwort für den Lehrerbereich eingeben:");

    if (input === TEACHER_PASSWORD) {
        switchRole('teacher');
    } else {
        if (input !== null) {
            alert("⚠️ Falsches Passwort.");
        }
        switchRole('student');
    }
}

// ROLLE WECHSELN
// Steuert jetzt zwei komplett getrennte Ansichten:
// - #student-view: der Chat
// - #teacher-view: das Lehrer-Dashboard (kein Chatfenster)
function switchRole(role) {
    currentRole = role;

    const studentView = document.getElementById('student-view');
    const teacherView = document.getElementById('teacher-view');
    const badge = document.getElementById('role-badge');
    const teacherButton = document.getElementById('btn-teacher');
    const studentButton = document.getElementById('btn-student');

    if (role === 'teacher') {
        if (studentView) studentView.classList.add('hidden');
        if (teacherView) teacherView.classList.remove('hidden');

        if (badge) {
            badge.textContent = "Lehrkraft";
            badge.classList.add('role-badge-teacher');
        }

        if (teacherButton) teacherButton.style.display = 'none';
        if (studentButton) studentButton.style.display = 'inline-flex';

    } else {
        if (studentView) studentView.classList.remove('hidden');
        if (teacherView) teacherView.classList.add('hidden');

        if (badge) {
            badge.textContent = "Schüler";
            badge.classList.remove('role-badge-teacher');
        }

        if (teacherButton) teacherButton.style.display = 'inline-flex';
        if (studentButton) studentButton.style.display = 'none';
    }
}

async function changeChatMode(mode) {
    try {
        const response = await fetch(
            `${API_BASE}/api/teacher/set-mode`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ mode: mode })
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || "Chatmodus konnte nicht geändert werden.");
        }

        currentMode = data.mode;

        updateChatModeDisplay();

    } catch (error) {
        console.error("CHATMODUS FEHLER:", error);
        alert("Chatmodus konnte nicht geändert werden.");
    }
}


async function loadChatMode() {
    try {
        const response = await fetch(
            `${API_BASE}/api/chat/mode`
        );

        if (!response.ok) {
            throw new Error("Chatmodus konnte nicht geladen werden.");
        }

        const data = await response.json();

        currentMode = data.mode;

        updateChatModeDisplay();

        if (currentMode === 'group') {
            await joinGroupChat();
            startGroupChatPolling();
        }

    } catch (error) {
        console.error("CHATMODUS LADEN FEHLER:", error);
    }
}


function updateChatModeDisplay() {
    const display =
        document.getElementById('current-mode-display');

    const title =
        document.getElementById('chat-title');

    if (display) {
        display.textContent =
            currentMode === 'group'
                ? 'Gruppenchat'
                : 'Einzelchat';
    }

    if (title) {
        title.textContent =
            currentMode === 'group'
                ? 'Gruppenchat'
                : 'KI-Lernchat';
    }
}

function switchToStudent() {
    switchRole('student');
    loadChatMode();
    updateChatModeDisplay();
}

function switchMode(mode) {
    currentMode = mode;

    document.getElementById('btn-single').classList.remove('active');
    document.getElementById('btn-group').classList.remove('active');

    const title = document.getElementById('chat-title');

    if (mode === 'single') {
        document.getElementById('btn-single').classList.add('active');

        if (title) {
            title.textContent = "KI-Lernchat";
        }

        stopGroupChatPolling();
    }

    if (mode === 'group') {
        document.getElementById('btn-group').classList.add('active');

        if (title) {
            title.textContent = "Gruppenchat";
        }

        joinGroupChat();
        startGroupChatPolling();
    }
}

async function joinGroupChat() {
    try {
        const response = await fetch(
            `${API_BASE}/api/chat/join`,
            {
                method: 'POST'
            }
        );

        if (!response.ok) {
            throw new Error(
                "Beitritt zum Gruppenchat fehlgeschlagen."
            );
        }

        const data = await response.json();

        participantId = data.participant_id;

        console.log(
            "GRUPPENCHAT TEILNEHMER:",
            participantId
        );

    } catch (error) {
        console.error(
            "GRUPPENCHAT BEITRITT FEHLER:",
            error
        );
    }
}

let groupChatPolling = null;
let groupChatMessageCount = 0;
let groupChatInitialized = false;

function startGroupChatPolling() {
    stopGroupChatPolling();

    groupChatMessageCount = 0;
    groupChatInitialized = false;

    loadGroupChat();

    groupChatPolling = setInterval(() => {
        loadGroupChat();
    }, 2000);
}

function stopGroupChatPolling() {
    if (groupChatPolling) {
        clearInterval(groupChatPolling);
        groupChatPolling = null;
    }
}

async function loadGroupChat() {
    if (currentMode !== 'group') return;

    try {
        const response = await fetch(
            `${API_BASE}/api/chat/group`
        );

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (!data.chat_history) {
            return;
        }

        const container = document.getElementById('chat-messages');

        if (!container) {
            return;
        }

        if (!groupChatInitialized) {
            container.innerHTML = '';

            data.chat_history.forEach(message => {
                const sender =
                    message.role === 'user'
                        ? 'user'
                        : 'bot';

                appendMessage(
                    message.content,
                    sender
                );
            });

            groupChatMessageCount =
                data.chat_history.length;

            groupChatInitialized = true;

            return;
        }

        if (
            data.chat_history.length <=
            groupChatMessageCount
        ) {
            return;
        }

        const newMessages =
            data.chat_history.slice(
                groupChatMessageCount
            );

        newMessages.forEach(message => {
            const sender =
                message.role === 'user'
                    ? 'user'
                    : 'bot';

            appendMessage(
                message.content,
                sender
            );
        });

        groupChatMessageCount =
            data.chat_history.length;

    } catch (error) {
        console.error(
            "GRUPPENCHAT FEHLER:",
            error
        );
    }
}

// BOT-ROLLE ÄNDERN
async function changeBotRole(selectedRole) {
    try {
        console.log("BOT ROLLE REQUEST START");

        const response = await fetch(
            `${API_BASE}/api/teacher/set-role`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    role: selectedRole
                })
            }
        );

        console.log(
            "BOT ROLLE RESPONSE:",
            response.status
        );

        const data = await response.json();

        alert(data.message);

    } catch (e) {
        console.error(
            "BOT ROLLE FEHLER:",
            e
        );

        alert(
            "Fehler beim Ändern der Bot-Rolle!"
        );
    }
}


// DATEI UPLOAD
async function uploadTeacherFile(event) {
    event.preventDefault();

    const form = document.getElementById('uploadForm');
    const button = document.getElementById('upload-submit-btn');
    const originalText = button.innerHTML;

    button.innerHTML =
        "Datei wird verarbeitet...";

    button.disabled = true;
    button.style.opacity = "0.7";

    const formData = new FormData(form);

    try {
        console.log("TEST A - Upload startet");

        const response = await fetch(
            `${API_BASE}/api/teacher/upload-file`,
            {
                method: 'POST',
                body: formData
            }
        );

        console.log("TEST B - Antwort erhalten");
        console.log("HTTP STATUS:", response.status);

        const data = await response.json();

        console.log("SERVER DATEN:", data);

        if (data.status === "success") {
            alert(data.message);
        } else {
            alert(data.message);
        }

    } catch (e) {
        console.error(
            "UPLOAD FEHLER:",
            e
        );

        alert(
            "Fehler beim Senden der Datei!"
        );

    } finally {
        button.innerHTML = originalText;
        button.disabled = false;
        button.style.opacity = "1";
    }
}


// TOOLS
async function triggerTool(toolName, buttonEl) {

    const originalLabel = buttonEl ? buttonEl.innerHTML : null;

    if (buttonEl) {
        buttonEl.disabled = true;
        buttonEl.innerHTML = "Wird erstellt...";
    }

    try {

        console.log(
            "TOOL REQUEST:",
            toolName
        );

        // =====================================================
        // WORD-DOWNLOADS
        // =====================================================

        if (
            toolName === "handout" ||
            toolName === "livequiz"
        ) {

            const response = await fetch(
                `${API_BASE}/api/tools/${toolName}`,
                {
                    method: "POST"
                }
            );

            if (!response.ok) {
                throw new Error(
                    "Download fehlgeschlagen."
                );
            }

            const blob = await response.blob();

            const url =
                window.URL.createObjectURL(blob);

            const link =
                document.createElement("a");

            link.href = url;

            link.download =
                toolName === "handout"
                    ? "Handout.docx"
                    : "Live-Quiz.docx";

            document.body.appendChild(link);

            link.click();

            link.remove();

            window.URL.revokeObjectURL(url);

            return;
        }

        // =====================================================
        // ALLE ANDEREN TOOLS
        // =====================================================

        const response = await fetch(
            `${API_BASE}/api/tools/${toolName}`,
            {
                method: "POST"
            }
        );

        const data = await response.json();

        // =====================================================
        // LERNZIELTRACKER IM DASHBOARD ANZEIGEN
        // =====================================================

        if (toolName === "lernzieltracker") {

            document.getElementById(
                "learning-goal-result"
            ).style.display = "block";

            const contentEl = document.getElementById(
                "learning-goal-content"
            );

            contentEl.innerHTML = "";

            data.result
                .split("\n")
                .map(line => line.trim())
                .filter(line => line.length > 0)
                .forEach(line => {
                    const lineEl = document.createElement("div");
                    lineEl.className = "goal-line";
                    lineEl.textContent = line;
                    contentEl.appendChild(lineEl);
                });

            document.getElementById(
                "download-learning-goals"
            ).style.display = "block";

            return;
        }

        // =====================================================
        // ALLE ANDEREN TOOLS
        // =====================================================

        alert(
            `🤖 KI generiert für ${toolName.toUpperCase()}:\n\n${data.result}`
        );

    } catch (error) {

        console.error(error);

        alert(
            "Fehler: Python-Backend antwortet nicht."
        );

    } finally {

        if (buttonEl) {
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalLabel;
        }
    }
}

// CHAT
async function sendMessage() {
    const input = document.getElementById('user-input');

    if (!input || !input.value.trim()) return;

    const text = input.value.trim();

    input.value = '';

    if (currentMode !== 'group') {
        appendMessage(text, 'user');
    }

    try {
        console.log("CHAT REQUEST START");

        const response = await fetch(
            `${API_BASE}/api/chat`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: text,
                    mode: currentMode
                })
            }
        );

        console.log("CHAT RESPONSE:", response.status);

        const data = await response.json();

        if (currentMode !== 'group') {
            setTimeout(() => {
                appendMessage(data.response, 'bot');
            }, 400);
        }

    } catch (error) {
        console.error("CHAT FEHLER:", error);

        if (currentMode !== 'group') {
            appendMessage(
                '⚠️ Verbindung zum Python-Backend fehlgeschlagen.',
                'bot'
            );
        }
    }
}

// CHAT MESSAGE ANZEIGEN
function appendMessage(text, sender) {
    const container = document.getElementById('chat-messages');

    if (!container) return;

    const msgDiv = document.createElement('div');

    msgDiv.classList.add('message', sender);

    let name = '';

    if (sender === 'user') {
        const match = text.match(/^Schüler \d+:\s*/);

        if (match) {
            name = match[0].replace(':', '').trim();
            text = text.replace(match[0], '');
        } else {
            name = 'Schüler';
        }
    } else {
        name = 'KI-Lernassistent';
    }

    const nameDiv = document.createElement('div');
    nameDiv.classList.add('message-name');
    nameDiv.textContent = name;

    msgDiv.appendChild(nameDiv);

    const textDiv = document.createElement('div');
    textDiv.innerHTML = text.replace(/\n/g, '');

    msgDiv.appendChild(textDiv);

    container.appendChild(msgDiv);

    container.scrollTop = container.scrollHeight;
}

// ENTER
function handleKeyPress(e) {
    if (e.key === 'Enter') {
        sendMessage();
    }
}


// PROMPTS
function usePrompt(promptText) {
    const input =
        document.getElementById('user-input');

    if (input) {
        input.value = promptText;
        sendMessage();
    }
}


// LERNZIELTRACKER ALS WORD HERUNTERLADEN
async function downloadLearningGoals() {

    try {

        const response = await fetch(
            `${API_BASE}/api/tools/lernzieltracker/download`,
            {
                method: "POST"
            }
        );

        if (!response.ok) {
            throw new Error(
                "Download fehlgeschlagen."
            );
        }

        const blob =
            await response.blob();

        const url =
            window.URL.createObjectURL(blob);

        const link =
            document.createElement("a");

        link.href = url;
        link.download = "Lernzieltracker.docx";

        document.body.appendChild(link);

        link.click();

        link.remove();

        window.URL.revokeObjectURL(url);

    } catch (error) {

        console.error(error);

        alert(
            "Der Lernzieltracker konnte nicht heruntergeladen werden."
        );
    }
}

attemptTeacherAccess();
loadChatMode();

async function resetChat() {
    const confirmReset = confirm(
        "Möchten Sie wirklich eine neue Unterrichtseinheit starten?\n\nChat, Material und Einstellungen werden für alle Schüler zurückgesetzt."
    );

    if (!confirmReset) {
        return;
    }

    try {
        const response = await fetch(
            `${API_BASE}/api/chat/reset`,
            {
                method: "POST"
            }
        );

        if (!response.ok) {
            const text = await response.text();
            throw new Error(
                `Server antwortet mit ${response.status}: ${text}`
            );
        }

        const data = await response.json();

        const container =
            document.getElementById("chat-messages");

        if (container) {
            container.innerHTML = "";
        }

        const fileInput = document.getElementById("teacher-file");
        if (fileInput) fileInput.value = "";

        const goalResult = document.getElementById("learning-goal-result");
        if (goalResult) goalResult.style.display = "none";

        alert("Neue Unterrichtseinheit gestartet.");

    } catch (error) {
        console.error("RESET FEHLER:", error);

        alert(
            "Reset fehlgeschlagen:\n\n" +
            error.message
        );
    }
}