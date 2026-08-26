# ADR-0002: Terminologie — Drei-Takt / Switch statt „Live-Unterricht / Session / Regie"

- **Status:** Akzeptiert
- **Datum:** 2026-07-29
- **Betrifft:** Konzept-Wortschatz (docs/01-konzept), insbesondere die Reihe 00, 05, 06

## Kontext

In der Dokumentation existieren **zwei parallele Vokabulare** für denselben Ablauf:

- **Neu** (in [00 · Wie Toolio funktioniert](../01-konzept/00-wie-toolio-funktioniert.md),
  gestützt vom realen `mod_toolio`-Flow): die **Toolio-Aktivität** ist der Motor; Einstieg
  über eine **Methoden-Wolke**; Bedienung über den **Switch** (drei Ansichten 🟢/🔵/🟡);
  jede Werkzeug-Aktivität läuft im **Drei-Takt** *Vorbereiten → Arbeiten → Sichern*;
  gesicherte Zwischenstände treiben die **Verkettung**.
- **Alt** (in [06 · Live-Unterricht](../01-konzept/06-live-unterricht.md)): ein separater
  **„Live-Unterricht"** als **Orchestrator/Dachebene** über den Werkzeugen, mit **Session**
  (flüchtige Durchführung), **Regie-Modus/Regie-Leiste** und einem vierstufigen Aufbau
  *Kurs → Lernsituation → Live-Unterricht → Session*.

Die verbindliche Terminologie (`.github/copilot-instructions.md` §12) kennt **Toolio**,
**mod_toolio**, **block_toolio**, die **drei Ansichten** und die **fünf Werkzeuge** — die
Begriffe „Live-Unterricht", „Session", „Regie" sind dort **nicht** sanktioniert. Der reale
`mod_toolio/view.php`-Flow trägt sie ebenfalls nicht. Zwei Vokabulare für dieselbe Sache
verwirren Leser:innen und Agenten und verletzen die Priorität „Terminologie erhalten".

## Optionen

### Option A — Neues Modell ist kanonisch; alte Begriffe werden abgelöst
- Vorteile: eine Sprache, deckt sich mit Code und mit Seite 00; erfüllt §12; klarer für alle.
- Nachteile: 06 muss überarbeitet werden; einige gute Einzelkonzepte müssen umbenannt statt
  verworfen werden.

### Option B — Zwei Ebenen nebeneinander (Live-Unterricht = Dach, Takt = innen)
- Vorteile: 06 bliebe fast unverändert.
- Nachteile: hält zwei Vokabulare am Leben; „Orchestrator über der Aktivität" widerspricht
  dem realen Flow (die Aktivität *ist* der Orchestrator); dauerhafte Verwechslungsgefahr.

### Option C — Alte Begriffe behalten, Seite 00 zurückbauen
- Vorteile: keine Änderung an 06.
- Nachteile: widerspricht Code und §12; verwirft die geschärfte Produktsicht. Abgelehnt.

## Entscheidung

**Akzeptiert: Option A.** Das neue Vokabular ist **kanonisch**. „Live-Unterricht" als
eigene Dachebene, „Session", „Regie-Modus/-Leiste" und „Orchestrator" werden als
**Primärbegriffe abgelöst**. Gute Einzelkonzepte aus 06 (flüchtige Durchführung,
Fokus-+-Kontext-Oberfläche, bewusste Nachbereitung) **bleiben erhalten** — unter dem neuen
Wortschatz. Verbindliche Zuordnung:

| Alt (06) | Neu (kanonisch) |
|---|---|
| „Live-Unterricht" als Orchestrator/Dachebene | Die **Toolio-Aktivität** selbst (`mod_toolio`) — kein separater Layer darüber |
| Session (flüchtige Durchführung) | Der **live freigegebene** Zustand der Aktivität; die Durchführung ist flüchtig, die Aktivität der Anker |
| Regie-Modus / Regie-Oberfläche / Regie-Leiste | Die **Lehrkraft-Ansichten des Switch**: 🟢 **LK ON** und 🔵 **LK OFF** |
| Session-Lebenszyklus: Vorbereiten · Starten · Durchführen · Nachbereiten | Der **Drei-Takt** je Aktivität: 📝 **Vorbereiten** · ⏱️ **Arbeiten** · 💾 **Sichern** (Freigabe = Wechsel 🟢→🔵) |
| Nachbereiten / Abschluss-Dialog | **Sichern** des Zwischenstands + optionaler **Download/Übernahme** nach Moodle |
| Phasen-Stepper / Phase 1–6 als Navigation | **Vollständige Handlung** als optionale **Linse** (kein Geländer); Einstieg über die **Methoden-Wolke** |
| Vier Ebenen: Kurs → Lernsituation → Live-Unterricht → Session | Kurs → (Lernsituation/Kachel) → **Toolio-Aktivität** → **Kette von Zyklen** |

„**Lernsituation**" bleibt als didaktischer Ordnungsbegriff des Kurses gültig; „Switch",
„Ansicht", „Takt", „Zyklus", „Verkettung", „Sichern" sind die kanonischen Begriffe.

> Status **Akzeptiert** (2026-07-29): der neue Wortschatz ist ab sofort verbindlich; Seite 00
> ist die Referenz. Alte Begriffe werden nicht neu eingeführt und in bestehender Doku gemäß
> Tabelle abgelöst.

## Konsequenzen

- **06 · Live-Unterricht** wird überarbeitet: auf den neuen Wortschatz umgestellt und auf
  seine tragfähige Rolle fokussiert (**Live-Durchführung & Nachbereitung** einer
  Toolio-Aktivität); Dopplungen mit 00 und 05 werden entfernt. Alternativ kann 06 ganz in
  00/05 aufgehen — als eigener Folge-Schritt zu entscheiden.
- Verweise auf „Session/Regie" in anderen Dokumenten werden gemäß Tabelle angeglichen.
- Falls nötig, wird §12 der `copilot-instructions.md` um die kanonischen Ablauf-Begriffe
  (Takt, Zyklus, Verkettung, Sichern) ergänzt.
- Solange die Überarbeitung von 06 aussteht, gilt dort die Zuordnungstabelle oben; die alten
  Begriffe werden **nicht** neu eingeführt.
