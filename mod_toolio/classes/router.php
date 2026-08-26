<?php
namespace mod_toolio;

defined('MOODLE_INTERNAL') || die();

/**
 * Router — die "Weiche" des Monolithen (docs/04-umsetzung/02-mod-toolio-struktur.md).
 *
 * view.php bleibt Entry (Wortwolke); sobald eine Methode ein Werkzeug öffnet
 * (?tool=<key>), übernimmt der Router: Er kennt die Werkzeuge, wählt das passende
 * und reicht die zentral ermittelte Ansicht (Schüler · LK ON · LK OFF) durch.
 *
 * Jedes Werkzeug rendert NUR seinen Inhalt; die gemeinsame Chrome (Statusleiste,
 * Zurück-Link) kommt aus \mod_toolio\ui — überall identisch.
 */
class router {

    /**
     * Werkzeug-Registry: URL-Key → Metadaten.
     * 'ready' = bereits portiert; alles andere zeigt einen ehrlichen "wird gebaut"-Slot.
     */
    private const TOOLS = [
        'gruppen'   => ['dir' => 'gruppentool', 'label' => 'Gruppentool', 'icon' => '👥', 'ready' => true],
        'board'     => ['dir' => 'board',       'label' => 'Board',       'icon' => '📋', 'ready' => false],
        'chatbot'   => ['dir' => 'chatbot',     'label' => 'KI-Chatbot',  'icon' => '🤖', 'ready' => false],
        'abfrage'   => ['dir' => 'abfrage',     'label' => 'Abfrage',     'icon' => '❓', 'ready' => false],
        'bewertung' => ['dir' => 'bewertung',   'label' => 'Bewertung',   'icon' => '⭐', 'ready' => false],
    ];

    /**
     * Ist der Key ein bekanntes Werkzeug?
     *
     * @param string $tool
     * @return bool
     */
    public static function is_tool(string $tool): bool {
        return isset(self::TOOLS[$tool]);
    }

    /**
     * Rendert das gewählte Werkzeug in der aktuellen Ansicht. Gibt die komplette
     * Werkzeug-Seite (zwischen header und footer) direkt aus.
     *
     * @param string $tool URL-Key (?tool=)
     * @param string $view view_mode::SUS | LK_ON | LK_OFF
     * @param array $ctx ['cmid'=>int,'method'=>string,'cmname'=>string,'username'=>string,'backurl'=>string]
     * @return void
     */
    public static function render(string $tool, string $view, array $ctx): void {
        $c        = ui::tokens($view);
        $isteach  = view_mode::is_teacher($view);
        $backurl  = $ctx['backurl'] ?? '#';

        echo ui::base_css($c);
        echo '<div id="tio-wrap">';
        echo '<div id="tio-main">';

        if (!self::is_tool($tool)) {
            self::render_unknown($backurl);
        } else if (!self::TOOLS[$tool]['ready']) {
            // "Coming soon"-Slot: einfacher Rückweg ohne Breadcrumb.
            if ($isteach) {
                echo ui::back_link($backurl);
            }
            self::render_comingsoon(self::TOOLS[$tool]);
        } else {
            $ctx['view'] = $view;
            $ctx['tokens'] = $c;
            self::dispatch(self::TOOLS[$tool]['dir'], $ctx);
        }

        echo '</div></div>';
    }

    /**
     * Lädt die Render-Datei eines portierten Werkzeugs und ruft sie auf.
     *
     * @param string $dir Ordnername unter tools/
     * @param array $ctx durchgereichter Kontext (inkl. 'view' und 'tokens')
     * @return void
     */
    private static function dispatch(string $dir, array $ctx): void {
        $file = dirname(__DIR__) . '/tools/' . $dir . '/render.php';
        if (!is_readable($file)) {
            self::render_unknown($ctx['backurl'] ?? '#');
            return;
        }
        require_once($file);
        $fn = '\\mod_toolio\\tool\\render_' . $dir;
        if (function_exists($fn)) {
            $fn($ctx);
        } else {
            self::render_unknown($ctx['backurl'] ?? '#');
        }
    }

    /**
     * Ehrlicher Platzhalter für ein noch nicht portiertes Werkzeug (keine Sackgasse).
     *
     * @param array $meta Registry-Eintrag
     * @return void
     */
    private static function render_comingsoon(array $meta): void {
        $icon  = $meta['icon'];
        $label = s($meta['label']);
        echo "<div class=\"tio-wait\">"
            . "<span class=\"tio-wait-icon\">{$icon}</span>"
            . "<span class=\"tio-wait-title\">{$label} wird gerade gebaut.</span>"
            . "<span class=\"tio-wait-sub\">Dieses Werkzeug ist noch nicht verfügbar.</span>"
            . "</div>";
    }

    /**
     * Fallback für einen unbekannten Werkzeug-Key.
     *
     * @param string $backurl
     * @return void
     */
    private static function render_unknown(string $backurl): void {
        echo "<div class=\"tio-wait\">"
            . "<span class=\"tio-wait-icon\">🤔</span>"
            . "<span class=\"tio-wait-title\">Werkzeug nicht gefunden.</span>"
            . "<span class=\"tio-wait-sub\"><a href=\"{$backurl}\">← zurück zu den Methoden</a></span>"
            . "</div>";
    }
}
