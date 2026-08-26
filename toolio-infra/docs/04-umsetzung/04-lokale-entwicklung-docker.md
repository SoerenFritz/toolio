# Lokal entwickeln mit Docker — Leitfaden (für Sören)

> Ziel dieser Seite: **verständlich erklären, warum die letzte Version nicht lief**,
> und dir einen **einfachen Weg** zeigen, das Plugin **auf deinem eigenen Rechner**
> zum Laufen zu bringen — ohne dass du Server-Profi sein musst.
>
> Vorab, ehrlich gemeint: **Dein Moodle-Code war gut.** Deine Funktionsnamen und
> Berechtigungen waren korrekt nach Moodle-Konvention benannt — sauberer als der
> alte Ausgangscode. Es hat an **einer einzigen strukturellen Sache** gehangen.
> Die räumen wir hier aus dem Weg.

---

## 1. Warum es nicht lief (die eine Ursache)

Du hast das Plugin in `mod_whiteboard` umbenannt — inhaltlich völlig okay.
Der Haken: Das Plugin lag weiterhin im **Ordner/Repo namens `kollabboard`**.

In Moodle gilt eine eiserne Regel:

> **Der Ordnername eines `mod`-Plugins muss exakt dem „Short Name" entsprechen.**

Ein Plugin, das im Ordner `mod/kollabboard/` liegt, **muss** `mod_kollabboard` heißen.
Deins hieß aber innen `mod_whiteboard`. Diese Diskrepanz — Ordner sagt „kollabboard",
Code sagt „whiteboard" — wertet Moodle als **kaputtes Plugin** (`detectedbrokenplugin`).

Stell es dir wie einen Personalausweis vor: Auf dem Umschlag steht „Müller",
im Ausweis „Schmidt". Der Beamte lehnt ab — nicht weil der Ausweis schlecht ist,
sondern weil **Umschlag und Inhalt nicht zusammenpassen**.

Dazu kamen noch **gemischte Sprachdateien** im selben Ordner
(`kollabboard.php`, `whiteboard.php`, `local_whiteboard.php`) — das verwirrt Moodle
zusätzlich. Pro `mod`-Plugin gibt es genau **eine** Sprachdatei, und die heißt wie
der Short Name: `lang/en/kollabboard.php`.

### Warum das *alles* blockiert hat

Unsere Deploy-Pipeline ruft nach jedem Push Moodles Upgrade-Routine auf. Moodle prüft
dabei **alle** installierten Plugins. Ist **ein einziges** kaputt, bricht der ganze
Vorgang ab — kein Plugin lässt sich mehr aktualisieren. Deshalb hattest du das Gefühl,
„nichts geht mehr": Ein Namens-Mismatch hat die komplette Instanz lahmgelegt.

### Die goldene Regel zum Merken

| Ort | Muss heißen (Beispiel) |
|---|---|
| Ordner | `mod/kollabboard/` |
| `version.php` → `$plugin->component` | `mod_kollabboard` |
| Funktionen in `lib.php` | `kollabboard_add_instance()` … (ohne `mod_`) |
| Upgrade-Funktion | `xmldb_kollabboard_upgrade()` |
| Sprachdatei | `lang/en/kollabboard.php` |
| Berechtigungen | `mod/kollabboard:view` … |

Kurz: **Ordnername = Short Name**, und der taucht überall wieder auf.
(Alle Regeln stehen ausführlich in `.github/copilot-instructions.md` im Repo.)

---

## 2. Der Trick: erst lokal testen, dann pushen

Dein eigentliches Problem war nicht der Code, sondern der **Arbeitsweg**: Du hast direkt
auf den gemeinsamen Server gepusht — und jeder Fehler hat dort sofort alles blockiert.

Ab jetzt machen wir es andersherum:

```
Ändern  →  lokal im eigenen Moodle ansehen  →  erst wenn es läuft: pushen
```

So kann nichts kaputtgehen, was andere betrifft. Du siehst Fehler sofort auf deinem
eigenen Bildschirm.

---

## 3. Lokales Setup — Schritt für Schritt

Wir starten ein **komplettes Moodle auf deinem Rechner** mit Docker und hängen den
Plugin-Ordner hinein. Das **Board (Excalidraw) musst du nicht selbst bauen** — wir
zeigen einfach auf das schon laufende Board im Internet.

### Schritt 1 — Docker Desktop installieren

