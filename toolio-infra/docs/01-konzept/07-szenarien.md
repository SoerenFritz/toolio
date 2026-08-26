# 7 · Unterrichtsszenarien — Evaluierungsbasis

> 30 alltagsnahe Szenarien als Prüfstein für UI/UX und Implementierung.
> Jedes Szenario beschreibt einen echten Anwendungsfall aus der Perspektive einer
> Lehrkraft an einer berufsbildenden Schule in Niedersachsen.

**Legende:**
- **Typ:** K = Klassiker · S = Sonderfall · E = Edge Case
- **Sozialform:** Einzel · Paar · Gruppe
- **Tools:** GT = Gruppentool · CB = Chatbot · BO = Board · AB = Abfrage · BW = Bewertung
- **Aufwand:** Einrichtungszeit in Minuten (≈ Orientierungswert)

---

## Einzel-Tool-Szenarien (1 Tool)

### S01 · Blitzschnelle Gruppenbildung
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Gruppe |
| **Tools** | GT |
| **Aufwand** | < 1 min |

Zwischen zwei Stunden entschieden: heute wird in Gruppen gearbeitet. Arbeitsauftrag liegt bereits auf Moodle. Die LK will ausschließlich wissen, wer mit wem zusammenarbeitet — keine weiteren digitalen Tools.

**Ablauf:** Sozialform → Gruppe · Anzahl wählen · Zufällig einteilen · Freigeben.
**UI-Anforderung:** 4 Interaktionen bis SuS ihre Gruppe sehen. Keine anderen Tools im Weg.

---

### S02 · Exit Ticket am Stundenende
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Einzel |
| **Tools** | AB |
| **Aufwand** | 3 min |

Letzte 10 Minuten. LK will prüfen ob das Thema verstanden wurde. 3–4 kurze Fragen, Multiple Choice, Ergebnisse live sehen. Keine Gruppen — alle individuell.

**Ablauf:** Abfrage aktivieren · Fragen eintippen · Freigeben · Ergebnisse beobachten.
**UI-Anforderung:** Kein Gruppentool-Schritt. Einzelarbeit ist impliziter Default.

---

### S03 · Anonyme Meinungsumfrage
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Einzel |
| **Tools** | AB |
| **Aufwand** | 5 min |

LK will ehrliche Meinungen einholen (z.B. "Wie sicher fühlt ihr euch mit dem Thema?"). Anonymität ist wichtig — SuS sollen offen antworten können.

**Ablauf:** Abfrage · Anonymität einschalten · Likert-Skala · Ergebnisse aggregiert zeigen.
**UI-Anforderung:** Anonymität-Toggle muss prominent und klar beschriftet sein.

---

### S04 · KI-gestützte Einzelrecherche
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Einzel |
| **Tools** | CB |
| **Aufwand** | 20 min (Vorbereitung) |

SuS sollen sich mithilfe des Chatbots zu einer Lernfeldsituation informieren. Der Bot ist als "Kundenvertreter XY" konfiguriert und kennt ausschließlich das hochgeladene Lernfeldmaterial.

**Ablauf:** Chatbot aktivieren · Persona definieren · PDF hochladen · Sozialform Einzel · Freigeben.
**UI-Anforderung:** Material-Upload und Persona-Feld müssen klar getrennt und einfach erreichbar sein.

---

### S05 · Freies kollaboratives Whiteboard
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Gruppe |
| **Tools** | GT + BO |
| **Aufwand** | 5 min |

Gruppen sollen ohne Vorlage frei auf einem Board sammeln (Brainstorming, Mind-Map). Kein Startmaterial, kein Erwartungshorizont — pure Erarbeitung.

**Ablauf:** GT konfigurieren · Board aktivieren · kein Startmaterial · Freigeben.
**UI-Anforderung:** Board-Aktivierung ohne Pflichtfelder möglich (alles optional).

---

### S06 · Selbstbewertung einer Gruppenarbeit
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Gruppe |
| **Tools** | BW |
| **Aufwand** | 10 min |

Nach einer Gruppenarbeit bewertet jede Gruppe ihre eigene Arbeit anhand von 5 vorgegebenen Kriterien. LK hat die Kriterien bereits angelegt; Bewertungsobjekt ist ein Moodle-Dokument.

