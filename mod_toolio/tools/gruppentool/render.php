<?php
namespace mod_toolio\tool;

defined('MOODLE_INTERNAL') || die();

/**
 * Gruppentool — vertikaler Durchstich (Weg A), jetzt mit echter Persistenz (ADR-0003).
 *
 * Die Lehrkraft (🟢 LK ON) teilt Sozialform + Gruppen ein und gibt frei; der Zustand
 * wird als versionierter JSON-Blob am Standard-Zyklus gespeichert
 * (toolio_gruppentool_state). 🔵 LK OFF und 🟡 Schüler lesen exakt diesen Stand.
 *
 * NOCH provisorisch: die Namensliste (Roster) ist Demo-Material. Der Abgleich echter
 * Moodle-Teilnehmender ↔ Gruppenmitglied (Enrolment-Import) und Realtime (SSE) folgen
 * als eigene Schritte. Der gespeicherte *Zustand* ist bereits echt (DB, rollenübergreifend).
 *
 * Drei Ansichten über die zentrale Modus-Ermittlung:
 *  🟢 LK ON  — Sozialform & Gruppen konfigurieren, freigeben (schreibt in die DB)
 *  🔵 LK OFF — freigegebene Gruppen read-only + Online-Status beobachten
 *  🟡 Schüler — die eigene Gruppe sehen
 *
 * @param array $ctx ['view','username','state','cmid','saveurl','sesskey', ...]
 */
function render_gruppentool(array $ctx): void {
    $view     = $ctx['view'];
    $username = $ctx['username'] ?? 'Ich';

    if ($view === \mod_toolio\view_mode::SUS) {
        render_gruppentool_sus($ctx, $username);
        return;
    }
    render_gruppentool_teacher($ctx, $username);
}

/**
 * Lehrkraft-Ansichten (LK ON = konfigurieren & freigeben, LK OFF = beobachten).
 *
 * @param array $ctx
 * @param string $username
 */