- Windows/Mac: [Docker Desktop](https://www.docker.com/products/docker-desktop/) herunterladen und installieren.
- Nach der Installation Docker Desktop **starten** (Wal-Symbol muss laufen).
- Test im Terminal (PowerShell / Terminal-App):
  ```
  docker --version
  ```
  Wenn eine Versionsnummer erscheint, ist alles bereit.

### Schritt 2 — Das Plugin holen

Lege dir einen Arbeitsordner an und hole das Repo hinein:

```bash
mkdir toolio-lokal
cd toolio-lokal
git clone https://github.com/Toolio-Moodle-Plugin/mod_kollabboard.git
```

Danach hast du einen Ordner `mod_kollabboard/` — genau richtig.

### Schritt 3 — Die Docker-Dateien anlegen

Wir nutzen **denselben Stack wie der Server**: die offiziellen Moodle-HQ-Images mit
**Moodle 5.1**. Anders als Fertig-Images (die Moodle mitbringen) enthält
`moodlehq/moodle-php-apache` nur PHP + Apache — den Moodle-Kern holen wir uns einmalig
selbst. Das übernimmt ein kleines Setup-Skript, du musst es nur starten.

Lege **direkt neben** dem Ordner `mod_kollabboard` zwei Dateien an.

**`docker-compose.yml`:**

```yaml
services:
  db:
    image: mariadb:11
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: moodle
      MYSQL_USER: moodle
      MYSQL_PASSWORD: moodle
    command: >
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
    volumes:
      - db_data:/var/lib/mysql

  moodle:
    image: moodlehq/moodle-php-apache:8.2
    container_name: moodle-dev-server
    ports:
      - "8080:80"
    environment:
      APACHE_DOCUMENT_ROOT: /var/www/moodle/public
    volumes:
      - moodle_core:/var/www/moodle
      - moodledata:/var/www/moodledata
      # >>> HIER wird dein Plugin eingehängt <<<
      # Links: dein Ordner. Rechts MUSS auf "kollabboard" enden (= Short Name!)
      - ./mod_kollabboard:/var/www/moodle/public/mod/kollabboard
    depends_on:
      - db

volumes:
  moodle_core:
  moodledata:
  db_data:
```

> Achte auf die Zeile mit `…/public/mod/kollabboard`. Der Teil nach dem letzten `/`
> **muss `kollabboard`** heißen — das ist genau die Regel aus Abschnitt 1, hier in der
> Praxis. Würdest du dort `whiteboard` schreiben, wäre es wieder „kaputt". (In Moodle 5.1
> liegen die `mod`-Plugins unter **`public/mod/…`**.)

**`setup-moodle.sh`** (holt einmalig den Moodle-5.1-Kern und installiert ihn):

```bash
#!/bin/bash
set -e
docker compose up -d
sleep 5

docker exec -i moodle-dev-server bash -c '
  apt-get update && apt-get install -y git unzip curl

  # Moodle-Kern (5.1) nur klonen, wenn noch nicht vorhanden
  if [ ! -d /var/www/moodle/lib ]; then
    cd /var/www
    git clone -b MOODLE_501_STABLE https://github.com/moodle/moodle.git moodle_temp
    cp -a moodle_temp/. /var/www/moodle/
    rm -rf moodle_temp
  fi

  # Composer bereitstellen
  if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
  fi

  cd /var/www/moodle
  composer install --no-dev --classmap-authoritative
  mkdir -p /var/www/moodledata

  php /var/www/moodle/admin/cli/install.php \
    --non-interactive --agree-license --lang=de \
    --wwwroot=http://localhost:8080 \
    --dataroot=/var/www/moodledata \
    --dbtype=mariadb --dbhost=db --dbname=moodle --dbuser=moodle --dbpass=moodle \
    --fullname="Moodle Dev" --shortname="MoodleDev" \
    --adminuser=admin --adminpass="Admin123!" --adminemail="admin@example.com"

  sed -i "s|APACHE_DOCUMENT_ROOT=.*|APACHE_DOCUMENT_ROOT=/var/www/moodle/public|" /etc/apache2/envvars
  echo "ServerName localhost" >> /etc/apache2/apache2.conf
  apachectl -k graceful || true

  chown -R www-data:www-data /var/www/moodle /var/www/moodledata
  chmod -R 775 /var/www/moodle /var/www/moodledata
'
```

Dein Ordner sieht jetzt so aus:

```
toolio-lokal/
├─ docker-compose.yml
├─ setup-moodle.sh
└─ mod_kollabboard/   (das geklonte Repo)
```