**Ablauf:** Bewertung aktivieren · Selbstbewertung-Modus · Kriterien eintippen · Datei verlinken · Freigeben.
**UI-Anforderung:** Bewertungsmodus (LK / Selbst / Peer) als klares Auswahlfeld oben.

---

## Dual-Tool-Szenarien (2 Tools)

### S07 · Gruppenarbeit mit kollaborativem Board
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Gruppe |
| **Tools** | GT + BO |
| **Aufwand** | 10 min |

Klassische Gruppenarbeit: jede Gruppe hat ein eigenes Board, erarbeitet z.B. eine Mindmap zu einer Handlungssituation. Am Ende vergleicht die LK alle Boards in der Übersicht.

**Ablauf:** GT (Gruppen einteilen) · Board (kein Startmaterial nötig) · Freigeben · In LK OFF: Boards aller Gruppen parallel beobachten.
**UI-Anforderung:** LK OFF muss Board-Übersicht aller Gruppen zeigen (nicht nur eine).

---

### S08 · KI-Chatbot mit anschließender Gruppenarbeit
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Einzel → Gruppe |
| **Tools** | CB + GT |
| **Aufwand** | 25 min |

SuS recherchieren zunächst einzeln über den Chatbot, tauschen sich danach in Gruppen aus. Sozialform wechselt mitten in der Einheit von Einzel zu Gruppe.

**Ablauf:** CB (Einzel) starten · Nach Phase 1–2: Sozialform auf Gruppe umstellen (GT) · Board oder Moodle-Aufgabe für Gruppenphase.
**UI-Anforderung:** Sozialform-Wechsel während einer laufenden Durchführung muss ohne Neustart möglich sein.

---

### S09 · Gruppenarbeit + Lernstandscheck
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Gruppe → Einzel |
| **Tools** | GT + AB |
| **Aufwand** | 8 min |

Nach einer Gruppenarbeitsphase will die LK prüfen ob alle SuS — nicht nur die Sprechenden der Gruppe — das Thema verstanden haben. Individuelle Abfrage als Abschluss.

**Ablauf:** GT (Gruppen) für Erarbeitung · AB (Einzel) für Abschlusscheck.
**UI-Anforderung:** Tool-Wechsel in LK OFF per Klick; altes Tool bleibt konfiguriert.

---

### S10 · Board-Erarbeitung + Peer-Bewertung
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Gruppe |
| **Tools** | GT + BO + BW |
| **Aufwand** | 20 min (Vorbereitung) |

Gruppen erarbeiten auf dem Board; danach bewertet jede Gruppe das Board einer anderen Gruppe anhand von LK-vorgegebenen Kriterien. Board-Snapshot wird zum Bewertungsobjekt.

**Ablauf:** GT + Board · Ergebnis-Zustand auslösen (Board archivieren) · Bewertung (Peer) auf Board-Snapshot.
**UI-Anforderung:** "Board archivieren → als Bewertungsobjekt verwenden" muss ein klar geführter Übergang sein.

---

### S11 · Chatbot informieren + Abfrage überprüfen
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Einzel |
| **Tools** | CB + AB |
| **Aufwand** | 25 min |

SuS informieren sich über den Chatbot. Danach folgt eine Abfrage, die prüft ob die richtigen Schlüsselinfos verstanden wurden. Beide Tools ohne Gruppenarbeit.

**Ablauf:** CB (Einzel) · AB anschließend (ebenfalls Einzel) · Ergebnisse vergleichen.
**UI-Anforderung:** Reihenfolge der aktiven Tools in der Steuerleiste ist sichtbar und steuerbar.

---

### S12 · SuS stellen eigene Abfrage-Fragen
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Einzel |
| **Tools** | AB |
| **Aufwand** | 10 min |

Modus B der Abfrage: SuS reichen eigene Fragen zum Thema ein, LK prüft und gibt frei, die Klasse beantwortet dann die SuS-Fragen gegenseitig. Fördert Transferdenken.

**Ablauf:** Abfrage (Modus B) · Thema und Fragenrahmen vorgeben · SuS-Einreichungen sichten · Fragen freigeben.
**UI-Anforderung:** LK OFF braucht Moderations-Queue: eingereichte Fragen mit Freigeben/Ablehnen.

