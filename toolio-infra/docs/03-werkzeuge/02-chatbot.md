---
id: chatbot
structurizr: Detail_Chatbot
---

# KI-Chatbot

> **Status:** Planung · **Handlungsphase:** 1–2 (Informieren/Planen) · **Realtime:** SSE (nur LK-Monitoring)

> *📎 Diagramm/Medium „Detail_Chatbot.svg" — lokal in Obsidian verfügbar, nicht in Git.*
> *C3 · KI-Chatbot (Structurizr) — Schüler · LK ON · LK OFF plus KI-Anbindung. Lokal interaktiv: [localhost:8088](http://localhost:8088/workspace/1/diagrams#Detail_Chatbot).*

> **Im Zyklus:** Der Chatbot trägt den Takt beim **Informieren/Planen** — z. B. als Kundin-Persona, die die Lehrkraft per Switch (LK ⇄ Kunde) selbst spielt, in [Zyklus 1](../01-konzept/00-wie-toolio-funktioniert.md#zyklus-1--den-auftrag-verstehen--methode-placemat) von *Wie Toolio funktioniert*.

## Zweck
Rollenbasierte Unterstützung beim Informieren und Planen. Der Bot ist eine **KI-Persona aus der
Handlungssituation** (z. B. „Kunde XY") und antwortet **nur aus dem von der Lehrkraft bereitgestellten
Material** — kein Internetzugriff, kein RAG (NotebookLM-artig, gekapselt, DSGVO-konform).

## Ansichten
| Ansicht | Sieht / kann |
|---|---|
| **Schüler** | Chat mit der KI-Persona; Einzel- oder Gruppen-Chat (Sozialform kommt vom Gruppentool); Verlauf bleibt im Kurs gespeichert; KI kennt nur das LK-Material |
| **Lehrkraft ON** | Persona definieren (Name, Aufgabe, Charakter); Material bereitstellen (PDF/Links); Basis-Prompt anpassen; Sozialform & Chat-Freigabe (manuell oder zeitgesteuert) |
| **Lehrkraft OFF** | alle Schüler-Verläufe live mitlesen (SSE); in einen Chat eingreifen und mitschreiben; Überblick, wer begonnen hat |

## Realtime
Das **LK-Monitoring** der Schüler-Aktivitäten läuft über **SSE** (Lehrkraft liest Verläufe live mit).
Der Chat-Turn selbst läuft als **Request/Response über die LLM-API** (API-gesteuert).

## Datenmodell
- `chatbot_config` — `cmid`, `persona_prompt`, `material_refs`, `base_prompt`
- `chatbot_message` — `sessionid`, `role` (`user` | `assistant` | `lk`), `content`, `timecreated`
- Chat-Raum-ID: `userid+cmid` (Einzel-Chat) oder `groupid+cmid` (Gruppen-Chat)
- **System-Prompt** = `Persona + Material + Plugin-Basis-Prompt` (kein RAG, Material direkt als Kontext)

## KI-Evaluierung (eigenständiges Querschnitts-Werkzeug)
Unabhängig vom Phase-1-Bot, aber auf **derselben LLM-API**: ein optionaler **KI-Evaluierungs-Button**
in allen Werkzeugen.
- **Schüler:** eigene Ergebnisse zur Selbstkontrolle bewerten lassen.
- **Lehrkraft:** Klassen-Ergebnisse zusammenfassen lassen.
- Kein Chat-Interface — einmalige Anfrage mit Ergebnis-Kontext.
- Zentrale Konfiguration in der Moodle-Admin (API-Endpoint + Provider).

## DSGVO & Rollen
- **Kein offener Webzugriff** der KI; Antworten nur aus dem bereitgestellten Material.
- Anbindung über eine **austauschbare, API-gesteuerte** Schnittstelle; konkreter Provider noch offen.
- Klären: Speicherung von Konversationen / Einwilligung bei externem API-Provider.

## Offene Fragen
- Welcher Backend-Provider (self-hosted vs. extern)? — derzeit nur „API-gesteuert".
- DSGVO bei Datenweitergabe an eine externe LLM-API — Einwilligungspflicht beim Schülerchat?
- LK-Eingriff: als sichtbare Lehrkraft oder als KI-Persona?
- Kontextfenster-Limit bei großen PDFs / langen Verläufen?