### Schritt 4 — Moodle installieren & starten

Im Ordner `toolio-lokal` **einmalig** das Setup ausführen:

```bash
bash setup-moodle.sh
```

Beim **ersten Mal** dauert das ein paar Minuten (der Moodle-5.1-Kern wird geklont,
Composer-Pakete geladen, die Datenbank eingerichtet). Wenn am Ende keine Fehlermeldung
mehr kommt, ist es fertig. Danach genügt künftig ein einfaches:

```bash
docker compose up -d
```

### Schritt 5 — Board-URL eintragen

Damit das Plugin das schon-live Board zeigt, setzen wir einmalig die Adresse
(CLI-Skripte liegen in Moodle 5.1 weiterhin unter `admin/cli`):

```bash
docker compose exec moodle php /var/www/moodle/admin/cli/cfg.php \
  --component=mod_kollabboard --name=boardurl --set=https://board.baldauf.media
```

### Schritt 6 — Im Browser öffnen

- Adresse: **http://localhost:8080**
- Login: Benutzer `admin`, Passwort `Admin123!`

Jetzt einen Testkurs anlegen → „Aktivität anlegen" → **KollabBoard** wählen →
speichern → öffnen. Du solltest das eingebettete Board sehen.

---

## 4. So arbeitest du ab jetzt

1. Du änderst etwas im Ordner `mod_kollabboard/` (z. B. in `view.php`).
2. **Wenn du nur PHP-Dateien geändert hast:** im Browser die Seite neu laden — fertig.
3. **Wenn du `db/install.xml` oder `db/upgrade.php` geändert hast** (Datenbank):
   - Zahl in `version.php` bei `$plugin->version` **erhöhen** (z. B. `…01` → `…02`).
   - Im Browser oben rechts auf **Benachrichtigungen** gehen → Moodle bietet das
     Upgrade an → „Weiter". Erst dadurch wird die Datenbank angepasst.
4. Läuft alles lokal? **Dann** committen und pushen. Nie vorher.

### Wenn mal etwas klemmt

- **Alles neu aufsetzen** (löscht das lokale Test-Moodle, *nicht* deinen Code):
  ```bash
  docker compose down -v
  docker compose up -d
  ```
- **Ist mein Plugin überhaupt angekommen?**
  ```bash
  docker compose exec moodle ls /var/www/moodle/public/mod/kollabboard
  ```
  Dort müssen `version.php`, `lib.php` usw. auftauchen.
- **Plugin wird als „kaputt" gemeldet?** → Fast immer ein Namens-Mismatch
  (Abschnitt 1). Prüfe: Heißt der Ordner rechts im Mount `kollabboard`? Steht in
  `version.php` `mod_kollabboard`? Gibt es genau **eine** Sprachdatei `kollabboard.php`?

---

## 5. Optional: das Board auch lokal bauen (später)

Für den Anfang reicht das schon-live Board (Schritt 5). Wenn du **später** Excalidraw
selbst anpassen willst (Buttons entfernen/hinzufügen), brauchst du das Board lokal.
Das ist der fortgeschrittene Teil und in
[Board-Deployment](03-board-deployment.md) beschrieben — melde dich, dann gehen wir
das gemeinsam durch.

> Gut zu wissen für deine Tests: Das live Board **speichert** inzwischen den Zustand in
> Moodle (übersteht Neuladen, Nachzügler sehen den aktuellen Stand) und zeigt auf dem Board
> **deinen Moodle-Namen** statt eines Zufallsnamens. Beides passiert automatisch, sobald
> `boardurl` gesetzt ist — du musst dafür nichts extra tun. Wer das Board **selbst** lokal
> bauen will, findet die genauen Fork-Patches und Build-Variablen in
> [Board-Deployment](03-board-deployment.md) → „Serverseitige Fork-Anpassungen" und in der
> [Admin-Installationsanleitung](05-admin-installationsanleitung.md), Abschnitt 2.

---

## 6. Kurzfassung

- Es lag **nicht** an schlechtem Code, sondern an **Ordnername ≠ Plugin-Name**.
- Merke: **Ordner = Short Name**, überall gleich (`kollabboard`).
- **Immer erst lokal** in Docker testen, **dann** pushen — so blockierst du nie mehr
  die gemeinsame Instanz.
- Das Board läuft schon; lokal reicht es, mit `boardurl` darauf zu zeigen.