---

## Drei-Tool-Szenarien

### S13 · VH-Phasen 1–4: Informieren → Erarbeiten
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Einzel → Gruppe |
| **Tools** | CB + GT + BO |
| **Aufwand** | 30 min (Vorbereitung) |

Klassische Umsetzung der Vollständigen Handlung, erste Hälfte. SuS informieren sich per Chatbot, bilden dann Gruppen und erarbeiten auf dem Board ein Handlungsergebnis.

**Ablauf:** CB (Einzel) · GT (Gruppen einteilen) · Board freigeben · Ergebnis-Zustand am Ende.
**UI-Anforderung:** Tool-Sequenz muss erkennbar sein. Übergänge zwischen Tools klar steuerbar.

---

### S14 · Gruppenarbeit → Board → Bewertung
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Gruppe |
| **Tools** | GT + BO + BW |
| **Aufwand** | 25 min |

Volle Erarbeitungs- und Reflexionssequenz. Gruppen arbeiten am Board, Ergebnis wird archiviert und danach bewertet (Selbst oder Peer).

**Ablauf:** GT · Board · Ergebnis-Zustand · Bewertung auf Snapshot.
**UI-Anforderung:** Snapshot-Übergang ins Bewertungstool muss nahtlos und ohne manuellen Export sein.

---

### S15 · Lernstandscheck → Gruppenarbeit → Board
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Einzel → Gruppe |
| **Tools** | AB + GT + BO |
| **Aufwand** | 15 min |

LK startet mit einem kurzen Vorwissens-Check (Abfrage), teilt dann — basierend auf den Ergebnissen — gezielt heterogene Gruppen ein und gibt das Board frei.

**Ablauf:** AB (Vorwissen) · Ergebnisse analysieren · GT (manuelle Einteilung nach Ergebnis) · Board.
**UI-Anforderung:** Abfrage-Ergebnisse müssen vor der Gruppenbildung sichtbar sein, um informierte Entscheidungen zu treffen.

---

## Vollständige Suite

### S16 · Vollständige Unterrichtseinheit (VH komplett)
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Einzel → Gruppe |
| **Tools** | GT + CB + BO + AB + BW |
| **Aufwand** | 45 min (Vorbereitung) |

Idealtypische Umsetzung der vollständigen Handlung über alle 6 Phasen. Für eine vorbereitete Lernsituation im Lernfeld-Unterricht.

**Ablauf:** CB (Informieren) · GT + BO (Planen/Entscheiden/Durchführen) · AB (Kontrollieren) · BW (Bewerten).
**UI-Anforderung:** Die Suite muss als kohärentes Ganzes erlebt werden — nicht als 5 lose Tools.

---

### S17 · Blockunterricht (4 h, mehrere Zyklen)
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | wechselnd |
| **Tools** | alle, mehrfach |
| **Aufwand** | 60 min (Vorbereitung) |

Im Blockunterricht durchläuft die Klasse mehrere Handlungszyklen. Nach dem ersten Zyklus wird das Ergebnis bewertet, dann startet ein neuer Zyklus mit anderem Thema.

**Ablauf:** Zwei oder mehr vollständige Zyklen in einer Durchführung.
**UI-Anforderung:** Tools müssen zurückgesetzt und erneut gestartet werden können, ohne die Durchführung zu beenden.

---

## Sonderfälle

### S18 · Fehlende SuS nachträglich einteilen
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Gruppe |
| **Tools** | GT |
| **Aufwand** | < 1 min (live) |

Drei SuS kommen zu spät oder fehlen erst und kommen dann doch noch. Die Gruppen sind bereits eingeteilt. LK will sie schnell ergänzen.

**Ablauf:** In LK OFF: Einzel-Zuweisung eines SuS zu einer bestehenden Gruppe.
**UI-Anforderung:** LK OFF muss individuelle Zuweisung zu Gruppen erlauben (drag & drop oder Dropdown).

---

### S19 · Wechsel der Sozialform mitten in der Stunde
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Paar → Gruppe |
| **Tools** | GT |
| **Aufwand** | < 2 min (live) |

Gestartet als Paararbeit. LK entscheidet spontan: Paare fusionieren zu Gruppen. Boards sollen mitgenommen werden.

