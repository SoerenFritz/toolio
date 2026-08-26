<?php
namespace mod_toolio;

defined('MOODLE_INTERNAL') || die();

/**
 * Store — der schmale Datenzugang zum Zyklus-Rückgrat (ADR-0003).
 *
 * Hält den Weg-A-Durchstich bewusst klein: eine Toolio-Aktivität hat vorerst genau
 * einen Standard-Zyklus; Werkzeuge legen darunter ihren Live-Arbeitszustand als
 * versionierten JSON-Blob ab (hier: toolio_gruppentool_state). Verkettung, Snapshots
 * und mehrere Zyklen sind im Schema angelegt, aber noch nicht verdrahtet.
 */
class store {

    /**
     * Liefert die id des Standard-Zyklus einer Toolio-Aktivität, legt ihn bei Bedarf an.
     *
     * @param int $toolioid toolio.id (= $cm->instance)
     * @return int cycleid
     */
    public static function ensure_default_cycle(int $toolioid): int {
        global $DB, $USER;

        $existing = self::get_cycle($toolioid);
        if ($existing !== null) {
            return $existing;
        }

        $now = time();
        $record = (object) [
            'toolioid'     => $toolioid,
            'ordinal'      => 0,
            'method'       => null,
            'sozialform'   => null,
            'status'       => 'draft',
            'timecreated'  => $now,
            'timemodified' => $now,
            'usermodified' => (int) ($USER->id ?? 0),
        ];
        return (int) $DB->insert_record('toolio_cycle', $record);
    }

    /**
     * Liefert die id des ersten Zyklus einer Aktivität oder null, wenn keiner existiert.
     *
     * @param int $toolioid
     * @return int|null
     */
    public static function get_cycle(int $toolioid): ?int {
        global $DB;
        $id = $DB->get_field('toolio_cycle', 'id',
            ['toolioid' => $toolioid], IGNORE_MULTIPLE);
        return $id ? (int) $id : null;
    }

    /**
     * Lädt den gespeicherten Gruppentool-Zustand eines Zyklus.
     *
     * @param int $cycleid
     * @return array|null dekodiertes Payload oder null, wenn noch nichts freigegeben wurde
     */
    public static function load_gruppentool(int $cycleid): ?array {
        global $DB;
        $record = $DB->get_record('toolio_gruppentool_state', ['cycleid' => $cycleid]);
        if (!$record) {
            return null;
        }
        $data = json_decode($record->payload, true);
        if (!is_array($data)) {
            return null;
        }
        $data['version'] = (int) $record->version;
        return $data;
    }

    /**
     * Speichert den Gruppentool-Zustand (Upsert, Version wird hochgezählt).
     *
     * @param int $cycleid
     * @param array $payload bereits bereinigtes Payload (siehe save.php)
     * @return int neue Versionsnummer
     */
    public static function save_gruppentool(int $cycleid, array $payload): int {
        global $DB;
        $now  = time();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $existing = $DB->get_record('toolio_gruppentool_state', ['cycleid' => $cycleid]);
        if ($existing) {
            $existing->payload      = $json;
            $existing->version      = (int) $existing->version + 1;
            $existing->timemodified = $now;
            $DB->update_record('toolio_gruppentool_state', $existing);
            return (int) $existing->version;
        }

        $record = (object) [
            'cycleid'      => $cycleid,
            'version'      => 1,
            'payload'      => $json,
            'timemodified' => $now,
        ];
        $DB->insert_record('toolio_gruppentool_state', $record);
        return 1;
    }

    /**
     * Liefert alle Zyklen einer Toolio-Aktivität, nach ordinal sortiert.
     *
     * @param int $toolioid
     * @return array array of stdClass-Records aus toolio_cycle
     */
    public static function get_all_cycles(int $toolioid): array {
        global $DB;
        return array_values(
            $DB->get_records('toolio_cycle', ['toolioid' => $toolioid], 'ordinal ASC')
        );
    }
}
