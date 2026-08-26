# 3 · Daten & Betrieb

Wo liegen die Daten, und wie wird die Suite betrieben? Beides folgt einem Prinzip:
**so wenig Eigenbau wie möglich, alles im Rahmen des Moodle-Kerns.**

## Zustand & Datenfluss

- **Eine Quelle der Wahrheit:** Der Zustand liegt in der **Moodle-DB** — eigene
  Tabellen je Werkzeug (z. B. `gruppentool_mdl_state`) mit einer Versionsnummer.
- **Clients sind nicht autoritativ:** Browser rendern nur, was der Server liefert;
  sie halten keinen eigenen verbindlichen Zustand.
- **Live-Updates** laufen über SSE (Standard) bzw. WebSocket (nur Board) —
  Details in [2 · Realtime-Architektur](02-realtime.md).

## Datenschutz & Betrieb

- **Self-Hosting** auf Schulservern → **DSGVO-konform** ohne Drittanbieter; die Daten
  verlassen die Schulinfrastruktur nicht.
- **Auch das Board bleibt im Haus** — der WebSocket-Server (Excalidraw-Room) läuft auf
  demselben Schulserver wie Moodle, nicht in einer externen Cloud.
- **Rollen & Rechte** werden je Werkzeug über Moodle-Capabilities (`db/access.php`)
  geprüft — kein paralleles Rechtesystem.
- **KI ohne offenen Webzugriff** — der Chatbot arbeitet lokal bzw. gekapselt, kein
  unkontrollierter Abfluss von Schüler- oder Unterrichtsdaten.

> Im Detail je Werkzeug: [03-werkzeuge/](../03-werkzeuge/).