**Ablauf:** Sozialform in LK OFF von Paar auf Gruppe ändern · Bestehende Board-Inhalte bleiben erhalten.
**UI-Anforderung:** Sozialform-Wechsel darf keine Datenverluste an anderen Tools verursachen.

---

### S20 · Hybrid-Unterricht (Teil Präsenz, Teil Remote)
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | gemischt |
| **Tools** | GT + BO + AB |
| **Aufwand** | 15 min |

Ein Teil der Klasse sitzt im Raum, ein Teil ist via Video zugeschaltet. Gemischte Gruppen aus Präsenz und Remote-SuS arbeiten am Board zusammen.

**Ablauf:** Gruppen manuell aus Präsenz + Remote zusammenstellen · Board synchron · Abfrage für alle gleich.
**UI-Anforderung:** Kein Unterschied in der Tool-Logik zwischen Präsenz und Remote — Synchronisation läuft ohnehin über SSE/WebSocket.

---

### S21 · Vertretungsunterricht ohne Vorbereitung
| | |
|---|---|
| **Typ** | E |
| **Sozialform** | offen |
| **Tools** | AB oder GT |
| **Aufwand** | 0 min |

LK übernimmt spontan eine fremde Klasse. Kein Material, kein Plan. Sie will wenigstens eine Aktivität anbieten: entweder Gruppenarbeit (mit Moodle-Auftrag) oder eine kurze Umfrage.

**Ablauf:** Aktivität öffnen · Einzel-Tool ohne Vorbereitung sofort starten.
**UI-Anforderung:** Der Minimal-Pfad (Abfrage mit einer Frage oder reine Gruppenbildung) muss ohne jede Vorarbeit in unter 60 Sekunden starten.

---

### S22 · Sehr heterogene Klasse, manuelle Einteilung
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Gruppe |
| **Tools** | GT |
| **Aufwand** | 5 min |

LK kennt ihre Klasse: Zufallsgruppen wären kontraproduktiv (Konflikte, Leistungsextreme). Sie will gezielt mischen — SuS A mit SuS B, nicht mit SuS C.

**Ablauf:** GT · Modus "manuell" · Drag & Drop oder Name-by-Name zuweisen.
**UI-Anforderung:** Neben Zufalls-Button muss manuelles Zuweisen gleichwertig zugänglich sein — kein Verstecken hinter "Erweitert".

---

### S23 · Gleiche Einheit für drei Klassen
| | |
|---|---|
| **Typ** | K |
| **Sozialform** | Gruppe |
| **Tools** | alle konfigurierten |
| **Aufwand** | einmalig 30 min, dann 0 |

Klassiker im BBS-Alltag: BK23a, BK23b, BK23c haben dieselbe Lernsituation. LK konfiguriert einmal, führt drei Durchführungen durch.

**Ablauf:** Einheit konfigurieren · Durchführung 1 mit Klasse A · Durchführung 2 mit Klasse B · Durchführung 3 mit Klasse C.
**UI-Anforderung:** Durchführung starten ≠ Einheit neu konfigurieren. Konfiguration bleibt persistent; Durchführungen sind flüchtig.

---

### S24 · Gerät wird geteilt (1 Gerät, 2 SuS)
| | |
|---|---|
| **Typ** | E |
| **Sozialform** | Paar |
| **Tools** | GT + AB |
| **Aufwand** | 2 min |

Nicht alle SuS haben eigene Geräte. Zwei SuS teilen ein Tablet. Bei der Abfrage sollen trotzdem beide individuell antworten.

**Ablauf:** GT (Paar) · AB mit Einzel-Modus · SuS tauschen das Gerät zwischen den Fragen.
**UI-Anforderung:** Abfrage-Fortschritt muss nach Login des zweiten SuS fortgesetzt werden können (nicht von vorne starten).

---

### S25 · Spontaner Tool-Wechsel weil Plan nicht aufgeht
| | |
|---|---|
| **Typ** | E |
| **Sozialform** | Gruppe |
| **Tools** | BO → AB (live) |
| **Aufwand** | 0 min |

