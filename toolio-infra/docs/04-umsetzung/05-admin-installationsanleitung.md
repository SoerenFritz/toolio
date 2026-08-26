# Admin-Installationsanleitung — Toolio

> **Kurzfassung für Moodle-Administrator:innen.** Die **vollständige, gepflegte Anleitung
> reist mit dem Plugin** und liegt als `README.md` im Repo **`mod_toolio`**. Diese Seite
> hier ist nur der interne Einstieg/Verweis.

Auf einer produktiven Moodle-Instanz werden am Ende **nur zwei** Plugins installiert:

| Plugin | Rolle | Pflicht? |
|---|---|---|
| **`mod_toolio`** | Kern-Aktivität (Monolith) mit allen Werkzeugen | **ja** |
| **`block_toolio`** | Sidebar zur Steuerung im Kurs | optional |

> Perspektivisch können beide zu **einem** Plugin zusammengeführt werden. Alle übrigen
> Repos sind **Prototyp-Sandkästen** und gehören **nicht** auf Produktion
> (siehe [Repos & Zusammenführung](01-repos-und-zusammenfuehrung.md)).

## Installation in Kürze

1. `mod_toolio` nach `<moodle>/mod/toolio/` (Ordner **exakt** `toolio`).
2. Optional `block_toolio` nach `<moodle>/blocks/toolio/`.
3. **Website-Administration → Benachrichtigungen** → Installation/Upgrade bestätigen.
4. In einem Kurs **Aktivität → Toolio** anlegen.

Die ausführliche Schritt-für-Schritt-Fassung (inkl. ZIP-Upload, Ordner-Namensregel,
Entwicklungsstand) steht in der **Plugin-README von `mod_toolio`**.

## Board-Werkzeug (zusätzliche Server-Dienste)

Das kollaborative **Board** benötigt — anders als die übrigen Werkzeuge — zusätzliche
Server-Dienste (Excalidraw-Frontend + WebSocket-Room-Server in Docker) und eine
Reverse-Proxy-Konfiguration. Dieser Teil gehört zum Board-Plugin `mod_kollabboard` und ist
**separat** dokumentiert — dort steht eine **vollständige Schritt-für-Schritt-Anleitung**
(von Docker-Setup bis Moodle-Anbindung):

➡️ **[3 · Board-Deployment → „Schritt-für-Schritt: Board-Server aufsetzen"](03-board-deployment.md#schritt-für-schritt-board-server-aufsetzen)**

> Wer Toolio **ohne** Board betreibt, braucht **keine** Docker-Dienste — dann genügen die
> vier Schritte oben.

## Verwandte Kapitel

- [1 · Repos & Zusammenführung](01-repos-und-zusammenfuehrung.md) — warum am Ende nur zwei Plugins.
- [2 · Interne Struktur von `mod_toolio`](02-mod-toolio-struktur.md) — wie die Werkzeuge im Monolith liegen.
- [3 · Board-Deployment](03-board-deployment.md) — Board-Server & Excalidraw.
- [4 · Lokal entwickeln mit Docker](04-lokale-entwicklung-docker.md) — Testumgebung fürs Team.
