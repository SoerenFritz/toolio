# 6 · Einsteiger-Anleitung: Excalidraw lokal mit Docker fuer Kollabboard

Diese Anleitung ist fuer einen schnellen lokalen Test gedacht. Du kannst sie Schritt fuer Schritt mit GitHub Copilot nachbauen.

Ziel:
- Excalidraw lokal starten
- Live-Collab ueber WebSocket aktiv haben
- Speichern/Laden ueber Moodle-Endpoint von mod_kollabboard nutzen

Wichtig:
- Das Patch-Skript ist idempotent (mehrfach ausfuehrbar).
- Fuer Kollabboard muss die Storage-URL auf /mod/kollabboard/storage.php zeigen.

## Voraussetzungen

- Docker Desktop laeuft
- Git ist installiert
- Python 3 ist installiert
- Ein lokales Moodle (oder Dev-Moodle), in dem mod_kollabboard installiert ist

## Schritt 1: Arbeitsordner anlegen

Oeffne ein Terminal in VS Code (Terminal > Neues Terminal). Das Terminal startet im Toolio-Workspace-Stammverzeichnis – merke dir diesen Pfad, du brauchst ihn in Schritt 3.

Dann den Arbeitsordner fuer Excalidraw anlegen. Beispiel:

    mkdir C:\board
    cd C:\board

Du kannst auch jeden anderen Ordner verwenden – ersetze dann `C:\board` in allen folgenden Schritten entsprechend.

## Schritt 2: Excalidraw-Fork holen

(Weiterhin im Terminal, in deinem Arbeitsordner)

    git clone --depth 1 https://github.com/kitsteam/excalidraw.git excalidraw

## Schritt 3: Toolio-Patches anwenden (ohne Env-Datei)

Das Skript patcht den Fork, aber wir lassen die Env-Datei absichtlich aus, damit wir die Kollabboard-URL selbst setzen.

Wechsle im Terminal zurueck ins Toolio-Workspace-Stammverzeichnis (dort wo `toolio-infra/` liegt), dann ausfuehren:

    python toolio-infra/scripts/patch-excalidraw-fork.py --path C:/board/excalidraw --skip-env

Hinweis: `C:/board/excalidraw` ist der Pfad aus Schritt 1+2. Wenn du einen anderen Ordner gewaehlt hast, hier entsprechend anpassen.

## Schritt 4: .env.production fuer Kollabboard erstellen

Datei anlegen: C:/board/excalidraw/.env.production

Inhalt:

    MODE="production"

    # Live-Sync (WebSocket) ueber lokalen Proxy
    VITE_APP_WS_SERVER_URL=http://localhost:8099

    # Persistenz + Nachzuegler-Sync ueber Moodle
    VITE_APP_STORAGE_BACKEND=http
    VITE_APP_HTTP_STORAGE_BACKEND_URL=http://localhost:8080/mod/kollabboard/storage.php

    VITE_APP_ENABLE_TRACKING=false
    VITE_APP_DISABLE_SENTRY=true

Hinweis:
- localhost:8080 ist hier die Beispiel-URL deines lokalen Moodle.
- Wenn dein Moodle anders laeuft, die URL entsprechend anpassen.

## Schritt 5: docker-compose.yml erstellen

Datei anlegen: C:/board/docker-compose.yml

Inhalt:

    services:
      excalidraw-room:
        build:
          context: https://github.com/kitsteam/excalidraw-room.git#master
        container_name: excalidraw-room-local
        restart: unless-stopped

      excalidraw:
        build:
          context: ./excalidraw
          target: production
        container_name: excalidraw-local
        restart: unless-stopped
        environment:
          - NGINX_PORT=3000

      caddy:
        image: caddy:2
        container_name: excalidraw-caddy-local
        restart: unless-stopped
        ports:
          - "8099:80"
        volumes:
          - ./Caddyfile:/etc/caddy/Caddyfile:ro
        depends_on:
          - excalidraw
          - excalidraw-room

## Schritt 6: Caddyfile erstellen

Datei anlegen: C:/board/Caddyfile

Inhalt:

    :80 {
      handle /socket.io/* {
        reverse_proxy excalidraw-room:8090
      }

      handle {
        reverse_proxy excalidraw:3000
      }
    }

Warum Caddy?
- Leicht zu verstehen
- Die kritische Route /socket.io ist klar getrennt

## Schritt 7: Container bauen und starten

Im Ordner C:/board:

    docker compose up -d --build

## Schritt 8: Schneller Funktionstest

1. Browser: http://localhost:8099
2. Wenn Excalidraw sichtbar ist, Frontend laeuft.
3. Moodle-Plugin-Setting boardurl auf http://localhost:8099 setzen.
4. Eine Kollabboard-Aktivitaet im Kurs oeffnen.
5. Dasselbe Board in zwei Tabs oeffnen und zeichnen.
6. Zeichnung muss im anderen Tab sofort erscheinen.

## Schritt 9: boardurl in Moodle setzen (CLI)

Wenn Moodle im Docker-Service moodle laeuft:

    docker compose exec moodle php /var/www/moodle/admin/cli/cfg.php --component=mod_kollabboard --name=boardurl --set=http://localhost:8099

Wenn Moodle nicht im selben Compose-Projekt laeuft, den Befehl im passenden Moodle-Container ausfuehren.

## Typische Fehler und Loesung

1) Board laedt, aber kein Live-Sync
- Ursache: /socket.io Routing fehlt oder ist falsch.
- Loesung: Caddyfile pruefen, Container neu starten:

    docker compose up -d

2) CORS-Fehler im Browser
- Ursache: boardurl in Moodle passt nicht exakt zur Board-URL.
- Loesung: boardurl exakt auf http://localhost:8099 setzen.

3) Speichern/Laden geht nicht
- Ursache: Falscher Storage-Pfad.
- Loesung: In .env.production muss fuer Kollabboard stehen:

    VITE_APP_HTTP_STORAGE_BACKEND_URL=http://localhost:8080/mod/kollabboard/storage.php

4) Frontend aendert sich nicht nach Env/Patch
- Loesung: neu bauen:

    docker compose up -d --build excalidraw

## Optional: Schnell-Check der Dienste

    docker compose ps
    docker compose logs --tail=100 caddy
    docker compose logs --tail=100 excalidraw
    docker compose logs --tail=100 excalidraw-room

Wenn alle drei Services laufen und die 2-Tab-Synchronisation funktioniert, reicht dieses Setup fuer lokale Entwicklung in der Regel aus.
