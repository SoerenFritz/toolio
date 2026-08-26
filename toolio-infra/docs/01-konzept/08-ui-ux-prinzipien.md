# 8 · UI/UX-Prinzipien — weniger ist mehr

Diese Seite hält die **groben Gestaltungsregeln** fest, an denen sich jede Toolio-Ansicht
misst. Bewusst **kein starres Design-System**: Die Detailoptik entsteht iterativ. Was hier
steht, ist der **Prüfstein für Konsistenz** — bei jeder neuen Ansicht gegen diese Regeln
(und die UI-Anforderungen in [7 · Szenarien](07-szenarien.md)) gegenprüfen.

> **Zielgruppe:** Lehrkräfte **ohne** Informatik-Affinität. Die Oberfläche muss
> **selbsterklärend** sein — wer sie zum ersten Mal sieht, benutzt sie ohne Anleitung.

## Vorbilder & Haltung

Toolio steht auf zwei gestalterischen Schultern:

- **Dieter Rams — „Weniger, aber besser."** Gutes Design ist so wenig Design wie möglich:
  unaufdringlich, ehrlich, konsequent, langlebig. Die Oberfläche **tritt zurück**, damit
  der Unterricht im Vordergrund steht — nicht das Werkzeug.
- **Apple / flaches Design.** Klar, flach, funktional. **Kein „Liquid Glass"**, keine
  Glas-/Blur-Effekte, keine Verläufe, kein Skeuomorphismus. Ordnung entsteht durch
  **Fläche, Typografie und Abstand**, nicht durch Dekoration.

## Gestaltungssprache (flach)

| Regel | Konkret |
|---|---|
| **Flach statt Effekt** | Keine Schlagschatten-Spielereien, kein Blur/Glas, keine Verläufe. Ebenen höchstens durch dezente Kanten/Flächen trennen. |
| **Farbe sparsam** | Neutrale Flächen; **eine** Akzentfarbe je Ansicht (🟢 LK ON · 🔵 LK OFF · 🟡 Schüler). Farbe markiert Zustand und Primäraktion — sonst nichts. |
| **Typografie trägt die Hierarchie** | Größe und Gewicht ordnen den Inhalt — nicht Rahmen, Boxen oder Farbflächen. |
| **Weißraum ist Struktur** | Großzügige Abstände statt Trennlinien; klare Kanten, dezente Radien. |
| **Bewegung nur funktional** | Animation zeigt Zustandswechsel (Ein-/Ausblenden), nie Dekoration. |
| **Zentrale Tokens** | Farbe, Radius und Abstand liegen an **einer** Stelle (nicht je Werkzeug hartkodiert), damit die Optik über alle Ansichten trägt. |

## Die Leitregeln

| Prinzip | Bedeutung | Konkret in Toolio |
|---|---|---|
| **Weniger ist mehr** | Nur zeigen, was jetzt gebraucht wird. | Kein Optionen-Dickicht; Erweitertes ist optional und eingeklappt. |
| **Klare Struktur** | Ein sichtbares Raster, wenige Bereiche. | Statusleiste oben · Arbeitsfläche in der Mitte · eine Primäraktion. |
| **Selbsterklärend** | Beschriftung statt Erklärung. | Buttons sagen die Handlung („Für SuS freigeben"), keine Icons ohne Text. |
| **Ein Fokus pro Ansicht** | Eine Aufgabe, ein Ziel je Bildschirm. | Im Werkzeug dominiert **ein** aktives Element (Fokus + Kontext, siehe [06](06-live-unterricht.md)). |
| **Konsistenz über alle Werkzeuge** | Gleiches sieht gleich aus und verhält sich gleich. | Statusleiste, Switch-Badge, Buttons, Wartescreen sind **überall identisch**. |
| **Sichtbarer Zustand** | Man sieht jederzeit, wo man ist. | Die aktive Ansicht ist farbcodiert: 🟢 LK ON · 🔵 LK OFF · 🟡 Schüler. |
| **Keine Sackgassen** | Immer ein Rückweg, nie ein Fehler-Dead-End. | „← zurück" ist immer da; Wartescreen statt leerer Seite. |
| **Sprache der Lehrkraft** | Fachsprache Unterricht, nicht IT. | „Gruppen bilden", „freigeben", „sichern" — kein „Session", „Instanz", „Commit". |
| **Barrierefrei** | Zustand nie nur über Farbe; für alle lesbar. | Farbe **+** Text/Icon (nie Farbe allein); ausreichender Kontrast, große Schrift, Touch-Ziele großzügig. |
| **Touch & kleine Geräte** | Funktioniert auf Tablet und Handy. | Schüler-Ansicht touch-first; geteilte Geräte berücksichtigt (S24); keine Hover-only-Bedienung. |
| **Ehrlicher Live-Zustand** | Man sieht, dass es live ist und was gerade passiert. | „freigegeben / wartet / verbunden" sichtbar; Verbindungsabbruch zeigt sich, nie stiller Fehler (S26). |

## Do / Don't

| ✅ Tun | ❌ Lassen |
|---|---|
| Standardfall in 1–2 Klicks erreichbar | Pflichtfelder für den Standardfall |
| Voreinstellungen, die meistens passen | Leere Formulare ohne Default |
| Eine Primäraktion je Ansicht (farbig) | Mehrere gleichwertige Buttons nebeneinander |
| Text + Symbol zusammen | Symbol allein (nicht selbsterklärend) |
| Nächster Schritt sichtbar / naheliegend | Nutzer raten lassen, was als Nächstes kommt |

## Konsistenz laufend prüfen

Design ist bei Toolio ein **lebendes Dokument** — es schärft sich mit jeder gebauten
Ansicht. Damit es nicht auseinanderdriftet:

1. **Bei jeder neuen Ansicht** gegen die Leitregeln oben **und** gegen die passenden
   „UI-Anforderungen" der [Szenarien](07-szenarien.md) gegenprüfen.
2. **Gemeinsame Bausteine nur einmal** bauen (Statusleiste, Switch-Badge, Buttons,
   Karten, Wartescreen) — nicht je Werkzeug neu erfinden. So bleibt die Optik über den
   Monolith hinweg konsistent (vgl. [Struktur von `mod_toolio`](../04-umsetzung/02-mod-toolio-struktur.md)).
3. **Matrix im Blick behalten:** 5 Werkzeuge × 3 Ansichten. Jede Zelle folgt denselben
   Regeln; Abweichungen brauchen einen Grund.

> Verwandte Seiten: [5 · Bedienkonzept Switch](05-bedienkonzept-switch.md) (der
> Interaktionskern), [6 · Aktivität live führen](06-live-unterricht.md) (konkrete Layouts),
> [7 · Szenarien](07-szenarien.md) (UI-Anforderungen als Testfälle).
