#!/usr/bin/env python3
"""Wendet die Toolio-Fork-Patches auf einen frisch geklonten kitsteam/excalidraw an
und schreibt die Build-Variablen (.env.production).

Idempotent: bereits gepatchte Stellen werden erkannt und übersprungen.

Verwendung (im Ordner des geklonten Forks ODER via --path):
    BOARD_DOMAIN=board.example.org MOODLE_DOMAIN=moodle.example.org \
        python3 patch-excalidraw-fork.py --path /opt/board/excalidraw

Danach im Board-Ordner:  docker compose up -d --build excalidraw

Hintergrund/Diffs: docs/04-umsetzung/03-board-deployment.md
                   -> "Serverseitige Fork-Anpassungen (reproduzierbar)"
"""
import argparse
import os
import sys

# (relativer Pfad, Anker der bereits erfolgt ist, Original, Ersetzung)
SOURCE_PATCHES = [
    (
        "excalidraw-app/App.tsx",
        "const isCollabDisabled = false;",
        "const isCollabDisabled = isRunningInIframe();",
        "const isCollabDisabled = false;",
    ),
    (
        "excalidraw-app/App.tsx",
        "if (false && parentUrl.origin === currentUrl.origin) {",
        "    if (parentUrl.origin === currentUrl.origin) {",
        "    if (false && parentUrl.origin === currentUrl.origin) {",
    ),
    (
        "excalidraw-app/data/httpStorage.ts",
        "if (!getResponse.ok) {\n    return null;\n  }",
        "  const buffer = await getResponse.arrayBuffer();\n  const elements = getSyncableElements(",
        "  if (!getResponse.ok) {\n    return null;\n  }\n"
        "  const buffer = await getResponse.arrayBuffer();\n"
        "  if (buffer.byteLength < SCENE_VERSION_LENGTH_BYTES + IV_LENGTH_BYTES) {\n"
        "    return null;\n  }\n  const elements = getSyncableElements(",
    ),
    (
        "excalidraw-app/data/localStorage.ts",
        "const injectedUsername = new URLSearchParams(window.location.search).get(",
        "export const importUsernameFromLocalStorage = (): string | null => {\n"
        "  try {\n"
        "    const data = localStorage.getItem(STORAGE_KEYS.LOCAL_STORAGE_COLLAB);",
        "export const importUsernameFromLocalStorage = (): string | null => {\n"
        "  try {\n"
        "    // Toolio/Moodle liefert den Klarnamen per URL-Query (?username=...).\n"
        "    const injectedUsername = new URLSearchParams(window.location.search).get(\n"
        '      "username",\n'
        "    );\n"
        "    if (injectedUsername) {\n"
        "      return injectedUsername;\n"
        "    }\n"
        "    const data = localStorage.getItem(STORAGE_KEYS.LOCAL_STORAGE_COLLAB);",
    ),
    # 5. Toolio-UI-Modus per ?toolio=clean|mini: blendet Excalidraw-Chrome aus.
    #    clean (Schueler): kein Burgermenue, keine Sidebar (Werkzeuge bleiben).
    #    mini (LK-OFF-Kartenvorschau): zusaetzlich View-Mode (keine Werkzeuge, read-only).
    #    Umsetzung: Modus-Klassen an .excalidraw-app + scoped <style> zum Ausblenden.
    (
        "excalidraw-app/App.tsx",
        '"toolio-clean":',
        '      className={clsx("excalidraw-app", {\n'
        '        "is-collaborating": isCollaborating,\n'
        "      })}\n"
        "    >\n"
        "      <Excalidraw\n",
        '      className={clsx("excalidraw-app", {\n'
        '        "is-collaborating": isCollaborating,\n'
        '        "toolio-clean":\n'
        '          new URLSearchParams(window.location.search).get("toolio") ===\n'
        '          "clean",\n'
        '        "toolio-mini":\n'
        '          new URLSearchParams(window.location.search).get("toolio") ===\n'
        '          "mini",\n'
        "      })}\n"
        "    >\n"
        "      <style>{`\n"
        "        .excalidraw-app.toolio-clean .main-menu-trigger,\n"
        "        .excalidraw-app.toolio-clean .sidebar-trigger,\n"
        "        .excalidraw-app.toolio-clean .excalidraw-ui-top-right,\n"
        "        .excalidraw-app.toolio-mini .main-menu-trigger,\n"
        "        .excalidraw-app.toolio-mini .sidebar-trigger,\n"
        "        .excalidraw-app.toolio-mini .App-toolbar,\n"
        "        .excalidraw-app.toolio-mini .excalidraw-ui-top-right { display: none !important; }\n"
        "      `}</style>\n"
        "      <Excalidraw\n",
    ),
    # 6. View-Mode (read-only) fuer Mini-Vorschau (?toolio=mini) UND lehrergesteuerten
    #    Lock: SuS-Board wird read-only (nur Zoom/Pan) und kann nicht selbst verlassen werden.
    #    Der Lock wird per postMessage (toolioViewLock) OHNE iframe-Reload umgeschaltet -
    #    ein Reload aller Peers wuerde sonst die geteilte Board-Szene loeschen.
    (
        "excalidraw-app/App.tsx",
        "viewModeEnabled={",
        "      <Excalidraw\n"
        "        excalidrawAPI={excalidrawRefCallback}\n",
        "      <Excalidraw\n"
        "        viewModeEnabled={\n"
        '          new URLSearchParams(window.location.search).get("toolio") === "mini" ||\n'
        "          toolioViewLock\n"
        "        }\n"
        "        excalidrawAPI={excalidrawRefCallback}\n",
    ),
    # 7. Mini-Vorschau (?toolio=mini) auf den Board-Inhalt einpassen (zoom-to-fit),
    #    damit die LK-OFF-Karte den gesamten Ausschnitt sinnvoll skaliert zeigt.
    (
        "excalidraw-app/App.tsx",
        "Toolio Mini-Vorschau",
        "  const [, forceRefresh] = useState(false);\n"
        "\n"
        "  useEffect(() => {\n"
        "    if (isDevEnv()) {\n",
        "  const [, forceRefresh] = useState(false);\n"
        "\n"
        "  // Toolio Mini-Vorschau (?toolio=mini): Board auf den Inhalt einpassen (zoom-to-fit),\n"
        "  // damit die LK-OFF-Karte den gesamten Board-Ausschnitt sinnvoll skaliert zeigt.\n"
        "  useEffect(() => {\n"
        "    if (\n"
        '      new URLSearchParams(window.location.search).get("toolio") !== "mini" ||\n'
        "      !excalidrawAPI\n"
        "    ) {\n"
        "      return;\n"
        "    }\n"
        "    const fit = () => {\n"
        "      try {\n"
        "        const els = excalidrawAPI.getSceneElements();\n"
        "        if (els && els.length) {\n"
        "          excalidrawAPI.scrollToContent(els, {\n"
        "            fitToContent: true,\n"
        "            animate: false,\n"
        "          });\n"
        "        }\n"
        "      } catch {\n"
        "        // ignore\n"
        "      }\n"
        "    };\n"
        "    fit();\n"
        "    const timer = window.setInterval(fit, 1500);\n"
        "    return () => window.clearInterval(timer);\n"
        "  }, [excalidrawAPI]);\n"
        "\n"
        "  useEffect(() => {\n"
        "    if (isDevEnv()) {\n",
    ),
    # 8. Lehrer-Lock per postMessage: Der Moodle-Parent schaltet den read-only-Modus
    #    live um (ohne iframe-Reload), sodass "Sichern" das Board nicht neu laedt und
    #    die geteilte Szene nicht loescht. Initialwert weiterhin aus ?lock=1.
    (
        "excalidraw-app/App.tsx",
        "const [toolioViewLock, setToolioViewLock]",
        "  const [, forceRefresh] = useState(false);\n"
        "\n"
        "  // Toolio Mini-Vorschau (?toolio=mini): Board auf den Inhalt einpassen (zoom-to-fit),\n",
        "  const [, forceRefresh] = useState(false);\n"
        "\n"
        "  // Toolio Lehrer-Lock: read-only per postMessage vom Moodle-Parent (OHNE iframe-Reload),\n"
        '  // damit "Sichern" das Board nicht neu laedt (Reload aller Peers wuerde die Szene loeschen).\n'
        "  const [toolioViewLock, setToolioViewLock] = useState(\n"
        '    new URLSearchParams(window.location.search).get("lock") === "1",\n'
        "  );\n"
        "  useEffect(() => {\n"
        "    const onToolioMsg = (e: MessageEvent) => {\n"
        "      const d = e && (e.data as { toolio?: string; on?: boolean });\n"
        '      if (d && d.toolio === "viewmode") {\n'
        "        setToolioViewLock(!!d.on);\n"
        "      }\n"
        "    };\n"
        '    window.addEventListener("message", onToolioMsg);\n'
        '    return () => window.removeEventListener("message", onToolioMsg);\n'
        "  }, []);\n"
        "\n"
        "  // Toolio Mini-Vorschau (?toolio=mini): Board auf den Inhalt einpassen (zoom-to-fit),\n",
    ),
]


