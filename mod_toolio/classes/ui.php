<?php
namespace mod_toolio;

defined('MOODLE_INTERNAL') || die();

/**
 * Gemeinsamer UI-Baustein für den mod_toolio-Monolith.
 *
 * Realisiert zwei Prinzipien aus docs/01-konzept/08-ui-ux-prinzipien.md:
 *  - "Zentrale Tokens": Farbe/Radius/Abstand liegen an EINER Stelle, nicht je Werkzeug.
 *  - "Statusleiste ... überall identisch": Chrome (Statusleiste, Zurück, Wartescreen)
 *    wird hier einmal gebaut und von Entry und allen Werkzeugen verwendet.
 *
 * Flaches Design (Rams/Apple): keine Schatten/Verläufe/Glas — Ordnung über Fläche,
 * Typografie und Abstand. Eine Akzentfarbe je Ansicht (🟢 LK ON · 🔵 LK OFF · 🟡 Schüler).
 */
class ui {

    /**
     * Zentrale Design-Tokens je Ansicht.
     *
     * @param string $view view_mode::SUS | LK_ON | LK_OFF
     * @return array{dot:string,label:string,accent:string,accentbg:string,bardark:string}
     */
    public static function tokens(string $view): array {
        $map = [
            view_mode::LK_ON  => ['dot' => '🟢', 'label' => 'Konfigurieren', 'accent' => '#2e7d32', 'accentbg' => '#e8f5e9', 'bardark' => '#f1f8f1'],
            view_mode::LK_OFF => ['dot' => '🔵', 'label' => 'Beobachten',    'accent' => '#1565c0', 'accentbg' => '#e3f2fd', 'bardark' => '#f0f4ff'],
            view_mode::SUS    => ['dot' => '🟡', 'label' => 'Arbeiten',       'accent' => '#e65100', 'accentbg' => '#fff3e0', 'bardark' => '#fff8f0'],
        ];
        return $map[$view] ?? $map[view_mode::SUS];
    }

