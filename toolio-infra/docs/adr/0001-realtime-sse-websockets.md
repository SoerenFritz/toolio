# ADR-0001: Realtime — SSE als Standard, WebSockets nur für das Board

- **Status:** Teilweise akzeptiert (Board), im Übrigen **Vorgeschlagen / offen**
- **Datum:** 2026-07-29
- **Betrifft:** Realtime-Kommunikation aller Werkzeuge

## Kontext

Toolio-Werkzeuge zeigen Live-Zustände (Gruppen, Chat, Abfrage, Bewertung, Board). Es
braucht eine einheitliche Realtime-Strategie. Constraints: Self-Hosting/DSGVO, Moodle als
autoritative Datenquelle (MariaDB), begrenzte Server-Ressourcen an Schulen, einfache
Betreibbarkeit.

## Optionen

### Option A — SSE (Server-Sent Events) als Standard
- Vorteile: einfach über HTTP, gut mit Moodle/PHP koppelbar, unidirektional ausreichend
  für „Server hat die Wahrheit, Clients hören zu"; kein Zusatzdienst.
- Nachteile: nur Server→Client; hohe Schreibfrequenz vieler Clients ungünstig.

### Option B — WebSockets überall
- Vorteile: bidirektional, niedrige Latenz.
- Nachteile: Zusatzdienst/Betrieb, überdimensioniert für die meisten Werkzeuge.

## Entscheidung

- **Akzeptiert:** Das **Board** nutzt **WebSockets** (Excalidraw / `excalidraw-room`),
  weil dort viele Teilnehmer gleichzeitig schreiben. Dieser Dienst läuft self-hosted.
- **Vorgeschlagen (offen):** **SSE** als **Standard** für Gruppentool, KI-Chatbot,
  Abfrage und Bewertung. Diese Festlegung ist noch **nicht endgültig verifiziert** und
  bleibt eine offene Architekturfrage, bis sie hier auf *Akzeptiert* gesetzt wird.
- Kein weiteres Werkzeug verwendet WebSockets, sofern keine eigene ADR es beschließt.

## Konsequenzen

- MariaDB bleibt die einzige autoritative Datenquelle; Clientzustände sind flüchtig.
- Solange der SSE-Teil „Vorgeschlagen" ist, dürfen Agenten SSE **nicht** als endgültige
  Regel behandeln — bei abweichendem Bedarf eine Folge-ADR anlegen.
- Bei Akzeptanz: Realtime-Abschnitt in [docs/02-architektur](https://github.com/Toolio-Moodle-Plugin/toolio-infra/tree/main/docs/02-architektur) als Referenz führen.
