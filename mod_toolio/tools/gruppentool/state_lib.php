<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Nur-Lese-Bruecke zwischen der Gruppentool-Engine (toolio_gt_*) und den
 * Toolio-Ansichten LK OFF / Schueler.
 *
 * Die Engine schreibt ihre Gruppen in toolio_gt_member (groupid je Nutzer) und
 * toolio_gt_state (groupmode, grouplabelsjson). LK OFF und die Schuelersicht
 * lesen jedoch BOOT.state.groups. Diese Funktion rechnet den Engine-Zustand in
 * genau dieses Format um, damit die dort gebauten Gruppen ueberall erscheinen.
 */

/**
 * Berechnet die aktuell in der Engine gebildeten Gruppen fuer ein Kursmodul.
 *
 * @param int    $cmid     Kursmodul-ID (coursemoduleid in toolio_gt_*)
 * @param array  $learners userid => Anzeigename (Lernende, ohne Trainer:innen)
 * @param moodle_database $DB
 * @return array{groups: array, sozialform: string, hasdata: bool}
 */
function toolio_gt_compute_result(int $cmid, array $learners, $DB): array {
    $members = $DB->get_records('toolio_gt_member', ['coursemoduleid' => $cmid]);
    $state = $DB->get_record('toolio_gt_state', ['coursemoduleid' => $cmid]);
    $hasmemberdata = !empty($members);

    if (!$state && !$hasmemberdata) {
        return ['groups' => [], 'sozialform' => 'gruppe', 'hasdata' => false];
    }

    $groupmode = $state ? (string) $state->groupmode : '';
    if ($groupmode === '' && $hasmemberdata) {
        foreach ($members as $member) {
            if (!empty($member->groupmode)) {
                $groupmode = (string) $member->groupmode;
                break;
            }
        }
    }
    $sozialform = ($groupmode === 'partner') ? 'paar' : 'gruppe';

    $labels = [];
    if ($state) {
        $decoded = json_decode((string) $state->grouplabelsjson, true);
        if (is_array($decoded)) {
            $labels = $decoded;
        }
    }

    // Mitglieder je Engine-Gruppe (group-N) sammeln, nur aktive Lernende.
    $buckets = [];
    foreach ($members as $m) {
        $uid = (int) $m->userid;
        if ((int) $m->active !== 1 || $m->groupid === null || $m->groupid === '') {
            continue;
        }
        if (!isset($learners[$uid])) {
            continue;
        }
        $gid = (string) $m->groupid;
        $buckets[$gid][] = ['uid' => $uid, 'order' => (int) $m->grouporder];
    }

    if (empty($buckets)) {
        return ['groups' => [], 'sozialform' => $sozialform, 'hasdata' => $hasmemberdata];
    }

    // Gruppen nach group-N sortieren (numerischer Suffix).
    uksort($buckets, static function ($a, $b) {
        $na = (int) substr($a, strrpos($a, '-') + 1);
        $nb = (int) substr($b, strrpos($b, '-') + 1);
        return $na <=> $nb;
    });

    $groups = [];
    foreach ($buckets as $gid => $rows) {
        usort($rows, static function ($x, $y) {
            return $x['order'] <=> $y['order'] ?: ($x['uid'] <=> $y['uid']);
        });

        $names = [];
        $ids   = [];
        foreach ($rows as $r) {
            $names[] = $learners[$r['uid']];
            $ids[]   = $r['uid'];
        }

        $label = isset($labels[$gid]) ? trim((string) $labels[$gid]) : '';
        if ($label === '') {
            $idx   = (int) substr($gid, strrpos($gid, '-') + 1);
            $label = 'Gruppe ' . max(1, $idx);
        }

        $groups[] = [
            'id'         => $gid,
            'name'       => $label,
            'students'   => $names,
            'studentids' => $ids,
        ];
    }

    // Ungruppierte, aktive Lernende NICHT ausschliessen: jede Person ohne
    // Engine-Gruppe erscheint als eigene Einzelkarte (Name als Label), damit sie
    // Tool/Auftrag genauso erhaelt wie eine Gruppe. Deaktivierte (active=0) bleiben aussen vor.
    $inactive = [];
    $groupeduids = [];
    foreach ($members as $m) {
        if ((int) $m->active !== 1) {
            $inactive[(int) $m->userid] = true;
        }
    }
    foreach ($buckets as $rows) {
        foreach ($rows as $r) {
            $groupeduids[$r['uid']] = true;
        }
    }
    foreach ($learners as $uid => $name) {
        if (isset($groupeduids[$uid]) || isset($inactive[$uid])) {
            continue;
        }
        $groups[] = [
            'id'         => 'solo-' . $uid,
            'name'       => $name,
            'students'   => [$name],
            'studentids' => [$uid],
        ];
    }

    return ['groups' => $groups, 'sozialform' => $sozialform, 'hasdata' => true];
}