def patch_sources(root: str) -> None:
    for rel, already, old, new in SOURCE_PATCHES:
        path = os.path.join(root, rel)
        if not os.path.isfile(path):
            print(f"FEHLT   {rel} (übersprungen)")
            continue
        text = open(path, encoding="utf-8").read()
        if already in text:
            print(f"OK      {rel} (bereits gepatcht)")
            continue
        count = text.count(old)
        if count != 1:
            print(f"ABBRUCH {rel}: Anker {count}x gefunden, erwartet 1")
            sys.exit(1)
        open(path, "w", encoding="utf-8").write(text.replace(old, new))
        print(f"PATCH   {rel}")


def write_env(root: str, board_domain: str, moodle_domain: str) -> None:
    path = os.path.join(root, ".env.production")
    lines = [
        'MODE="production"',
        "",
        "# Live-Sync (WebSocket) - Board-Domain",
        f"VITE_APP_WS_SERVER_URL=https://{board_domain}",
        "",
        "# Persistenz + Nachzuegler-Sync ueber Moodle (statt Firebase)",
        "VITE_APP_STORAGE_BACKEND=http",
        f"VITE_APP_HTTP_STORAGE_BACKEND_URL=https://{moodle_domain}/mod/toolio/tools/board/storage.php",
        "",
        "VITE_APP_ENABLE_TRACKING=false",
        "VITE_APP_DISABLE_SENTRY=true",
        "",
    ]
    open(path, "w", encoding="utf-8").write("\n".join(lines))
    print(f"ENV     .env.production geschrieben (board={board_domain}, moodle={moodle_domain})")


