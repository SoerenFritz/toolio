# AGENTS.md — Arbeitsweise für KI-Agenten in Toolio

Diese Datei gilt für **alle KI-Agenten** (GitHub Copilot, Claude Code, Codex, Aider …),
die in einem Toolio-Repository arbeiten. Sie beschreibt **wie** gearbeitet wird.

Abgrenzung (Single Source of Truth):
- **Welche** technischen Regeln gelten → [`.github/copilot-instructions.md`](.github/copilot-instructions.md)
- **Warum** Toolio so gedacht ist → [Engineering Charter](https://github.com/Toolio-Moodle-Plugin/toolio-infra/blob/main/docs/00-engineering-charter.md)
- **Wie** Menschen beitragen → [`CONTRIBUTING.md`](CONTRIBUTING.md)

> **Diese Datei wird aus `toolio-infra/governance/AGENTS.md` erzeugt.** Nicht in Kopien editieren.

---

## Grundhaltung

- **Die Dokumentation ist die Spezifikation.** Code folgt der Doku — nicht umgekehrt.
- **Triff niemals unbegründete Annahmen.** Fehlt Information: Lücke markieren, Optionen
  nennen, Empfehlung geben — aber **nie stillschweigend** entscheiden.
- **Erfinde keine Architektur.** Offene Entscheidungen werden als **ADR** sichtbar gemacht,
  nicht im Code versteckt.
- Weicht Code von der Doku ab, gilt die **Doku** als Referenz, bis eine neue ADR es ändert.

---

## Prioritäten (höhere schlägt niedrigere)

1. Vision erhalten
2. Mentales Modell erhalten
3. Terminologie erhalten
4. Architektur erhalten
5. Dokumentation aktuell halten
6. Code schreiben

Eine niedrigere Priorität darf eine höhere **niemals** verletzen.

---

## Arbeitsablauf vor jeder Code-Änderung

```
Kontext lesen
      ↓
ADRs prüfen
      ↓
Offene Fragen markieren
      ↓
Implementieren
      ↓
Selbstprüfung (Hard Rules)
      ↓
Doku / ADR aktualisieren
```

1. **Kontext lesen** — das passende Kapitel gemäß Tabelle unten. Verstehe Vision und
   mentales Modell, bevor du Code anfasst.
2. **ADRs prüfen** — existiert eine Entscheidung zum Thema? Dann daran halten. Existiert
   keine, aber die Änderung setzt eine Architekturentscheidung voraus → als **offene ADR**
   markieren, nicht selbst entscheiden.
3. **Offene Fragen markieren** — unklare Punkte explizit benennen (Optionen + Empfehlung).
4. **Implementieren** — minimal und der Doku folgend. Kein Scope-Creep.
5. **Selbstprüfung** — gegen die Moodle Hard Rules in `.github/copilot-instructions.md`
   (Short-Name-Regeln, Sprachdateien, `lib.php`, `install.xml`, `version.php`).
6. **Doku/ADR aktualisieren** — Änderung an Verhalten oder Architektur = Doku nachziehen.

---

## Welche Doku vor welcher Änderung

| Änderung betrifft … | Lies zuerst |
|---|---|
| Vision, Didaktik, Bedienkonzept | [docs/01-konzept](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/01-konzept) |
| Architektur, Realtime, Datenhaltung | [docs/02-architektur](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/02-architektur) |
| Einzelnes Werkzeug | [docs/03-werkzeuge](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/03-werkzeuge) |
| Setup, Deployment, lokale Entwicklung | [docs/04-umsetzung](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/04-umsetzung) |
| Architekturentscheidungen | [docs/adr](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/adr) |

---

## Terminologie & Hard Rules

Nicht hier wiederholt — es gilt ausschließlich die Fassung in
[`.github/copilot-instructions.md`](.github/copilot-instructions.md) (Abschnitt 12 Terminologie,
Abschnitte 1–11 Hard Rules).

---

## Sicherheit & Umkehrbarkeit

- Lokale, umkehrbare Aktionen (Dateien ändern, Tests) frei ausführen.
- Schwer umkehrbare Aktionen (Push → Auto-Deploy, DB-Änderungen, Löschen) erst nach
  Bestätigung. **Ein Push auf `main` deployt automatisch** und ein defektes Plugin
  blockiert alle Upgrades (→ Hard Rules).