    /**
     * Gemeinsames, flaches Grund-CSS (Statusleiste, Zurück, Buttons, Karten, Wartescreen).
     * Wird EINMAL je Seite ausgegeben und über die Akzentfarbe der Ansicht parametrisiert.
     *
     * @param array $c Tokens aus self::tokens()
     * @return string <style>-Block
     */
    public static function base_css(array $c): string {
        $accent   = $c['accent'];
        $accentbg = $c['accentbg'];
        $bardark  = $c['bardark'];
        return <<<CSS
<style>
#tio-wrap {
    display: flex; flex-direction: column;
    min-height: calc(100vh - 110px);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: #212121; background: #f9f9f9;
}
#tio-main { flex: 1; display: flex; flex-direction: column; padding: 24px 32px; }

.tio-back {
    display: inline-flex; align-items: center; gap: 6px;
    background: none; border: none; color: #aaa;
    font-size: .83rem; cursor: pointer; padding: 0; margin-bottom: 22px;
    align-self: flex-start; text-decoration: none; transition: color .2s;
}
.tio-back:hover { color: {$accent}; }

.tio-h1  { font-size: 1.7rem; font-weight: 700; color: #212121; margin: 0 0 4px; }
.tio-sub { font-size: .9rem; color: #888; margin: 0 0 26px; }

.tio-section { margin-bottom: 24px; }
.tio-label   { font-size: .82rem; font-weight: 600; color: #666; margin-bottom: 8px; }

/* Segmented control (flach) */
.tio-seg { display: inline-flex; border: 1.5px solid #e0e0e0; border-radius: 10px; overflow: hidden; }
.tio-seg button {
    padding: 9px 20px; border: none; background: #fff; color: #666;
    font-size: .88rem; font-weight: 600; cursor: pointer;
    border-right: 1.5px solid #e0e0e0; transition: background .15s, color .15s;
}
.tio-seg button:last-child { border-right: none; }
.tio-seg button.active { background: {$accentbg}; color: {$accent}; }

/* Stepper */
.tio-step { display: inline-flex; align-items: center; gap: 16px; }
.tio-step button {
    width: 36px; height: 36px; border: 1.5px solid {$accent}55; background: #fff;
    color: {$accent}; border-radius: 8px; font-size: 1.2rem; line-height: 1; cursor: pointer;
    transition: background .15s;
}
.tio-step button:hover { background: {$accentbg}; }
.tio-step .tio-val { font-size: 1.25rem; font-weight: 700; min-width: 2ch; text-align: center; color: #333; }

/* Buttons */
.tio-btn {
    padding: 10px 22px; background: {$accent}; color: #fff; border: none;
    border-radius: 8px; font-size: .9rem; font-weight: 600; cursor: pointer;
    transition: opacity .15s; display: inline-flex; align-items: center; gap: 8px;
}
.tio-btn:hover { opacity: .9; }
.tio-btn:disabled { opacity: .4; cursor: default; }
.tio-btn-ghost { background: #fff; color: {$accent}; border: 1.5px solid {$accent}55; }
.tio-btn-ghost:hover { background: {$accentbg}; opacity: 1; }

/* Gruppen-Raster */
.tio-groups { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
.tio-group  { background: #fff; border: 1.5px solid #eee; border-radius: 10px; padding: 14px; }
.tio-group h4 { margin: 0 0 10px; font-size: .85rem; font-weight: 700; color: {$accent}; }
.tio-member { display: flex; align-items: center; gap: 8px; font-size: .84rem; color: #555; padding: 3px 0; }
.tio-member.tio-me { font-weight: 700; color: #212121; }
.tio-mdot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.tio-on   { background: #43a047; }
.tio-off  { background: #ccc; }

/* Provisorisch-Hinweis (ehrlich sichtbar bis zur Daten-ADR) */
.tio-provisional {
    font-size: .78rem; color: #b26a00; background: #fff8f0;
    border: 1px dashed #e0c9a0; border-radius: 8px; padding: 8px 12px; margin-top: 4px;
}

/* Aktionsleiste unten — eine Primäraktion je Ansicht */
.tio-actions { margin-top: auto; display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding-top: 24px; }
.tio-save-note { font-size: .78rem; color: #888; }

/* Wartescreen statt leerer Seite (keine Sackgassen) */
.tio-wait { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: #bbb; text-align: center; }
.tio-wait-icon  { font-size: 2.4rem; }
.tio-wait-title { font-size: 1.05rem; color: #888; font-weight: 600; }
.tio-wait-sub   { font-size: .82rem; }

/* 5-Schritt-Breadcrumb (← + Schritt 2–5) */
.tio-bc { display: flex; align-items: center; gap: 4px; margin-bottom: 24px; flex-wrap: wrap; }
.tio-bc-back {
    color: #999; text-decoration: none; font-size: .88rem; font-weight: 600;
    padding: 5px 10px; border-radius: 8px; transition: background .15s, color .15s;
    margin-right: 6px; border: 1.5px solid transparent;
}
.tio-bc-back:hover { background: {$accentbg}; color: {$accent}; border-color: {$accent}44; }
.tio-bc-sep { color: #d0d0d0; font-size: .75rem; margin: 0 2px; user-select: none; }
.tio-bc-step {
    border: 1.5px solid #e0e0e0; background: #fff; color: #aaa;
    border-radius: 20px; padding: 5px 14px; font-size: .78rem; font-weight: 600;
    cursor: pointer; transition: all .15s;
}
.tio-bc-step:hover { border-color: {$accent}66; color: {$accent}; }
.tio-bc-step.tio-bc-done { border-color: {$accent}44; color: {$accent}99; background: #fafafa; }
.tio-bc-step.tio-bc-active { border-color: {$accent}; background: {$accentbg}; color: {$accent}; }
</style>
CSS;
    }

    /**
     * 5-Schritt-Breadcrumb: ← + bis zu 4 beschriftete Schritte.
     *
     * @param string $backurl  URL für den ← Zurück-Button
     * @param array  $steps    Schritt-Labels, z.B. ['Material','Werkzeug','Sozialform','Gruppen']
     * @param int    $active   1-basierter Index des aktiven Schritts
     * @param array  $c        Tokens aus self::tokens()
     * @return string
     */
    public static function breadcrumb(string $backurl, array $steps, int $active, array $c): string {
        $html = '<nav class="tio-bc" id="tio-bc" aria-label="Schritte">';
        $html .= "<a class=\"tio-bc-back\" href=\"{$backurl}\" aria-label=\"Zurück zu Methoden\">← Methoden</a>";
        foreach ($steps as $i => $label) {
            $n   = $i + 1;
            $cls = 'tio-bc-step';
            if ($n === $active)  { $cls .= ' tio-bc-active'; }
            if ($n < $active)    { $cls .= ' tio-bc-done'; }
            $html .= '<span class="tio-bc-sep">›</span>';
            $html .= "<button type=\"button\" class=\"{$cls}\" data-step=\"{$n}\">" . s($label) . "</button>";
        }
        $html .= '</nav>';
        return $html;
    }

    /**
     * Zurück-Link (für Wartescreen / "coming soon" — ohne Breadcrumb).
     *
     * @param string $href Ziel-URL (bereits escapt)
     * @param string $label sichtbarer Text
     * @return string
     */
    public static function back_link(string $href, string $label = '← Methoden'): string {
        return "<a class=\"tio-back\" href=\"{$href}\">{$label}</a>";
    }

    /**
     * Wartescreen (statt leerer Seite).
     *
     * @param string $icon Emoji
     * @param string $title Überschrift
     * @param string $sub Unterzeile
     * @return string
     */
    public static function wait(string $icon, string $title, string $sub): string {
        return "<div class=\"tio-wait\">"
            . "<span class=\"tio-wait-icon\">{$icon}</span>"
            . "<span class=\"tio-wait-title\">{$title}</span>"
            . "<span class=\"tio-wait-sub\">{$sub}</span>"
            . "</div>";
    }

    /**
     * Zyklus-Kette — Prototyp-Modus: ein Zykluspunkt + Pluspunkt.
     *
     * @param array    $cycles    stdClass-Records aus store::get_all_cycles()
     * @param int|null $activeid  id des aktiven Zyklus (null = keiner hervorgehoben)
     * @param array    $c         Tokens aus self::tokens()
     * @param int      $cmid      Kursmodul-ID
     * @param bool     $isteacher
     * @return string
     */
    public static function chain_footer(
        array  $cycles,
        ?int   $activeid,
        array  $c,
        int    $cmid,
        bool   $isteacher
    ): string {
        $accent = $c['accent'];
        $css = <<<CSS
<style>
#tio-chain{padding:12px 34px 10px;background:#fff;border-top:1.5px solid #dde2ea;flex-shrink:0}
.tio-ct{display:flex;align-items:flex-start;gap:0}
.tio-cn,.tio-cn-add{display:flex;flex-direction:column;align-items:center;gap:6px;background:none;border:none;position:relative;z-index:1;flex-shrink:0;text-decoration:none;color:inherit}
.tio-cn{cursor:pointer;padding:0 8px}
.tio-cn-add{padding:0 8px}
.tio-cn-d{width:20px;height:20px;border-radius:50%;background:#7d8796;border:2px solid #fff;display:flex;align-items:center;justify-content:center;box-sizing:border-box;transition:transform .12s,background .12s}
.tio-cn:hover .tio-cn-d{transform:scale(1.08);background:#6f7887}
.tio-cn.act .tio-cn-d{background:{$accent}}
.tio-cn-l{font-size:.72rem;color:#6e7785;white-space:nowrap;max-width:80px;overflow:hidden;text-overflow:ellipsis;line-height:1.1}
.tio-cn.act .tio-cn-l{color:#4f5969;font-weight:600}
.tio-ln{height:20px;position:relative;top:0;transform:translateY(9px)}
.tio-ln-solid{width:60px;border-top:2px solid #aeb6c3}
.tio-ln-dash{flex:1;min-width:140px;border-top:2px dashed #aeb6c3}
.tio-cn-add-d{width:20px;height:20px;border-radius:50%;border:2px dashed #7d8796;background:#fff;display:flex;align-items:center;justify-content:center;box-sizing:border-box;font-size:.9rem;font-weight:700;line-height:1;color:#6f7887;transition:border-color .12s,color .12s}
.tio-cn-add:hover .tio-cn-add-d{border-color:{$accent};color:{$accent}}
.tio-cn-add-l{font-size:.72rem;color:#6e7785;line-height:1.1}
.tio-cn-add:hover .tio-cn-add-l{color:#4f5969}
.tio-cn-add-disabled{cursor:default;pointer-events:none}
.tio-cn-add-disabled .tio-cn-add-d{border-color:#98a2b2;color:#7b8493}
.tio-cn-add-disabled .tio-cn-add-l{color:#7b8493}
.tio-build{margin-top:6px;font-size:.62rem;color:#a3acb8;text-align:right;letter-spacing:.02em}
</style>
CSS;
        $backurl = (new \moodle_url('/mod/toolio/view.php', ['id' => $cmid]))->out(false);

        $cycle = !empty($cycles) ? reset($cycles) : null;
        if ($cycle) {
            $isact = $activeid !== null && (int)$cycle->id === $activeid;
            $raw   = $cycle->method ?: 'Zyklus 1';
        } else {
            $isact = false;
            $raw   = 'Zyklus 1';
        }

        $label = 'Zyklus 1';
        $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
        $cls   = $isact ? ' act' : '';
        $node  = "<a class=\"tio-cn{$cls}\" title=\"{$title}\" href=\"{$backurl}\">"
            . "<span class=\"tio-cn-d\"></span>"
            . "<span class=\"tio-cn-l\">{$label}</span>"
            . "</a>\n";

        if ($isteacher) {
            $add = "<a class=\"tio-cn-add\" title=\"Neuer Zyklus\" href=\"{$backurl}\">"
                . "<span class=\"tio-cn-add-d\">+</span>"
                . "<span class=\"tio-cn-add-l\">Neu</span></a>";
        } else {
            $add = "<span class=\"tio-cn-add tio-cn-add-disabled\" title=\"Neuer Zyklus\">"
                . "<span class=\"tio-cn-add-d\">+</span>"
                . "<span class=\"tio-cn-add-l\">Neu</span></span>";
        }

        return $css
            . "<div id=\"tio-chain\"><div class=\"tio-ct\">"
            . $node
            . "<span class=\"tio-ln tio-ln-solid\"></span>"
            . $add
            . "<span class=\"tio-ln tio-ln-dash\"></span>"
            . "</div><div class=\"tio-build\">Prototype build: 2026-07-29c</div></div>\n";
    }

    /**
     * Debug-Panel — kollabierbar, klar als Dev-only markiert.
     * Zum Entfernen: Aufruf in `if (defined('DEVMODE') && DEVMODE)` wrappen.
     *
     * @param array $info Key-Value-Paare für die Debug-Tabelle
     * @return string
     */
    public static function debug_panel(array $info): string {
        $rows = '';
        foreach ($info as $key => $val) {
            $k      = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
            $v      = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
            $rows  .= "<tr><td class=\"tio-dbk\">{$k}</td><td class=\"tio-dbv\">{$v}</td></tr>";
        }
        $head = <<<'DBG'
<style>
#tio-dbg{border-top:2px dashed #e65c00;background:#1a1a1a;font-family:'Courier New',monospace;flex-shrink:0}
#tio-dbtog{display:flex;align-items:center;gap:8px;padding:5px 14px;cursor:pointer;border:none;background:none;color:#e65c00;font-size:.76rem;font-family:inherit;font-weight:700}
#tio-dbbadge{background:#e65c00;color:#fff;padding:1px 5px;border-radius:3px;font-size:.7rem;letter-spacing:.04em}
#tio-dbbd{display:none;padding:6px 14px 10px}
#tio-dbbd.open{display:block}
#tio-dbg table{border-collapse:collapse}
.tio-dbk{color:#88ff88;padding:2px 20px 2px 0;font-weight:700;white-space:nowrap;font-size:.76rem}
.tio-dbv{color:#aaffaa;font-size:.76rem}
</style>
<div id="tio-dbg">
<button id="tio-dbtog" onclick="var b=document.getElementById('tio-dbbd');b.classList.toggle('open');this.querySelector('.tio-dbarr').textContent=b.classList.contains('open')?'▴':'▾';">
<span id="tio-dbbadge">🛠 DEV</span> <span class="tio-dbarr">▾</span>
</button>
<div id="tio-dbbd"><table>
DBG;
        $tail = <<<'DBG'
</table></div>
</div>
DBG;
        return $head . $rows . $tail;
    }
}