def main() -> None:
    parser = argparse.ArgumentParser(description="Toolio-Fork-Patches anwenden")
    parser.add_argument("--path", default=".", help="Pfad zum geklonten excalidraw-Fork")
    parser.add_argument("--board-domain", default=os.environ.get("BOARD_DOMAIN"))
    parser.add_argument("--moodle-domain", default=os.environ.get("MOODLE_DOMAIN"))
    parser.add_argument(
        "--skip-env", action="store_true", help=".env.production NICHT ueberschreiben"
    )
    args = parser.parse_args()

    root = os.path.abspath(args.path)
    if not os.path.isdir(os.path.join(root, "excalidraw-app")):
        print(f"ABBRUCH: {root} sieht nicht nach einem excalidraw-Fork aus")
        sys.exit(1)

    patch_sources(root)

    if args.skip_env:
        print("ENV     uebersprungen (--skip-env)")
    elif not args.board_domain or not args.moodle_domain:
        print(
            "ENV     uebersprungen: BOARD_DOMAIN und MOODLE_DOMAIN nicht gesetzt.\n"
            "        Setze sie per Umgebungsvariable oder --board-domain/--moodle-domain,\n"
            "        oder passe .env.production manuell an."
        )
    else:
        write_env(root, args.board_domain, args.moodle_domain)

    print("FERTIG. Naechster Schritt: docker compose up -d --build excalidraw")


if __name__ == "__main__":
    main()
