# Architecture Decision Records (ADR)

Verbindliche Architekturentscheidungen für Toolio. Eine ADR macht eine Entscheidung
sichtbar, statt sie stillschweigend im Code zu treffen.

## Prozess

1. `0000-template.md` kopieren → nächste fortlaufende Nummer.
2. Kontext, Optionen, Entscheidung, Konsequenzen ausfüllen.
3. Status pflegen: **Vorgeschlagen → Akzeptiert** (oder *Abgelehnt* / *Ersetzt durch*).
4. Entscheidung in die betroffene Doku / Hard Rules übernehmen.

Offene Fragen bleiben als ADR mit Status **Vorgeschlagen** stehen — nie im Code verstecken.

## Register

| ADR | Titel | Status |
|-----|-------|--------|
| [0001](0001-realtime-sse-websockets.md) | Realtime — SSE Standard, WebSockets nur Board | Teilweise akzeptiert / offen |
| [0002](0002-terminologie-live-unterricht-vs-drei-takt.md) | Terminologie — Drei-Takt / Switch statt „Live-Unterricht / Session / Regie" | Akzeptiert |
| [0003](0003-datenmodell-drei-takt-verkettung.md) | Datenmodell — Zyklus-Kette & Verkettung in `mod_toolio` | Akzeptiert |
