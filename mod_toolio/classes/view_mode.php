<?php
namespace mod_toolio;

defined('MOODLE_INTERNAL') || die();

/**
 * Zentrale Ermittlung der Ansicht (Switch): Schüler · LK-ON · LK-OFF.
 *
 * Gemäß Ziel-Struktur (docs/04-umsetzung/02-mod-toolio-struktur.md) wird die
 * Rolle/Modus-Erkennung EINMAL zentral ermittelt und an die Werkzeuge durchgereicht —
 * nicht in jedem Werkzeug neu erfunden.
 */
class view_mode {

    /** @var string 🟡 Schüler — Arbeiten & Teilen. */
    const SUS = 'sus';

    /** @var string 🟢 LK ON — Konfigurieren (Bearbeiten EIN). */
    const LK_ON = 'lk_on';

    /** @var string 🔵 LK OFF — Beobachten & Mitmachen (Bearbeiten AUS). */
    const LK_OFF = 'lk_off';

    /**
     * Ermittelt die aktuelle Ansicht anhand von Capability und Bearbeitungsmodus.
     *
     * @param \context $context Modul-Kontext der Aktivität.
     * @param \moodle_page $page Die aktuelle Seite (für den Bearbeitungsmodus).
     * @return string einer der Werte self::SUS | self::LK_ON | self::LK_OFF
     */
    public static function detect(\context $context, \moodle_page $page): string {
        if (!has_capability('moodle/course:manageactivities', $context)) {
            return self::SUS;
        }
        return $page->user_is_editing() ? self::LK_ON : self::LK_OFF;
    }

    /**
     * Ist die aktuelle Rolle eine Lehrkraft-Ansicht (LK-ON oder LK-OFF)?
     *
     * @param string $view Ergebnis von self::detect().
     * @return bool
     */
    public static function is_teacher(string $view): bool {
        return $view === self::LK_ON || $view === self::LK_OFF;
    }
}
