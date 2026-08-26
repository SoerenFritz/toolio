---
id: gruppentool
structurizr: Detail_Gruppentool
---

# Gruppentool

> **Status:** Planung · **Handlungsphase:** phasenübergreifend · **Realtime:** SSE

> *📎 Diagramm/Medium „Detail_Gruppentool.svg" — lokal in Obsidian verfügbar, nicht in Git.*
> *C3 · Gruppentool (Structurizr) — Schüler · LK ON · LK OFF plus Persistenz/Realtime. Lokal interaktiv: [localhost:8088](http://localhost:8088/workspace/1/diagrams#Detail_Gruppentool).*

> **Im Zyklus:** Das Gruppentool setzt die **Sozialform** — eine der beiden freien Achsen jedes Arbeitszyklus (die andere ist das Material). Beispiel: die Placemat in [Zyklus 1](../01-konzept/00-wie-toolio-funktioniert.md#zyklus-1--den-auftrag-verstehen--methode-placemat) von *Wie Toolio funktioniert*.

## Zweck
Zentrales Steuerwerkzeug: legt die **Sozialform** (Einzel/Paar/Gruppe) und die
**Gruppeneinteilung** fest und steuert damit alle nachgelagerten Tools. Bestehende
**Moodle-Gruppen** lassen sich als Startpunkt importieren.

## Ansichten
| Ansicht | Sieht / kann |
|---|---|
| **Schüler** | eigene Gruppe & Sozialform, Mitgliederliste, keine Konfigurationsoptionen; Live via SSE |
| **Lehrkraft ON** | Sozialform wählen, Gruppenanzahl +/−, 1-Klick-Zufall, Moodle-Gruppen-Import — setzt die Sozialform für Chatbot, Board, Abfrage & Bewertung |
| **Lehrkraft OFF** | alle Gruppen read-only, Online-Status je Teilnehmer, Einzelzuweisung bei Fehlzeiten |

## Realtime
SSE — Lehrkraft ändert Sozialform/Gruppen → Server speichert → Push an Lernende.
Siehe [Realtime-Architektur](../02-architektur/02-realtime.md).

## Prototyp (Basis der Suite)
Das Gruppentool war das **erste umgesetzte Werkzeug** und der Ausgangspunkt der gesamten
Suite — hier wurde das SSE-Realtime-Prinzip erstmals praktisch erprobt:

> *📎 Diagramm/Medium „gruppentool_test.mp4" — lokal in Obsidian verfügbar, nicht in Git.*

## Datenmodell
- Sozialform (Enum: einzel/paar/gruppe)
- Gruppe → Mitglieder (Moodle-User) — Import bestehender Moodle-Gruppen als Startpunkt
- Aufgabe/Material-Referenz
- Ergebnis je Gruppe

## Rollen
- Schüler sehen nur die eigene Gruppe.
- Rollen über Moodle-Capabilities.

## Offene Fragen
- Automatische vs. manuelle Gruppeneinteilung?
- Wechsel der Sozialform während laufender Aufgabe?