LK hatte Board geplant, aber SuS sind nach 5 Minuten erkennbar überfordert. Sie unterbricht, schaltet spontan eine Klärungsabfrage frei — ohne die Durchführung zu beenden.

**Ablauf:** Die Durchführung läuft · LK öffnet in LK OFF: [+ Tool hinzufügen] · AB live hinzufügen · sofort Fragen eingeben · freigeben.
**UI-Anforderung:** [+ Tool hinzufügen] muss im Live-Betrieb (LK OFF) erreichbar sein, ohne in die Konfiguration zurückzukehren.

---

### S26 · Board-Durchführung nach Unterbrechung fortsetzen
| | |
|---|---|
| **Typ** | E |
| **Sozialform** | Gruppe |
| **Tools** | BO |
| **Aufwand** | < 1 min |

Die Durchführung wird nach 20 Minuten durch eine Brandschutzübung unterbrochen. Alle gehen raus. Danach will die Klasse weitermachen — Board-Stände sollen erhalten geblieben sein.

**Ablauf:** Durchführung pausieren oder Browser schließen · Nach Rückkehr: Durchführung fortsetzen · Board-State aus DB geladen.
**UI-Anforderung:** Board-Snapshots werden automatisch persistiert; kein Datenverlust bei unerwartetem Verbindungsabbruch.

---

### S27 · Erwartungshorizont selektiv teilen
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Gruppe |
| **Tools** | BO |
| **Aufwand** | 15 min (Vorbereitung) |

LK hat einen Erwartungshorizont auf dem Board vorbereitet. Am Ende teilt sie nicht alles auf einmal, sondern zunächst nur einen Teil — um Diskussion anzuregen — und dann schrittweise mehr.

**Ablauf:** Erwartungshorizont-Board anlegen (LK ON) · In LK OFF: Teile selektiv droppen.
**UI-Anforderung:** Erwartungshorizont-Elemente müssen einzeln markierbar und schrittweise freigebbar sein.

---

### S28 · Bewertungskriterien gemeinsam mit SuS sammeln
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Einzel |
| **Tools** | BW |
| **Aufwand** | 10 min (live) |

LK gibt keine Kriterien vor. Stattdessen sammeln SuS per Live-Eingabe selbst, was gute Arbeit auszeichnet. LK moderiert, wählt die besten Kriterien aus und startet dann die Bewertung.

**Ablauf:** Bewertung (Kriterien-Modus "SuS sammeln") · Moderations-Queue · Kriterien bestätigen · Bewertungsphase starten.
**UI-Anforderung:** Kriterien-Sammlung und Bewertungsphase sind zwei klar getrennte Schritte mit explizitem Übergang.

---

### S29 · Chatbot-Einzel-Recherche + Gruppen-Synthese
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | Einzel → Gruppe |
| **Tools** | CB + GT + BO |
| **Aufwand** | 30 min |

SuS recherchieren zunächst allein per Chatbot (Phase 1). Dann bilden Gruppen und synthetisieren ihre Erkenntnisse auf einem gemeinsamen Board (Phase 3–4).

**Ablauf:** CB (Einzel) · Sozialform wechseln zu Gruppe (GT) · BO für Synthese.
**UI-Anforderung:** Chat-Verlauf der Einzel-Phase bleibt für SuS einsehbar auch nach Sozialform-Wechsel.

---

### S30 · Lernfelddurchlauf mit Zwischen-Evaluation
| | |
|---|---|
| **Typ** | S |
| **Sozialform** | wechselnd |
| **Tools** | alle |
| **Aufwand** | 60 min (Vorbereitung) |

Über mehrere Unterrichtsstunden wird ein vollständiger Lernfelddurchlauf durchgeführt. Nach jeder VH-Phase gibt es eine kurze Abfrage als Reflexionspunkt — bevor die nächste Phase beginnt.

**Ablauf:** Chatbot (Phase 1–2) · AB als Zwischenevaluation · Board (Phase 3–4) · AB als zweite Evaluation · Bewertung (Phase 5–6).
**UI-Anforderung:** Abfragen müssen als "Checkpoint" zwischen anderen Tools aktivierbar sein, ohne die laufende Konfiguration zu stören.

---

## Auswertungsmatrix