/**
 * Ermittelt die Lernenden eines Kursmoduls (eingeschriebene Nutzer ohne
 * Verwaltungsrecht) als userid => Anzeigename.
 *
 * @param context $context Kursmodul-Kontext
 * @return array userid => Anzeigename
 */
function toolio_gt_learners($context): array {
    $teacherids = array_keys(get_users_by_capability($context, 'moodle/course:manageactivities', 'u.id'));
    $enrolled   = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);
    $learners   = [];
    foreach ($enrolled as $u) {
        if (in_array((int) $u->id, array_map('intval', $teacherids), true)) {
            continue;
        }
        $learners[(int) $u->id] = fullname($u);
    }
    return $learners;
}

/**
 * Liefert die aktuelle Engine-Zustandsversion (toolio_gt_state.stateversion) fuer
 * ein Kursmodul. Aendert sich, sobald im Gruppentool Gruppen/Positionen geaendert
 * werden. Dient den Realtime-Endpunkten als zusaetzliches Aenderungssignal, damit
 * LK OFF / Schueler Gruppenaenderungen ohne Reload sehen.
 *
 * @param int             $cmid
 * @param moodle_database $DB
 * @return int
 */
function toolio_gt_state_version(int $cmid, $DB): int {
    $v = $DB->get_field('toolio_gt_state', 'stateversion', ['coursemoduleid' => $cmid]);
    return $v === false ? 0 : (int) $v;
}

/**
 * Bruecke fuer Realtime-Endpunkte (sse.php, poll.php): uebernimmt die in der
 * Gruppentool-Engine gebildeten Gruppen in den ausgelieferten State, damit LK OFF
 * und die Schueleransicht dieselbe Aufteilung sehen wie beim Boot (view.php) —
 * sonst wuerde ein SSE-/Poll-Update die Engine-Gruppen durch die
 * Moodle-Kursgruppen (»Kurs«-Fallback) ueberschreiben.
 *
 * @param mixed           $state    Geladener Zyklus-State (array) oder null
 * @param int             $cmid     Kursmodul-ID
 * @param array           $learners userid => Anzeigename (aus toolio_gt_learners)
 * @param moodle_database $DB
 * @return mixed unveraenderter oder um Engine-Gruppen ergaenzter State
 */
function toolio_gt_bridge_state($state, int $cmid, array $learners, $DB) {
    $result = toolio_gt_compute_result($cmid, $learners, $DB);
    if ($result['hasdata']) {
        // Auch ohne gespeicherten Zyklus-Store (State null) die in der Engine gebildeten
        // Gruppen ausliefern — wie view.php es beim Boot macht. Sonst sehen LK OFF/SuS
        // nichts, solange die LK nicht zusaetzlich "Aenderungen speichern" geklickt hat.
        if (!is_array($state)) {
            $state = [];
        }
        $state['groups']     = $result['groups'];
        $state['sozialform'] = $result['sozialform'];
    }
    return $state;
}
