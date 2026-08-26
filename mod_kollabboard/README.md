# KollabBoard-Plugin – Aktueller Entwicklungsstand

*Letzte Aktualisierung: 24. August 2026*

---

## 🎯 Projektziel

Integration eines kollaborativen Whiteboards (basierend auf Excalidraw) in Moodle mit Echtzeit-Funktionalität. Jede KollabBoard-Aktivität bietet einen eigenen, sicheren Raum für die Zusammenarbeit, auf den alle Teilnehmer gleichzeitig zugreifen und in Echtzeit zusammenarbeiten können. Das Plugin nutzt Ende-zu-Ende-Verschlüsselung für alle Board-Inhalte und unterstützt gruppenspezifische Boards.

---

## ✅ Abgeschlossene Arbeiten

### Plugin-Umbenennung und -Migration
- **Plugin-Name geändert**: von `mod_whiteboard` zu `mod_kollabboard`
- **Component-Name**: `mod_kollabboard` in allen Dateien aktualisiert
- **Version**: 2026072902 (Moodle 5.1.0+ erforderlich)
- **Maturity**: ALPHA

### Architektur und Sicherheit
1. **Deterministische Raum-ID-Generierung**
   - Raum-IDs werden deterministisch aus Course-Module-ID und Gruppen-ID abgeleitet
   - HMAC-basierte Generierung mit Server-Geheimnis (`roomsecret`)
   - Funktion: `kollabboard_get_room($cmid, $groupid)` in `lib.php`

2. **Ende-zu-Ende-Verschlüsselung**
   - Alle Board-Inhalte (Szene-Daten und Dateien) werden client-seitig verschlüsselt
   - Server speichert nur opake, verschlüsselte Blobs
   - 128-Bit-Schlüssel als Base64URL (22 Zeichen) im Excalidraw-Format

3. **Storage-Endpoint** (`storage.php`)
   - Unauthentifizierter HTTP-Endpoint für Excalidraw
   - CORS-konfigurierbar für Cross-Origin-Zugriff
   - Unterstützt GET/PUT/PATCH für:
     - Szenen-Daten (`/api/v2/rooms/:roomid`)
     - Datei-Metadaten (`/api/v2/files/rooms/:roomid/:fileid/timestamp`)
     - Datei-Inhalte (`/api/v2/files/rooms/:roomid/:fileid`)
   - Größenlimit: 25 MB pro Blob
   - Sicherheitsmodell: Nur registrierte Räume werden bedient

4. **Raum-Registrierung**
   - Räume werden automatisch in `view.php` registriert
   - Funktion: `kollabboard_register_room($roomid, $kollabboardid, $groupid)`
   - Verhindert das Anlegen beliebiger Räume durch unauthentifizierte Aufrufe

### Datenbank-Struktur
Drei Tabellen in `db/install.xml` (Version 2026072902):

1. **`kollabboard`** (Hauptinstanz-Tabelle)
   - `id`, `course`, `name`, `intro`, `introformat`
   - `timemodified`, `timecreated`

2. **`kollabboard_boards`** (Board-Szenen)
   - `id`, `roomid` (HMAC-abgeleitet, 64 Zeichen)
   - `kollabboardid` (FK zu kollabboard.id)
   - `groupid` (0 = gemeinsames Board für alle)
   - `sceneversion`, `sceneblob` (Base64, verschlüsselt)
   - `savedby`, `timecreated`, `timemodified`

3. **`kollabboard_files`** (Hochgeladene Dateien)
   - `id`, `roomid`, `fileid` (128 Zeichen)
   - `filedata` (Base64, verschlüsselt)
   - `timecreated`, `timemodified`

### Plugin-Dateien und Struktur

```
mod_kollabboard/
├── classes/
│   └── event/
│       └── course_module_viewed.php    # Event-Handler
├── db/
│   ├── access.php                      # Capabilities (kollabboard:addinstance, kollabboard:view)
│   └── install.xml                     # Datenbank-Schema (3 Tabellen)
├── lang/
│   ├── de/
│   │   ├── whiteboard.php              # Altes Plugin (veraltet)
│   │   └── local_whiteboard.php        # Lokale Übersetzungen
│   └── en/
│       ├── kollabboard.php             # Englische Sprachstrings
│       ├── whiteboard.php              # Altes Plugin (veraltet)
│       └── local_whiteboard.php        # Lokale Übersetzungen
├── .github/
│   ├── copilot-instructions.md          # Entwicklungsrichtlinien
│   └── workflows/
│       └── deploy.yml                  # Automatisches Deployment
├── .gitattributes                       # Git-Konfiguration
├── .gitignore                          # Ignorierte Dateien
├── AGENTS.md                           # Agenten-Anweisungen (generiert)
├── CONTRIBUTING.md                     # Beitragsrichtlinien (generiert)
├── index.php                           # Modul-Index (Weiterleitung)
├── lib.php                             # Kernfunktionen (Raum-Generierung, Registrierung)
├── mod_form.php                        # Formular für Aktivitätserstellung
├── plugin.yml                          # Plugin-Metadaten
├── settings.php                        # Admin-Einstellungen (Board-URL)
├── storage.php                         # HTTP-Storage-Endpoint für Excalidraw
├── style.css                           # CSS-Stile
├── version.php                         # Plugin-Version (2026072902)
└── view.php                            # Hauptansicht (iframe-Einbettung)
```