| # | Tools | Typ | Sozialform | Aufwand | Vorbereitung nötig? |
|---|---|---|---|---|---|
| S01 | GT | K | Gruppe | < 1 min | Nein |
| S02 | AB | K | Einzel | 3 min | Nein |
| S03 | AB | K | Einzel | 5 min | Nein |
| S04 | CB | K | Einzel | 20 min | Ja |
| S05 | GT+BO | K | Gruppe | 5 min | Nein |
| S06 | BW | S | Gruppe | 10 min | Ja |
| S07 | GT+BO | K | Gruppe | 10 min | Nein/Ja |
| S08 | CB+GT | K | Einzel→Gruppe | 25 min | Ja |
| S09 | GT+AB | K | Gruppe→Einzel | 8 min | Nein |
| S10 | GT+BO+BW | K | Gruppe | 20 min | Ja |
| S11 | CB+AB | S | Einzel | 25 min | Ja |
| S12 | AB | S | Einzel | 10 min | Nein |
| S13 | CB+GT+BO | K | Einzel→Gruppe | 30 min | Ja |
| S14 | GT+BO+BW | K | Gruppe | 25 min | Ja |
| S15 | AB+GT+BO | S | Einzel→Gruppe | 15 min | Nein/Ja |
| S16 | alle | K | wechselnd | 45 min | Ja |
| S17 | alle | S | wechselnd | 60 min | Ja |
| S18 | GT | S | Gruppe | < 1 min | Nein |
| S19 | GT | S | wechselnd | < 2 min | Nein |
| S20 | GT+BO+AB | S | gemischt | 15 min | Nein/Ja |
| S21 | AB/GT | E | offen | 0 min | Nein |
| S22 | GT | S | Gruppe | 5 min | Nein |
| S23 | alle konfig. | K | Gruppe | 0 (2.+3. Mal) | einmalig |
| S24 | GT+AB | E | Paar | 2 min | Nein |
| S25 | BO→AB | E | Gruppe | 0 min (live) | Nein |
| S26 | BO | E | Gruppe | < 1 min | Nein |
| S27 | BO | S | Gruppe | 15 min | Ja |
| S28 | BW | S | Einzel | 10 min (live) | Nein |
| S29 | CB+GT+BO | S | Einzel→Gruppe | 30 min | Ja |
| S30 | alle | S | wechselnd | 60 min | Ja |

---

## Ableitungen für UI/UX

Aus den 30 Szenarien ergeben sich folgende verbindliche Anforderungen:

### 1 · Einzel-Tool-Pfad muss unter 60 Sekunden funktionieren
S01, S02, S21 zeigen: der häufigste Anwendungsfall ist ein einziges Tool, minimal konfiguriert. Kein Pflichtschritt für Tools die nicht genutzt werden.

### 2 · Kein Pflicht-Gruppentool
S02, S03, S04, S11, S12 zeigen: Einzelarbeit ist ein erstklassiger Anwendungsfall. Das Gruppentool ist Basis, nicht Einstiegszwang. Sozialform "Einzel" ist impliziter Default.

### 3 · Sozialform-Wechsel ohne Durchführungs-Neustart
S08, S19, S29 verlangen: Sozialform kann mitten in einer aktiven Durchführung geändert werden. Kein Datenverlust, kein Neustart.

### 4 · Live-Erweiterung im LK OFF
S25 verlangt: [+ Tool hinzufügen] auch im Live-Betrieb (nicht nur in LK ON).

### 5 · Konfiguration ≠ Durchführung (Wiederverwendung)
S23 verlangt: Einheit konfigurieren und Durchführung starten sind entkoppelt. Gleiche Konfiguration, mehrere Durchführungen mit verschiedenen Klassen.

### 6 · Persistenz bei Unterbrechung
S26 verlangt: Board-Snapshots automatisch in DB. Keine Datenverluste bei Verbindungsabbruch.

### 7 · Moderations-Queues für SuS-Inhalte
S12, S28 verlangen: LK muss SuS-Eingaben (Fragen, Kriterien) sichten und freigeben können — live, mit minimaler Ablenkung.

### 8 · Nahtloser Übergang zwischen Tools
S10, S14 verlangen: Board-Snapshot → Bewertungsobjekt muss ein geführter, verlustfreier Übergang sein — kein manueller Export.
