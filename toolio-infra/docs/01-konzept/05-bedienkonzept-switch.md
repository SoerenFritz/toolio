# 5 · Bedienkonzept: Switch ON / OFF

Lehrkräfte kennen den **Moodle-Bearbeitungsschalter** — er trennt Konfigurieren
von Ansehen. Toolio denkt dieses Prinzip weiter: aus zwei Zuständen werden
**drei klar getrennte Ansichten**, eine davon speziell für Lernende.

> *📎 Diagramm/Medium „switch.png" — lokal in Obsidian verfügbar, nicht in Git.*

```mermaid
flowchart LR
    LK(["👩‍🏫 Lehrkraft"])
    SuS(["👩‍🎓 Schüler:in"])

    LK -->|"🔵 Bearbeiten EIN"| ON["🟢 Konfigurieren"]
    LK -->|"🔵 Bearbeiten AUS"| OFF["🔵 Beobachten & Mitmachen"]
    SuS --> S["🟡 Arbeiten & Teilen"]

    style ON  fill:#14241b,stroke:#66bb6a,color:#c8e6c9
    style OFF fill:#131f2e,stroke:#42a5f5,color:#cfe8ff
    style S   fill:#2b2410,stroke:#ffca28,color:#ffe082
    style LK  fill:#1a1f33,stroke:#7986cb,color:#cfd8ff
    style SuS fill:#1a1f33,stroke:#7986cb,color:#cfd8ff
```

Der entscheidende Unterschied: Im **OFF-Modus** schlüpft die Lehrkraft in die
erweiterte Schülerrolle — sie sieht alle Gruppen gleichzeitig und kann moderierend
mitarbeiten (beim **Board** etwa einen **Erwartungshorizont** eintragen), ohne die
Konfiguration zu berühren.

## Warum kein Einstellungstab?
*Status: akzeptiert (2026-06-24)*

Klassische Einstellungsreiter wurden **verworfen**: zu viele Klicks, Doppelbelegung
von Begriffen, Verwirrung. Der eine Schalter senkt die Einstiegshürde und hält die
Bedienung über alle Werkzeuge **konsistent** — der Preis dafür ist, dass jede der
drei Ansichten dreifach durchdacht und getestet werden muss.

## Verbindlich für die Umsetzung
**Jedes Werkzeug implementiert genau diese drei Ansichten** — ohne Ausnahme.
Siehe Spezifikationen unter [03-werkzeuge/](../03-werkzeuge/).
