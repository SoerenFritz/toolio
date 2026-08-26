# Toolio — Dokumentation

Planungs- und Wissensbasis der **Toolio**-Plugin-Suite für Moodle.

> Toolio schließt die **Kollaborationslücke** von Moodle und begleitet die
> **vollständige Handlung** (Lernfelddidaktik) mit einfachen, rollenklaren Werkzeugen.
> Kernprinzip: ein **Switch ON/OFF** → drei Ansichten je Werkzeug (Schüler · LK-ON · LK-OFF).

## Kapitel

| Kapitel | Inhalt |
|---|---|
| [`01-konzept/`](01-konzept/) | Didaktik, Problemraum, Zielbild, Bedienkonzept, Live-Unterricht, UI/UX-Prinzipien, Szenarien |
| [`02-architektur/`](02-architektur/) | Systemüberblick, Integration/Topologie, Realtime (SSE/WebSocket), Daten & Betrieb, Vorgehen |
| [`03-werkzeuge/`](03-werkzeuge/) | Fach-Spezifikation je Werkzeug: Gruppentool · Chatbot · Board · Abfrage · Bewertung |
| [`04-umsetzung/`](04-umsetzung/) | **Bau-Ebene:** Repos & Zusammenführung, interne Struktur von `mod_toolio`, Board-Deployment, Admin-Installation, lokale Entwicklung |

> **Konzept vs. Bau:** Kapitel `01`–`03` beschreiben das **Produkt** (was & warum).
> Kapitel `04` beschreibt den **Bau-Prozess** (welche Repos, wie wird zusammengeführt).

## Empfohlene Lesereihenfolge

**Verstehen, worum es geht**
1. [Rahmenbedingung](01-konzept/01-rahmenbedingung.md)
2. [Problemraum Moodle](01-konzept/02-problemraum-moodle.md)
3. [Zielbild](01-konzept/03-zielbild.md)
4. [Vollständige Handlung](01-konzept/04-vollstaendige-handlung.md)
5. [Bedienkonzept (Switch ON/OFF)](01-konzept/05-bedienkonzept-switch.md)
6. [Live-Unterricht](01-konzept/06-live-unterricht.md)
7. [UI/UX-Prinzipien (weniger ist mehr)](01-konzept/08-ui-ux-prinzipien.md)

**Technik verstehen**
8. [Systemüberblick](02-architektur/00-architektur.md)
9. [Integration & Topologie](02-architektur/01-integration-topologie.md)
10. [Realtime-Architektur (SSE vs. WebSocket)](02-architektur/02-realtime.md)
11. [Daten & Betrieb](02-architektur/03-daten-betrieb.md)

**Selbst bauen (Team)**
12. [Repos & Zusammenführung](04-umsetzung/01-repos-und-zusammenfuehrung.md)
13. [Interne Struktur von `mod_toolio`](04-umsetzung/02-mod-toolio-struktur.md)
14. [Board-Deployment (Excalidraw)](04-umsetzung/03-board-deployment.md)
15. [Admin-Installationsanleitung (Toolio)](04-umsetzung/05-admin-installationsanleitung.md)
16. [Lokal entwickeln mit Docker (Leitfaden)](04-umsetzung/04-lokale-entwicklung-docker.md)
17. [Werkzeug-Specs](03-werkzeuge/01-gruppentool.md) — pro Tool umsetzen

## Diagramme

Alle Diagramme sind als **Mermaid** direkt in den Markdown-Dateien eingebettet und
rendern automatisch auf GitHub und in VS Code — kein Build, kein externes Tool nötig.
