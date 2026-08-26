---
id: board
---

# Kollaboratives Board

> **Status:** Planung · **Handlungsphase:** 3–4 (Entscheiden/Durchführen) · **Realtime:** WebSocket

> **Betrieb & Deployment:** siehe [3 · Board-Deployment](../04-umsetzung/03-board-deployment.md)
> — dort steht die konkrete Server-Architektur (nur **ein** Docker-Dienst, Persistenz in Moodle).

> **Im Zyklus:** Das Board ist der Arbeitsraum eines Zyklus; sein **gesicherter** Stand wird zum Startmaterial des nächsten — siehe [Zyklus 2](../01-konzept/00-wie-toolio-funktioniert.md#zyklus-2--die-lösung-entwerfen--direkt-das-board) von *Wie Toolio funktioniert*.

## Zweck
Gemeinsames visuelles Arbeiten in Echtzeit — das digitale Pendant zum „Sammeln an der
Tafel", auch im Distanz- und Hybridunterricht. Das Board ist das **einzige Werkzeug mit
bidirektionaler** Verbindung: alle Teilnehmer schreiben gleichzeitig.

## Technische Basis & Anpassungen
- **Basis:** Excalidraw im **kitsteam-Fork** — bewusst **ohne Firebase**.
- **Nur ein echter Dienst:** ein selbst gehosteter **WebSocket-Room-Server**
  (`excalidraw-room`); das Frontend ist ein statischer Build. **Kein** separater
  Zwischenspeicher — die Persistenz übernimmt **Moodle** (Details:
  [Board-Deployment](../04-umsetzung/03-board-deployment.md)).
- **Anpassungen:** Board-**Snapshots in der Moodle-DB** (Key `cmid + groupid`, damit
  **mehrere Boards je Kurs** sauber getrennt sind); Raum-Zuweisung aus Moodle
  (`groupid`/`cmid`); Materialanbindung über die **Moodle File API**.
- Damit bleiben alle Zeichnungen und Schülerdaten **im Haus** (DSGVO) →
  [1 · Integration & Topologie](../02-architektur/01-integration-topologie.md).

## Ansichten
| Ansicht | Sieht / kann |
|---|---|
| **Schüler** | im Board der eigenen Gruppe zeichnen/notieren (Raum kommt vom Gruppentool); Teile aufs gemeinsame Tafelbild „droppen" (veröffentlichen); fremde Gruppen-Boards read-only |
| **Lehrkraft ON** | Startmaterial laden (Datei oder Moodle-Handlungsergebnis); **Erwartungshorizont-Board anlegen** (für SuS verborgen); Aufgabe/Vorlage hinterlegen; Sichtbarkeit & Zeitsteuerung festlegen |
| **Lehrkraft OFF** | alle Gruppen-Boards im Dashboard, einzeln einzoomen; Teile aufs gemeinsame Tafelbild droppen; **Erwartungshorizont selektiv oder komplett teilen**; Ergebnis-Zustand auslösen (Boards archivieren) |

## Realtime
WebSocket (Room-Modell) — Client ↔ Client Broadcast über den WS-Server; Raum-ID aus
`groupid+cmid` (Gruppe) bzw. `cmid` (gemeinsames Tafelbild). Warum WebSocket statt
SSE/Polling/WebRTC: [2 · Realtime-Architektur](../02-architektur/02-realtime.md).

## Zwei Zustände: Entwurf → Handlungsergebnis
Das Board kennt zwei Phasen seiner Arbeit:
1. **Entwurf** — die Gruppe arbeitet im eigenen Raum.
2. **Ergebnis** — die Lehrkraft löst den Ergebnis-Zustand aus: die Boards werden
   **archiviert**, und das gesicherte **Handlungsergebnis** dient als **Startmaterial der
   Folge-Aktivität** (Phase 3–4 → Handlungsergebnis → Phase 5–6).

So *produziert* das Board das Handlungsergebnis im Phasen-Zyklus und reicht es weiter.

## Erwartungshorizont
- **Switch ON:** Die Lehrkraft legt ein **verborgenes** Erwartungshorizont-Board an.
- **Switch OFF:** Sie **droppt** es — **selektiv** (einzelne Teile) oder **komplett** — aufs
  gemeinsame Tafelbild, etwa zum Abgleich nach der Erarbeitung.

## Datenmodell
- Raum je Sozialform; Raum-ID `kb-{cmid}-{groupid}` (Gruppe) bzw. `kb-{cmid}-shared` (Tafelbild)
- **Mehrere Boards je Kurs** möglich: eine DB-Zeile je `(cmid, groupid)` in `kollabboard_boards`
- Szene/Elemente als Excalidraw-State; **Board-Snapshots in der Moodle-DB** (Autosave + Speichern)
- Teilnehmer-Präsenz im Raum (flüchtig, nur über den WebSocket-Server)
- Materialbezug über Moodle File API + archivierte Aktivitätsergebnisse

## Rollen
- Zugang nur zum eigenen Gruppenraum; fremde Boards read-only.

## Offene Fragen
- Drop-Mechanismus: Bereich markieren und aufs Tafelbild kopieren?
- Sehen SuS andere Gruppen-Boards während der Bearbeitung?
- Zeitsteuerung: LK-Button primär, Zeitlimit als optionaler Fallback?
- Snapshot-Intervall / Konfliktauflösung bei gleichzeitigem Editieren?
