---
id: bewertung
structurizr: Detail_Bewertung
---

# Bewertungstool

> **Status:** Planung · **Handlungsphase:** 5–6 (Kontrollieren/Bewerten) · **Realtime:** SSE

> *📎 Diagramm/Medium „Detail_Bewertung.svg" — lokal in Obsidian verfügbar, nicht in Git.*
> *C3 · Bewertungstool (Structurizr) — Schüler · LK ON · LK OFF plus Persistenz/KI. Lokal interaktiv: [localhost:8088](http://localhost:8088/workspace/1/diagrams#Detail_Bewertung).*

> **Im Zyklus:** Die Bewertung ist ein Arbeitszyklus mit **Kriterien** (LK-, Selbst- oder Peer-Bewertung) — siehe [Zyklus 4](../01-konzept/00-wie-toolio-funktioniert.md#zyklus-4--bewerten-lassen--peer-feedback) von *Wie Toolio funktioniert*.

## Zweck
Strukturierte Bewertung und Reflexion über eine **PioneeR-ähnliche Entscheidungsmatrix**
([prioneer.io](https://prioneer.io/)).
Bewertet werden die **Handlungsergebnisse** der vorigen Phasen — ein **Board-Snapshot**, eine
**Datei** oder ein **Abfrage-Ergebnis**. Damit schließt das Tool den Phasen-Zyklus
(Phase 3–4 → Handlungsergebnis → **Phase 5–6**).

## Bewertungsmodi
- **LK allein:** die Lehrkraft bewertet.
- **Selbstbewertung:** die Gruppe bewertet die eigene Arbeit.
- **Peer-Bewertung:** Gruppen bewerten einander (anonymisiert).

## Zwei Phasen
Wie beim Abfrage-Tool gibt es zwei Phasen:
- **Erarbeiten:** Kriterien festlegen — **vorgegeben durch die LK** oder **gemeinsam mit den
  SuS** gesammelt, inkl. optionaler **Gewichtung**.
- **Bewerten:** Scoring mit **1–5 Punkten je Kriterium**.

## Ansichten
| Ansicht | Sieht / kann |
|---|---|
| **Schüler** | Bewertungsobjekt ansehen (Board/Datei/Abfrage); 1–5 Punkte je Kriterium; je Modus eigene Arbeit (Selbst) oder andere Gruppen (Peer, anonymisiert); ggf. Kriterien mitsammeln; Gesamtergebnis nach LK-Freigabe |
| **Lehrkraft ON** | Bewertungsobjekt wählen (Board/Datei/Abfrage); Modus wählen (LK/Selbst/Peer); Kriterien definieren (allein oder mit SuS) + Gewichtung; Skala 1–5; Freigabe manuell |
| **Lehrkraft OFF** | Matrix-Übersicht (aggregiert, Ø); nach Gruppen & Kriterien; Peer anonym/identifiziert; Präsentationsmodus freischalten; optional **Export ins Moodle-Gradebook** |

## Realtime
SSE — Live-Bewertungsfortschritt an Beteiligte.
Siehe [Realtime-Architektur](../02-architektur/02-realtime.md).

## Datenmodell
- Kriterien-Matrix (Kriterium × Stufe, optional gewichtet)
- Bewertungsobjekt (Board-Snapshot / Datei / Abfrage-Ergebnis)
- Bewertung je Schüler/Gruppe

## Rollen
- Sichtbarkeit von Bewertungen je Rolle steuern.
- Peer-Bewertung anonymisiert.

## Offene Fragen
- Aggregation: Durchschnitt, Median oder gewichteter Score?
- Peer: sehen SuS andere Bewertungen erst nach eigener Abgabe?
- Kriterien-Sammlung durch SuS: eigene Phase oder inline eintippen?
- Welcher Board-Snapshot wird als Bewertungsobjekt fixiert?
- Gradebook: Entwicklungen über mehrere Bewertungen sichtbar machen?