### Implementierte Funktionen

#### In `lib.php`:
- `kollabboard_get_room($cmid, $groupid)` – Generiert deterministische Raum-ID und Schlüssel
- `kollabboard_register_room($roomid, $kollabboardid, $groupid)` – Registriert Raum in DB
- `whiteboard_add_instance($data)` – Erstellt neue Instanz (veraltet, für whiteboard)
- `whiteboard_update_instance($data)` – Aktualisiert Instanz (veraltet)
- `whiteboard_delete_instance($id)` – Löscht Instanz (veraltet)
- `whiteboard_supports($feature)` – Feature-Unterstützung
- `whiteboard_generate_room_id()` – 12-stellige alphanumerische ID (veraltet)
- `whiteboard_generate_encryption_key()` – 22-stellige Verschlüsselungs-Schlüssel (veraltet)

#### In `storage.php`:
- HTTP-Endpoint für Excalidraw-Speicherung
- CORS-Unterstützung
- Räume, Dateien und Szenen-Verwaltung
- Größenlimits und Validierung

#### In `view.php`:
- iframe-Einbettung des Excalidraw-Boards
- Dynamische URL-Generierung mit:
  - Benutzername aus Moodle (`fullname($USER)`)
  - Raum-ID und Schlüssel als URL-Fragment (`#room={roomid},{roomkey}`)
  - Board-URL aus Plugin-Einstellungen
- Automatische Raum-Registrierung
- Gruppenunterstützung via `groups_get_activity_group()`

#### In `settings.php`:
- Admin-Einstellung für `boardurl` (Basis-URL des Excalidraw-Boards)

---

## 📋 Aktueller Stand und bekannte Einschränkungen

### Implementiert und funktionierend:
1. ✅ Plugin-Struktur mit allen Moodle-Pflichtdateien
2. ✅ Datenbank-Schema mit 3 Tabellen
3. ✅ Deterministische Raum-Generierung (HMAC-basiert)
4. ✅ Ende-zu-Ende-Verschlüsselung für alle Inhalte
5. ✅ Storage-Endpoint für Excalidraw
6. ✅ iframe-Einbettung in Moodle
7. ✅ Gruppenunterstützung
8. ✅ Admin-Einstellungen für Board-URL
9. ✅ Event-Handling (course_module_viewed)
10. ✅ Berechtigungssystem (Capabilities)
11. ✅ Sprachdateien (Englisch, Deutsch teilweise)
12. ✅ GitHub Actions Deployment-Pipeline

### ✅ Gelöste Probleme

#### WebSocket-Verbindung im iframe (GELÖST)
**Lösung:**
- Storage-Endpoint (`storage.php`) als HTTP-Backend für Excalidraw
- CORS-konfigurierbar für Cross-Origin-Kommunikation
- Räume werden deterministisch generiert und registriert
- Keine WebSocket-Blockade mehr, da der Storage-Endpoint auf derselben Domain läuft

#### Datenbank: Falsche Instanz-IDs (GELÖST)
**Lösung:**
- Neue Tabellenstruktur mit `kollabboard_boards` und `kollabboard_files`
- Deterministische Raum-ID-Generierung basierend auf cmid und groupid
- Jede Aktivität erhält automatisch eindeutige Raum-IDs
- Gruppenunterstützung: Unterschiedliche Räume pro Gruppe möglich

---

## 🛠️ Offene Punkte / Verbesserungspotenzial

### Priorität 1 (Kritisch):
1. **Merge-Konflikte in view.php auflösen**
   - Datei enthält ungelöste Git-Merge-Konflikte (Zeilen 79-124)
   - Gemischte Referenzen zu whiteboard und kollabboard
   - **Empfehlung:** Konflikte auflösen und auf kollabboard bereinigen

2. **Veraltete whiteboard-Referenzen migrieren**
   - `lib.php`: Enthält sowohl `kollabboard_*`- als auch `whiteboard_*`-Funktionen
   - `mod_form.php`: Klassenname ist noch `mod_whiteboard_mod_form`
   - `view.php`: Enthält Referenzen zu `$whiteboard` statt `$kollabboard`
   - `access.php`: Enthält doppelte Capability-Definitionen (sowohl whiteboard als auch kollabboard)
   - **Empfehlung:** Komplette Bereinigung aller Dateien auf `kollabboard`

3. **Fehlende deutsche Sprachdatei**
   - `lang/de/kollabboard.php` fehlt (nur `lang/en/kollabboard.php` vorhanden)
   - **Empfehlung:** Deutsche Übersetzungen für kollabboard erstellen