function render_gruppentool_teacher(array $ctx, string $username): void {
    $view  = $ctx['view'];
    $ison  = ($view === \mod_toolio\view_mode::LK_ON);
    $state = $ctx['state'] ?? null;

    $methodlabel = trim((string) ($ctx['method'] ?? ''));
    if ($methodlabel === '' && !empty($state['methodlabel'])) {
        $methodlabel = (string) $state['methodlabel'];
    }
    if ($methodlabel === '') {
        $methodlabel = 'Ohne Vorlage';
    }

    $toolnames = [
        'none' => 'Kein Tool',
        'gruppen' => 'Gruppentool',
        'board' => 'Board',
        'chatbot' => 'KI-Chatbot',
        'abfrage' => 'Abfrage',
    ];

    $materials = [];
    if (!empty($state['materials']) && is_array($state['materials'])) {
        $materials = array_values(array_filter(array_map('strval', $state['materials']), static function($v) {
            return $v !== '';
        }));
    }

    $activeTool = (string) ($state['tool'] ?? 'gruppen');
    if (!array_key_exists($activeTool, $toolnames)) {
        $activeTool = 'gruppen';
    }

    $social = (string) ($state['sozialform'] ?? 'gruppe');
    if (!in_array($social, ['einzel', 'paar', 'gruppe'], true)) {
        $social = 'gruppe';
    }

    $count = (int) ($state['count'] ?? 4);
    if ($count < 2) {
        $count = 2;
    }
    if ($count > 8) {
        $count = 8;
    }

    if (!$ison) {
        // 🔵 LK OFF — nur beobachten, was freigegeben wurde.
        ?>
        <h1 class="tio-h1">👥 Gruppentool</h1>
        <p class="tio-sub">Beobachten — freigegebene Gruppen &amp; Online-Status</p>
        <?php
        if (!$state) {
            echo \mod_toolio\ui::wait('👥', 'Noch nicht freigegeben',
                'Die Lehrkraft teilt die Gruppen gerade ein.');
            return;
        }
        echo '<div class="tio-section">';
        echo '<div class="tio-provisional" style="background:#f6f9ff;border-style:solid;border-color:#d7e3ff;color:#2d4c83">';
        echo '<strong>Methode:</strong> ' . s((string) ($state['methodlabel'] ?? 'Ohne Vorlage')) . '<br>';
        echo '<strong>Werkzeug:</strong> ' . s($toolnames[(string) ($state['tool'] ?? 'gruppen')] ?? 'Gruppentool') . '<br>';
        echo '<strong>Sozialform:</strong> ' . s((string) ($state['sozialform'] ?? 'gruppe'));
        if (!empty($state['materials']) && is_array($state['materials'])) {
            echo '<br><strong>Material-Dock:</strong> ' . s(implode(' | ', $state['materials']));
        }
        echo '</div>';
        echo '</div>';
        echo '<div class="tio-section">';
        render_gruppentool_groups($state, $username, true);
        echo '</div>';
        return;
    }

    // 🟢 LK ON — konfigurieren.
    $saveurl = $ctx['saveurl'] ?? '';
    $sesskey = $ctx['sesskey'] ?? '';
    $cmid    = (int) ($ctx['cmid'] ?? 0);
    ?>
    <h1 class="tio-h1">👥 Gruppentool</h1>
    <p class="tio-sub">Drei-Takt vorbereiten: Material-Dock, Werkzeug, Sozialform</p>

    <?php echo \mod_toolio\ui::breadcrumb($ctx['backurl'] ?? '#', ['Material', 'Werkzeug', 'Sozialform', 'Gruppen einteilen'], 1, $ctx['tokens']); ?>

    <style>
    .gt-pane { display:none; }
    .gt-pane.active { display:block; }
    .gt-material-row { display:flex; gap:8px; flex-wrap:wrap; }
    .gt-material-row input { flex:1 1 280px; min-width: 220px; border:1.5px solid #d9dee7; border-radius:8px; padding:8px 10px; }
    .gt-material-list { margin-top:10px; display:grid; gap:8px; }
    .gt-material-item {
        border:1.5px solid #e6eaf2; border-radius:8px; padding:8px 10px; background:#fff;
        display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:.84rem;
    }
    .gt-chip-row { display:flex; flex-wrap:wrap; gap:8px; }
    .gt-chip {
        border:1.5px solid #d7dbe3; border-radius:999px; background:#fff; color:#556070;
        padding:6px 12px; font-size:.8rem; font-weight:700; cursor:pointer;
    }
    .gt-chip.active { border-color: <?php echo $ctx['tokens']['accent']; ?>; background: <?php echo $ctx['tokens']['accentbg']; ?>; color: <?php echo $ctx['tokens']['accent']; ?>; }
    </style>

    <div class="gt-pane active" id="gt-pane-1">
        <div class="tio-section">
            <div class="tio-label">Methode</div>
            <div class="tio-provisional" style="background:#f7faf7;border-style:solid;border-color:#d8e9d8;color:#2f5131">
                <?php echo s($methodlabel); ?>
            </div>
        </div>
        <div class="tio-section">
            <div class="tio-label">Material-Dock</div>
            <div class="gt-material-row">
                <input id="gt-material-input" type="text" placeholder="Material anhaengen (z.B. PDF: Fallstudie.pdf)">
                <button type="button" class="tio-btn tio-btn-ghost" id="gt-material-add">Hinzufuegen</button>
                <button type="button" class="tio-btn tio-btn-ghost" id="gt-material-debug">Debug-Datei</button>
            </div>
            <div class="gt-material-list" id="gt-material-list"></div>
        </div>
    </div>

    <div class="gt-pane" id="gt-pane-2">
        <div class="tio-section">
            <div class="tio-label">Aktives Werkzeug fuer den Zyklus</div>
            <div class="gt-chip-row" id="gt-tool-row">
                <button type="button" class="gt-chip" data-tool="none">Kein Tool</button>
                <button type="button" class="gt-chip" data-tool="gruppen">Gruppentool</button>
                <button type="button" class="gt-chip" data-tool="board">Board</button>
                <button type="button" class="gt-chip" data-tool="chatbot">KI-Chatbot</button>
                <button type="button" class="gt-chip" data-tool="abfrage">Abfrage</button>
            </div>
            <div class="tio-provisional" style="margin-top:10px;">
                Toolio bleibt monolithisch: der Einstieg erfolgt ueber Methoden, freigegeben wird
                ein klarer naechster Arbeitsschritt fuer SuS.
            </div>
        </div>
    </div>

    <div class="gt-pane" id="gt-pane-3">
        <div class="tio-section">
            <div class="tio-label">Sozialform</div>
            <div class="tio-seg" id="gt-seg">
                <button data-form="einzel">Einzel</button>
                <button data-form="paar">Paar</button>
                <button data-form="gruppe" class="active">Gruppe</button>
            </div>
        </div>
        <div class="tio-actions" style="padding-top:16px;justify-content:flex-start;margin-top:8px;">
            <button type="button" class="tio-btn" onclick="gtSetStep(4)">Weiter &rsaquo;</button>
        </div>
    </div>

    <div class="gt-pane" id="gt-pane-4">
        <div class="tio-section" id="gt-count-row">
            <div class="tio-label">Anzahl Gruppen</div>
            <div class="tio-step">
                <button type="button" id="gt-minus" aria-label="weniger">−</button>
                <span class="tio-val" id="gt-count">4</span>
                <button type="button" id="gt-plus" aria-label="mehr">+</button>
                <button type="button" class="tio-btn tio-btn-ghost" id="gt-shuffle" style="margin-left:16px;">Zufaellig einteilen</button>
                <button type="button" class="tio-btn tio-btn-ghost" id="gt-import" disabled title="folgt">Moodle-Gruppen importieren</button>
            </div>
        </div>

        <div class="tio-section">
            <div class="tio-label">Vorschau</div>
            <div class="tio-groups" id="gt-groups"></div>
        </div>
    </div>

    <div class="tio-section">
        <div class="tio-provisional">
            Die Namensliste ist noch Demo-Material (echter Teilnehmenden-Import folgt).
            Die <strong>Freigabe wird gespeichert</strong> und von LK OFF/SuS exakt so gelesen.
        </div>
    </div>

    <div class="tio-actions">
        <button type="button" class="tio-btn" id="gt-release"><?php
            echo $state ? 'Erneut freigeben >' : 'Fuer SuS freigeben >'; ?></button>
        <span class="tio-save-note" id="gt-save-note"><?php
            echo $state ? 'Zuletzt freigegeben (v' . (int) ($state['version'] ?? 1) . ')' : ''; ?></span>
    </div>

    <script>
    (function () {
        var me       = <?php echo json_encode($username); ?>;
        var saveurl  = <?php echo json_encode($saveurl); ?>;
        var sesskey  = <?php echo json_encode($sesskey); ?>;
        var cmid     = <?php echo $cmid; ?>;
        var initial  = <?php echo json_encode($state); ?>;
        var methodLabel = <?php echo json_encode($methodlabel); ?>;
        var methodId = methodLabel.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        if (!methodId) { methodId = 'ohne-vorlage'; }
        var toolNames = <?php echo json_encode($toolnames); ?>;
        var debugPool = [
            'Debug-Datei: Beispielauftrag.pdf',
            'Debug-Datei: Kriterienraster.docx',
            'Debug-Datei: Datensatz_Umsatz.csv'
        ];

        var defaultRoster = ['Mia','Ben','Lena','Jonas','Emma','Luca','Sofia','Finn','Hanna','Paul','Ida','Noah','Lara','Tim','Eva','Max'];
        var roster = defaultRoster.slice();
        var form   = <?php echo json_encode($social); ?>;
        var count  = <?php echo (int) $count; ?>;
        var activeTool = <?php echo json_encode($activeTool); ?>;
        var materials = <?php echo json_encode($materials); ?>;
        var lastGroups = [];

        // Gespeicherten Stand als Ausgangspunkt übernehmen.
        if (initial && initial.groups) {
            form = initial.sozialform || form;
            count = initial.count || count;
            activeTool = initial.tool || activeTool;
            materials = Array.isArray(initial.materials) ? initial.materials.slice() : materials;
            roster = [];
            initial.groups.forEach(function (g) {
                (g.members || []).forEach(function (m) { roster.push(m); });
            });
            if (!roster.length) { roster = defaultRoster.slice(); }
        }

        function buildModel() {
            var groups = [];
            if (form === 'einzel') {
                roster.forEach(function (name) {
                    if (name) { groups.push({ name: name, members: [name] }); }
                });
            } else {
                var per, n;
                if (form === 'paar') { per = 2; n = Math.ceil(roster.length / 2); }
                else { n = count; per = Math.ceil(roster.length / count); }
                for (var g = 0; g < n; g++) {
                    var members = roster.slice(g * per, g * per + per).filter(Boolean);
                    if (members.length) { groups.push({ name: 'Gruppe ' + (g + 1), members: members }); }
                }
            }
            return groups;
        }

        function renderMaterials() {
            var list = document.getElementById('gt-material-list');
            list.innerHTML = '';
            if (!materials.length) {
                var empty = document.createElement('div');
                empty.className = 'tio-save-note';
                empty.textContent = 'Noch kein Material im Dock.';
                list.appendChild(empty);
                return;
            }
            materials.forEach(function (item, idx) {
                var row = document.createElement('div');
                row.className = 'gt-material-item';
                row.innerHTML = '<span>' + item + '</span><button type="button" class="tio-btn tio-btn-ghost" data-rm="' + idx + '">Entfernen</button>';
                list.appendChild(row);
            });
        }

        function renderToolSelection() {
            var row = document.getElementById('gt-tool-row');
            Array.prototype.forEach.call(row.querySelectorAll('.gt-chip'), function (btn) {
                btn.classList.toggle('active', btn.dataset.tool === activeTool);
            });
        }

        function gtSetStep(target) {
            var step = Math.max(1, Math.min(4, target));
            // Breadcrumb-Schritte aktualisieren
            Array.prototype.forEach.call(document.querySelectorAll('#tio-bc .tio-bc-step'), function (btn) {
                var n = Number(btn.dataset.step);
                btn.classList.toggle('tio-bc-active', n === step);
                btn.classList.toggle('tio-bc-done', n < step);
                btn.classList.remove('tio-bc-active');
                btn.classList.remove('tio-bc-done');
                if (n === step) { btn.classList.add('tio-bc-active'); }
                else if (n < step) { btn.classList.add('tio-bc-done'); }
            });
            // Panes umschalten
            Array.prototype.forEach.call(document.querySelectorAll('.gt-pane'), function (pane) {
                pane.classList.toggle('active', pane.id === 'gt-pane-' + step);
            });
            // Anzahl-Zeile nur bei Gruppe sichtbar (auf Pane 4)
            if (step === 4) {
                document.getElementById('gt-count-row').style.display = (form === 'gruppe') ? '' : 'none';
            }
        }
        var setStep = gtSetStep; // Alias für Rückwärtskompatibilität

        function render() {
            lastGroups = buildModel();
            var container = document.getElementById('gt-groups');
            container.innerHTML = '';
            lastGroups.forEach(function (grp) {
                var box = document.createElement('div');
                box.className = 'tio-group';
                var h = document.createElement('h4');
                h.textContent = grp.name;
                box.appendChild(h);
                grp.members.forEach(function (name) {
                    var row = document.createElement('div');
                    row.className = 'tio-member' + (name === me ? ' tio-me' : '');
                    row.appendChild(document.createTextNode(name));
                    box.appendChild(row);
                });
                container.appendChild(box);
            });
        }

        function shuffle() {
            for (var i = roster.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var t = roster[i]; roster[i] = roster[j]; roster[j] = t;
            }
            render();
        }

        var seg = document.getElementById('gt-seg');
        seg.addEventListener('click', function (e) {
            var btn = e.target.closest('button'); if (!btn) return;
            Array.prototype.forEach.call(seg.children, function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            form = btn.dataset.form;
            document.getElementById('gt-count-row').style.display = (form === 'gruppe') ? '' : 'none';
            render();
        });
        document.getElementById('gt-plus').addEventListener('click', function () {
            if (count < 8) { count++; document.getElementById('gt-count').textContent = count; render(); }
        });
        document.getElementById('gt-minus').addEventListener('click', function () {
            if (count > 2) { count--; document.getElementById('gt-count').textContent = count; render(); }
        });
        document.getElementById('gt-shuffle').addEventListener('click', shuffle);
        document.getElementById('gt-material-add').addEventListener('click', function () {
            var input = document.getElementById('gt-material-input');
            var value = (input.value || '').trim();
            if (!value) { return; }
            if (materials.indexOf(value) === -1) {
                materials.push(value);
            }
            input.value = '';
            renderMaterials();
        });
        document.getElementById('gt-material-debug').addEventListener('click', function () {
            var item = debugPool[Math.floor(Math.random() * debugPool.length)];
            if (materials.indexOf(item) === -1) {
                materials.push(item);
            }
            renderMaterials();
        });
        document.getElementById('gt-material-list').addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-rm]');
            if (!btn) { return; }
            var idx = Number(btn.dataset.rm);
            if (!Number.isNaN(idx)) {
                materials.splice(idx, 1);
                renderMaterials();
            }
        });
        document.getElementById('gt-tool-row').addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-tool]');
            if (!btn) { return; }
            activeTool = btn.dataset.tool;
            renderToolSelection();
        });
        document.getElementById('tio-bc').addEventListener('click', function (e) {
            var btn = e.target.closest('button.tio-bc-step[data-step]');
            if (!btn) { return; }
            gtSetStep(Number(btn.dataset.step));
        });

        var releaseBtn = document.getElementById('gt-release');
        var note = document.getElementById('gt-save-note');
        releaseBtn.addEventListener('click', function () {
            releaseBtn.disabled = true;
            var original = releaseBtn.textContent;
            releaseBtn.textContent = 'Speichern…';
            var payload = {
                methodid: methodId,
                methodlabel: methodLabel,
                methodsummary: '',
                materials: materials,
                tool: activeTool,
                sozialform: form,
                count: count,
                groups: lastGroups
            };
            fetch(saveurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    id: cmid, tool: 'gruppen', sesskey: sesskey,
                    payload: JSON.stringify(payload)
                })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res && res.ok) {
                    releaseBtn.textContent = 'Freigegeben ✓';
                    note.textContent = 'Gespeichert (v' + res.version + ')';
                } else {
                    releaseBtn.disabled = false;
                    releaseBtn.textContent = original;
                    note.textContent = 'Speichern fehlgeschlagen.';
                }
            }).catch(function () {
                releaseBtn.disabled = false;
                releaseBtn.textContent = original;
                note.textContent = 'Speichern fehlgeschlagen.';
            });
        });

        // Startzustand herstellen (Sozialform-Button + Count spiegeln).
        Array.prototype.forEach.call(seg.children, function (b) {
            b.classList.toggle('active', b.dataset.form === form);
        });
        document.getElementById('gt-count').textContent = count;
        document.getElementById('gt-count-row').style.display = (form === 'gruppe') ? '' : 'none';
        renderMaterials();
        renderToolSelection();
        gtSetStep(1);
        render();
    }());
    </script>
    <?php
}

