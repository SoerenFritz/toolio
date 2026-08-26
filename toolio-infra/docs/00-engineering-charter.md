# 0 · Engineering Charter — Wie Toolio gedacht wird

> Diese Datei beschreibt die **Identität und Denkweise** von Toolio. Sie ändert sich selten.
> Sie enthält **Prinzipien**, keine Domänen-Definitionen und keine Tages-Coding-Regeln.
>
> - Technische Regeln → [`.github/copilot-instructions.md`](https://github.com/Toolio-Moodle-Plugin/toolio-infra/blob/main/governance/copilot-instructions.md)
> - Arbeitsweise für KI-Agenten → [`AGENTS.md`](https://github.com/Toolio-Moodle-Plugin/toolio-infra/blob/main/governance/AGENTS.md)
> - Menschliche Beiträge → [`CONTRIBUTING.md`](https://github.com/Toolio-Moodle-Plugin/toolio-infra/blob/main/governance/CONTRIBUTING.md)

---

## Warum es Toolio gibt

Toolio schließt die Kollaborationslücke von Moodle und begleitet die **vollständige
Handlung** (KMK-Lernfelddidaktik) an berufsbildenden Schulen. Es macht kooperatives,
handlungsorientiertes Arbeiten im Unterricht direkt nutzbar — DSGVO-konform, self-hosted.

Warum und wofür im Detail: [docs/01-konzept](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/01-konzept).

---

## Die Dokumentation IST die Spezifikation

- Die Doku beschreibt das **gewünschte Zielsystem**, nicht den aktuellen Code-Stand.
- Der Code ist eine Implementierung dieser Spezifikation.
- Weicht Code von der Doku ab, gilt die **Doku** als Referenz — bis eine **ADR** es ändert.
- Die Doku ist kein Changelog, keine Notizsammlung, keine Historie.

---

## Mentales Modell vor Technik

Jedes Dokument beantwortet zuerst **warum** ein Konzept existiert, erst danach **wie** es
technisch umgesetzt wird. Neue Entwickler:innen verstehen zuerst Toolio, dann den Code.

Das gemeinsame mentale Modell (Session, Ansichten, Ablauf einer Unterrichtsstunde) ist an
**einer** Stelle definiert und wird von hier nur verlinkt — siehe
[docs/01-konzept](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/01-konzept)
und [docs/03-werkzeuge](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/03-werkzeuge).

---

## Single Source of Truth

Globale Konzepte werden **niemals** mehrfach erklärt (z. B. Session-Lifecycle, Realtime,
Capabilities, Datenhaltung, Rollenmodell, View-States). Existiert eine Erklärung, wird
ausschließlich darauf **verwiesen** — nicht erneut erklärt. Das gilt auch für die
Governance-Dateien selbst.

---

## Offene Architektur ist erlaubt — aber sichtbar

- Viele Architekturentscheidungen sind bewusst offen.
- **Erfinde keine Architektur.** Fehlt eine Entscheidung, wird die Lücke benannt, mit
  Optionen, Vor-/Nachteilen und Empfehlung — als **ADR**.
- Architektur wird **nie stillschweigend im Code** entschieden.

ADR-Prozess: [docs/adr](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/adr).

---

## Prioritäten (Verfassungsrang)

1. Vision · 2. Mentales Modell · 3. Terminologie · 4. Architektur · 5. Doku · 6. Code.

Eine niedrigere Priorität darf eine höhere niemals verletzen.

---

## Schreibstil der Doku

Knapp. Listen und Tabellen bevorzugen. Sätze möglichst unter 20 Wörtern. Kein Marketing,
keine Historie, keine unnötigen Wiederholungen. Annahmen und offene Fragen deutlich
kennzeichnen.
