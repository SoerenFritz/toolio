<!-- =============================================================
     GENERATED FILE — DO NOT EDIT.
     Quelle: toolio-infra/governance/CONTRIBUTING.md
     Aendern nur in der Quelle, dann governance/sync-governance.ps1 ausfuehren.
     ============================================================= -->

# Beitragen zu Toolio (CONTRIBUTING)

Diese Datei richtet sich an **Menschen**. Für die Arbeitsweise von KI-Agenten siehe
[`AGENTS.md`](AGENTS.md), für die verbindlichen technischen Regeln
[`.github/copilot-instructions.md`](.github/copilot-instructions.md), für Philosophie und
Denkweise das [Engineering Charter](https://github.com/Toolio-Moodle-Plugin/toolio-infra/blob/main/docs/00-engineering-charter.md).

> **Diese Datei wird aus `toolio-infra/governance/CONTRIBUTING.md` erzeugt.** Nicht in Kopien editieren.

---

## Repository-Landschaft

Toolio ist **kein Monorepo**. Es besteht aus mehreren eigenständigen Repositories:

| Repo | Inhalt |
|---|---|
| `toolio-infra` | Dokumentation, Governance-Quelle, Deploy-Infrastruktur |
| `mod_toolio` | Aktivitätsplugin (Produktion) |
| `block_toolio` | Sidebar-Block (Produktion) |
| `mod_kollabboard`, `mod_kichatbot`, `mod_abfragetool`, `mod_bewertung` | Prototyp-Sandkästen einzelner Werkzeuge |
| `format_tiles` | Kursformat (externer Ursprung) |

Die Governance-Dateien (`copilot-instructions.md`, `AGENTS.md`, `CONTRIBUTING.md`) werden
**zentral** in `toolio-infra/governance/` gepflegt und in alle Repos gespiegelt
(→ `governance/sync-governance.ps1`). **Kopien nie direkt editieren.**

---

## Grundprinzip: erst lokal, dann pushen

```
Ändern → lokal in Docker testen → erst wenn es läuft: pushen
```

Ein Push auf `main` **deployt automatisch**. Ein defektes Plugin blockiert **alle**
Plugin-Upgrades auf dem Server. Deshalb: niemals ungetestet pushen.
Lokales Setup: [docs/04-umsetzung/04-lokale-entwicklung-docker.md](https://github.com/Toolio-Moodle-Plugin/toolio-infra/blob/main/docs/04-umsetzung/04-lokale-entwicklung-docker.md).

---

## Branch- & PR-Strategie

- `main` ist immer deploybar.
- Arbeit in Feature-Branches: `feat/…`, `fix/…`, `docs/…`, `chore/…`.
- Pull Request gegen `main`; mindestens ein Review vor Merge.
- Kein Merge, solange die Selbstprüfung gegen die Hard Rules nicht besteht.

## Commit-Konventionen

[Conventional Commits](https://www.conventionalcommits.org/):
`feat:`, `fix:`, `docs:`, `chore:`, `refactor:`, `test:`. Ein Commit = ein Anliegen.

## Code Review

- Erhält die Änderung die konzeptionelle Integrität (Vision → Terminologie → Architektur)?
- Ist die Doku nachgezogen?
- Bestehen die Moodle Hard Rules?
- Wurde eine Architekturentscheidung getroffen, die eine ADR bräuchte?

---

## Architekturentscheidungen (ADR)

Architektur wird nie stillschweigend im Code entschieden. Neue verbindliche Entscheidungen
entstehen als **ADR** in [docs/adr](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/adr):

1. Kopiere `docs/adr/0000-template.md` → nächste Nummer.
2. Beschreibe Kontext, Optionen, Entscheidung, Konsequenzen.
3. Merge der ADR **vor** oder **mit** der Implementierung.
4. Übernimm die Entscheidung anschließend in die betroffene Doku / Hard Rules.

---

## Release-Prozess

- Version in `version.php` (`$plugin->version`, Format `YYYYMMDDNN`) erhöhen, sobald sich
  `db/install.xml` oder `db/upgrade.php` ändert.
- `$plugin->requires` = Moodle-5.1.0-Stamp `2025100600`.
- Push auf `main` → GitHub Actions → rsync → `deploy.sh` → `upgrade.php`.