/**
 * Schüler-Ansicht: die eigene Gruppe aus dem freigegebenen Stand.
 *
 * @param array $ctx
 * @param string $username
 */
function render_gruppentool_sus(array $ctx, string $username): void {
    $state = $ctx['state'] ?? null;
    $toolnames = [
        'none' => 'Kein Tool',
        'gruppen' => 'Gruppentool',
        'board' => 'Board',
        'chatbot' => 'KI-Chatbot',
        'abfrage' => 'Abfrage',
    ];
    ?>
    <h1 class="tio-h1">👥 Deine Gruppe</h1>
    <p class="tio-sub">Das hat deine Lehrkraft für dich eingeteilt.</p>
    <?php
    if (!$state) {
        echo \mod_toolio\ui::wait('👥', 'Gleich geht es los',
            'Deine Lehrkraft bereitet die Gruppen gerade vor.');
        return;
    }

    echo '<div class="tio-section">';
    echo '<div class="tio-provisional" style="background:#fff7ec;border-style:solid;border-color:#f0d8b1;color:#6a4a1a">';
    echo '<strong>Methode:</strong> ' . s((string) ($state['methodlabel'] ?? 'Ohne Vorlage')) . '<br>';
    echo '<strong>Naechstes Werkzeug:</strong> ' . s($toolnames[(string) ($state['tool'] ?? 'gruppen')] ?? 'Gruppentool') . '<br>';
    echo '<strong>Sozialform:</strong> ' . s((string) ($state['sozialform'] ?? 'gruppe'));
    if (!empty($state['materials']) && is_array($state['materials'])) {
        echo '<br><strong>Material-Dock:</strong> ' . s(implode(' | ', $state['materials']));
    }
    echo '</div>';
    echo '</div>';

    // Eigene Gruppe suchen (Namensabgleich provisorisch — echter User-Bezug folgt).
    $mine = null;
    foreach (($state['groups'] ?? []) as $g) {
        if (in_array($username, $g['members'] ?? [], true)) {
            $mine = $g;
            break;
        }
    }

    echo '<div class="tio-section">';
    if ($mine !== null) {
        render_gruppentool_groups(['groups' => [$mine]], $username, false);
    } else {
        render_gruppentool_groups($state, $username, false);
    }
    echo '</div>';
    ?>
    <div class="tio-section">
        <div class="tio-provisional">
            Die Zuordnung zu deinem Namen folgt mit dem Teilnehmenden-Import; hier siehst du
            die von der Lehrkraft freigegebene Einteilung.
        </div>
    </div>
    <?php
}

/**
 * Rendert freigegebene Gruppen read-only.
 *
 * @param array $state Payload mit 'groups'
 * @param string $me Anzeigename der aktuellen Person (Hervorhebung)
 * @param bool $showonline Online-Punkte anzeigen (nur 🔵 LK OFF, Demo-Zufall)
 */
function render_gruppentool_groups(array $state, string $me, bool $showonline): void {
    $groups = $state['groups'] ?? [];
    echo '<div class="tio-groups">';
    foreach ($groups as $g) {
        echo '<div class="tio-group">';
        echo '<h4>' . s($g['name'] ?? '') . '</h4>';
        foreach (($g['members'] ?? []) as $name) {
            $isme = ($name === $me);
            echo '<div class="tio-member' . ($isme ? ' tio-me' : '') . '">';
            if ($showonline) {
                $on = (mt_rand(0, 100) > 35) ? 'tio-on' : 'tio-off';
                echo '<span class="tio-mdot ' . $on . '"></span>';
            }
            echo s($name) . '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}
