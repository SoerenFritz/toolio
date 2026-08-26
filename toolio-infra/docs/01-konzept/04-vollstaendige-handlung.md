# 4 · Vollständige Handlung (didaktische Klammer)

Die vollständige Handlung ist die **didaktische Klammer** für die gesamte Plugin-Suite —
und gleichzeitig die Verarbeitungsebene des [EVA-Prinzips](03-zielbild.md):
Die Lernsituation ist der **Input**, das Handlungsergebnis der **Output**,
die sechs Phasen bilden die **Verarbeitung**. Das Handlungsergebnis entsteht am
Ende von Phase 4 — Kontrollieren und Bewerten wirken auf das fertige Produkt.

> Die Zuordnung der Tools zeigt ihren **Schwerpunkt**, keine Exklusivität.
> Das **Gruppentool** greift als einziges Werkzeug phasenübergreifend ein —
> es legt die Sozialform fest und steuert damit alle anderen.

```mermaid
flowchart TB
    CB(["KI-Chatbot"]) & BO(["Board & Abfrage"]) & BW(["Bewertungstool"])

    CB -.-> P12
    BO -.-> P34
    BW -.-> P56

    LS(["Lern-<br/>situation"]) --> P12["1 Informieren<br/>2 Planen"]
    P12 --> P34["3 Entscheiden<br/>4 Durchführen"]
    P34 --> HE(["Handlungs-<br/>ergebnis"])
    HE --> P56["5 Kontrollieren<br/>6 Bewerten"]
    P56 -.Reflexion.-> LS

    GT(["Gruppentool · Sozialform"])
    GT -.-> P12 & P34 & P56

    style GT fill:#2b1f10,stroke:#FF9800,color:#ffe0b2
    style LS fill:#1a1f33,stroke:#7986cb,color:#cfd8ff
    style HE fill:#16271c,stroke:#66bb6a,color:#c8e6c9
    style CB fill:#1e2740,stroke:#64b5f6,color:#cfe3ff
    style BO fill:#1e2740,stroke:#64b5f6,color:#cfe3ff
    style BW fill:#1e2740,stroke:#64b5f6,color:#cfe3ff
```

So entsteht ein durchgängiger Werkzeug-Fluss: Jede Phase hat ihr **führendes
Werkzeug**, während das **Gruppentool** als Sozialform-Steuerung den Kreislauf
zusammenhält. Kein Werkzeug steht für sich — alle dienen **derselben Handlung**.

> Wie diese Werkzeuge im Detail funktionieren: [03-werkzeuge/](../03-werkzeuge/).
