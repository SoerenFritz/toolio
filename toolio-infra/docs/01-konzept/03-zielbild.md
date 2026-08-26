# 3 · Idee und Zielbild

Handlungsorientierter Unterricht folgt demselben Grundmuster wie das informatische
**EVA-Prinzip**: Auf eine **Eingabe** folgt eine **Verarbeitung**, daraus entsteht
eine **Ausgabe**. Übertragen auf die Lernsituation heißt das:

```mermaid
flowchart TB
    subgraph EVA [" "]
        direction LR
        E(["Eingabe<br/>Handlungssituation"])
        V["Verarbeitung<br/>Methode · Kooperation"]
        A(["Ausgabe<br/>Handlungsergebnis"])
        E --> V --> A
    end

    P{{"Toolio<br/>bedient die Methode"}}
    P -.-> V

    style E fill:#1a1f33,stroke:#7986cb,color:#cfd8ff
    style A fill:#16271c,stroke:#66bb6a,color:#c8e6c9
    style V fill:#231a2e,stroke:#ab47bc,color:#e1bee7
    style P fill:#2b1f10,stroke:#FF9800,color:#ffe0b2
```

Input (Auftrag) und Output (Produkt) gibt die Lehrkraft didaktisch vor — **dazwischen
liegt die Methode**, also das gemeinsame Erarbeiten. Genau diese Methode macht **Toolio**
einfach, sichtbar und kooperativ bedienbar.

## Lösungsprinzipien

Ein **Designprinzip** bildet das Dach; darunter tragen zwei Säulen die Lösung —
**Materialintegration** und **Kollaboration**.

```mermaid
flowchart TB
    D["🛠 Designprinzip<br/>Wesentliches sichtbar · Überflüssiges aus ·<br/>Funktionen nur kontextuell · niedrige Hürde"]
    M["📎 Materialintegration<br/>Bestehendes bewahren,<br/>nicht zerstören"]
    K["🤝 Kollaboration<br/>synchron & stabil,<br/>in Präsenz und Distanz"]

    D --> M
    D --> K

    style D fill:#231a2e,stroke:#ab47bc,color:#e1bee7
    style M fill:#142724,stroke:#26a69a,color:#b2dfdb
    style K fill:#142724,stroke:#26a69a,color:#b2dfdb
```

- **Designprinzip** — Wesentliches sichtbar machen, Überflüssiges ausblenden,
  Funktionen nur **kontextuell** zeigen, niedrige Einstiegshürde.
- **Materialintegration** — bestehende Unterrichtsmaterialien und gewachsene Kursstrukturen
  bleiben **unangetastet**: direkt nutzbar, automatisiert über die Dateiablage,
  keine Doppelpflege. Toolio ergänzt, ohne Vorhandenes zu zerstören.
- **Kollaboration** — synchron in Präsenz und Distanz, bewährte Drittanbieter-Konzepte
  nativ integriert, Gruppenarbeit stabil und einfach.

## Zielbild in einem Satz
Ein Plugin, das die **Kollaborationslücke** von Moodle schließt und die
**vollständige Handlung** durchgängig mit einfachen, rollenklaren Werkzeugen begleitet.

> Wie sich die Werkzeuge auf die Handlungsphasen verteilen:
> siehe [Vollständige Handlung](04-vollstaendige-handlung.md).
> Wie die Bedienung einfach bleibt: siehe [Bedienkonzept](05-bedienkonzept-switch.md).
