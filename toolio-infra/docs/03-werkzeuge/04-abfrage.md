---
id: abfrage
structurizr: Detail_Abfrage
---

# Abfrage-Tool

> **Status:** Planung · **Handlungsphase:** 3–4 (Entscheiden/Durchführen) · **Realtime:** SSE

> *📎 Diagramm/Medium „Detail_Abfrage.svg" — lokal in Obsidian verfügbar, nicht in Git.*
> *C3 · Abfragetool (Structurizr) — Schüler · LK ON · LK OFF plus Persistenz/Realtime. Lokal interaktiv: [localhost:8088](http://localhost:8088/workspace/1/diagrams#Detail_Abfrage).*

> **Im Zyklus:** Die Abfrage lässt sich als vollständiger Arbeitszyklus **spontan einschieben** — siehe [Zyklus 3](../01-konzept/00-wie-toolio-funktioniert.md#zyklus-3--kurz-nachfassen--spontan-eingeschoben) von *Wie Toolio funktioniert*.

## Zweck
Quiz und Umfragen zur Entscheidungs- und Durchführungsunterstützung — orientiert an
**Microsoft Forms** und **Mentimeter**. Das Tool kennt **zwei Betriebsmodi**: einen
LK-Fragenkatalog und einen kollaborativen Modus, in dem die Lernenden selbst Fragen
beisteuern.

## Zwei Modi
- **Modus A — LK-Fragenkatalog:** Die Lehrkraft erstellt die Fragen, die Lernenden füllen aus.
- **Modus B — SuS-kollaborativ:** Die Lehrkraft gibt **Thema und Fragenrahmen** vor; die
  Lernenden **reichen eigene Fragen ein**. Die Lehrkraft **prüft und gibt frei** (oder lehnt
  ab) — erst danach beantworten die anderen die freigegebenen Fragen.

## Ansichten
| Ansicht | Sieht / kann |
|---|---|
| **Schüler** | Modus A: Fragebogen ausfüllen (einzeln oder am Stück); Modus B: eigene Frage einreichen und freigegebene SuS-Fragen beantworten; Fortschritt „X von Y“; Ergebnisse nach LK-Freigabe |
| **Lehrkraft ON** | Modus A/B wählen; Fragen anlegen; Anonymität pro Abfrage; Sozialform (Einzel/Gruppe, via Gruppentool); Freigabe manuell oder zeitgesteuert; Modus B: Thema & Fragenrahmen vorgeben |
| **Lehrkraft OFF** | Live-Fortschritt je Teilnehmer (SSE); Ergebnisse (Balken, Wordcloud, %); zwei Sichten (Klasse gesamt / nach Gruppen); Präsentationsmodus freischalten; Modus B: SuS-Fragen freigeben/ablehnen; Ergebnisse archivieren |

## Fragetypen
Multiple Choice · Freitext · Likert · Ranking · Wordcloud

## Realtime
SSE — Live-Fortschritt und Ergebnis-Push an alle.
Siehe [Realtime-Architektur](../02-architektur/02-realtime.md).

## Datenmodell
- Abfrage (Typ: Quiz/Umfrage)
- Frage → Optionen
- Antwort je Nutzer
- Aggregiertes Ergebnis

## Rollen
- Anonymität **pro Abfrage konfigurierbar**.
- Rollenrechte über Moodle-Capabilities.

## Offene Fragen
- Präsentationsmodus: in-Tool-Toggle oder separater Ansichtslink?
- Modus B: Qualitätssicherung — Fragen erst nach LK-Prüfung sichtbar?
