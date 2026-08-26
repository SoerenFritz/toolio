# Toolio (`mod_toolio`)

**Toolio** schließt die Kollaborationslücke von Moodle und begleitet die **vollständige
Handlung** (Lernfelddidaktik) mit einfachen, rollenklaren Werkzeugen. Kernprinzip: ein
**Switch ON/OFF** → drei Ansichten je Werkzeug (Schüler · LK-ON · LK-OFF).

`mod_toolio` ist ein **Aktivitätsplugin (Monolith)**: Alle Werkzeuge (Gruppentool,
Chatbot, Board, Abfrage …) leben **innerhalb** dieser einen Aktivität und werden über die
Einstiegsansicht ausgewählt — kein separates Plugin je Werkzeug.

> **Produktionsumfang:** Auf einer produktiven Moodle-Instanz werden am Ende **nur zwei**
> Plugins installiert: `mod_toolio` (dieses Plugin) und optional `block_toolio` (Sidebar).
> Perspektivisch können beide zu einem zusammengeführt werden.

---

## Voraussetzungen

- **Moodle 5.1** oder neuer (`$plugin->requires = 2025100600`) — getestet auf Moodle 5.1.5+.
- Admin-Rechte in Moodle und Zugriff auf das Moodle-Verzeichnis (`<moodle>`).
- Für das **kollaborative Board**: zusätzliche Server-Dienste (Excalidraw) — siehe
  Abschnitt [Board-Werkzeug](#board-werkzeug-optional). Alle übrigen Werkzeuge laufen
  ohne Zusatzdienste allein in Moodle.

---

## Installation — Schritt für Schritt

### 1. Plugin einspielen

`mod_toolio` gehört in **`<moodle>/mod/toolio/`**.

> 🔑 **Der Ordner muss exakt `toolio` heißen** (der „Short Name"). Ein anderer Ordnername
> führt zu `detectedbrokenplugin` und **blockiert alle Plugin-Upgrades**.

```bash
cd <moodle>/mod
git clone https://github.com/Toolio-Moodle-Plugin/mod_toolio.git toolio
```

Alternativ das Plugin als **ZIP** über **Website-Administration → Plugins → Plugin
installieren** hochladen (Zielordner `toolio`).

### 2. (Optional) Sidebar-Block installieren

`block_toolio` ergänzt Toolio um eine **Sidebar** zur Steuerung im Kurs. Es ist optional —
`mod_toolio` funktioniert auch allein.

```bash
cd <moodle>/blocks
git clone https://github.com/Toolio-Moodle-Plugin/block_toolio.git toolio
```

### 3. In Moodle installieren

Als Admin **Website-Administration → Benachrichtigungen** öffnen — Moodle erkennt die neuen
Plugins und führt Installation/Upgrade durch (Datenbanktabellen werden automatisch angelegt).

### 4. Im Kurs verwenden

In einem Kurs den Bearbeitungsmodus einschalten → **Aktivität anlegen → Toolio**. Beim
Öffnen erscheint die Einstiegsansicht mit den Werkzeugen; über den **Switch ON/OFF**
wechselt die Lehrkraft zwischen den Ansichten.

---

## Board-Werkzeug (optional)

Das **kollaborative Board** benötigt — anders als die übrigen Werkzeuge — **zusätzliche
Server-Dienste** (ein Excalidraw-Frontend und einen WebSocket-Room-Server in Docker) sowie
einen Reverse Proxy mit HTTPS/WebSocket. Diese Dienste und die Board-Anbindung sind
**separat** dokumentiert (Board-Deployment) und werden über das Board-Plugin
`mod_kollabboard` bereitgestellt, das schrittweise in `mod_toolio` integriert wird.

> Wer Toolio **ohne** das Board nutzt, braucht **keine** Docker-Dienste — dann genügen die
> Schritte 1–4 oben.

---

## Entwicklungsstand

- `mod_toolio` ist der **Produktkern** (Monolith), in den die Werkzeug-Prototypen portiert
  werden. `block_toolio` liefert die optionale Sidebar.
- Das **Board** ist funktionsfähig (Live-Kollaboration + Persistenz), aktuell über
  `mod_kollabboard`; die Integration in `mod_toolio` läuft.
- Weitere Werkzeuge (Gruppentool, Chatbot, Abfrage) sind in Arbeit. **Prototyp-Plugins
  gehören nicht auf eine Produktivinstanz.**

## Lizenz

GPLv3 (Moodle-Plugin). Der Board-Server (Excalidraw / `excalidraw-room`) steht unter MIT.