### Priorität 2 (Wichtig):
1. **access.php bereinigen**
   - Doppelte Capability-Definitionen entfernen
   - Nur kollabboard-Capabilities behalten

2. **mod_form.php Klassenname aktualisieren**
   - Auf `mod_kollabboard_mod_form` ändern

3. **style.css mit Inhalten füllen**
   - CSS-Stile für iframe-Einbettung definieren

4. **AGENTS.md erstellen oder Referenz entfernen**
   - Wird in CONTRIBUTING.md referenziert, aber nicht gefunden

### Priorität 3 (Verbesserung):
1. **Dokumentation für Production-Deployment erstellen**
2. **Spezifischere Fehlermeldungen im Storage-Endpoint**
3. **Unit-Tests erstellen**
4. **Backup/Restore-Funktionalität testen**

---

## 🚀 Deployment

### Voraussetzungen
- Moodle 5.1.0 oder höher
- PHP 8.1+
- Excalidraw-kompatibler Frontend-Service (z.B. selbstgehosteter Fork)

### Installation
1. Plugin in `moodle/mod/kollabboard` ablegen
2. Moodle-Upgrade ausführen (Datenbank-Tabellen werden erstellt)
3. In Admin-Einstellungen:
   - `boardurl` auf die Basis-URL des Excalidraw-Boards setzen (z.B. `https://board.example.org`)

### Konfiguration

#### Moodle-Einstellungen:
- **Site Administration → Plugins → Activities → KollabBoard**
  - `Board-URL`: Basis-URL des Excalidraw-Boards (z.B. `https://board.example.org`)

#### Excalidraw-Frontend:
- Muss den Storage-Endpoint aufrufen können:
  - Szenen: `GET/PUT /mod/kollabboard/storage.php/api/v2/rooms/{roomid}`
  - Dateien: `GET/PUT /mod/kollabboard/storage.php/api/v2/files/rooms/{roomid}/{fileid}`

### CORS-Konfiguration
Der Storage-Endpoint erlaubt standardmäßig Cross-Origin-Anfragen von der in `boardurl` konfigurierten Domain. Stellen Sie sicher, dass:
1. Die Board-URL korrekt konfiguriert ist
2. Der Excalidraw-Service auf derselben Domain oder einer erlaubten Cross-Origin-Domain läuft

---

## 📊 Dateien-Übersicht

| Datei | Zweck | Status |
|-------|-------|--------|
| `version.php` | Plugin-Version (2026072902) | ✅ Aktuell |
| `plugin.yml` | Plugin-Metadaten | ✅ Aktuell |
| `lib.php` | Kernfunktionen | ⚠️ Enthält veraltete whiteboard-Funktionen |
| `view.php` | Hauptansicht | ⚠️ Merge-Konflikte |
| `mod_form.php` | Formular | ⚠️ Klassenname noch whiteboard |
| `storage.php` | HTTP-Storage | ✅ Funktionierend |
| `settings.php` | Admin-Einstellungen | ✅ Funktionierend |
| `index.php` | Modul-Index | ✅ Funktionierend |
| `style.css` | Stile | ⚠️ Inhalt fehlt |
| `db/install.xml` | Datenbank | ✅ Aktuell (3 Tabellen) |
| `db/access.php` | Berechtigungen | ⚠️ Doppelte Definitionen |
| `classes/event/course_module_viewed.php` | Event-Handler | ✅ Funktionierend |
| `lang/en/kollabboard.php` | Englisch | ✅ Aktuell |
| `lang/de/kollabboard.php` | Deutsch | ❌ Fehlt |
| `.github/workflows/deploy.yml` | Deployment | ✅ Konfiguriert |

---

## 🎯 Nächste Schritte

### Priorität 1 (Kritisch):
1. **Merge-Konflikte in view.php auflösen**
2. **Alle whiteboard-Referenzen zu kollabboard migrieren**
3. **Deutsche Sprachdatei erstellen** (`lang/de/kollabboard.php`)

### Priorität 2 (Wichtig):
1. **access.php bereinigen** (doppelte Capability-Definitionen entfernen)
2. **mod_form.php Klassenname aktualisieren** (auf `mod_kollabboard_mod_form`)
3. **style.css mit Inhalten füllen**
4. **AGENTS.md erstellen oder Referenz entfernen**

### Priorität 3 (Verbesserung):
1. **Dokumentation für Production-Deployment erstellen**
2. **Spezifischere Fehlermeldungen im Storage-Endpoint**
3. **Unit-Tests erstellen**
4. **Backup/Restore-Funktionalität testen**

---

## 📞 Unterstützung

Bei Fragen oder Problemen:
- Projekt-Repository prüfen
- CONTRIBUTING.md für Beitragsrichtlinien lesen
- copilot-instructions.md für Entwicklungsrichtlinien prüfen

---

*Dokumentation erstellt am 24. August 2026*


