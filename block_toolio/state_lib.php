<?php
/**
 * Shared state builder for the toolio block.
 * Returns the current tool state (pinned activities + running sessions)
 * so it can be rendered identically on initial load and via realtime polling.
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Build the block state for a course.
 *
 * @param stdClass $course
 * @param context $context
 * @param bool $isTeacher
 * @return array
 */
function block_toolio_build_state($course, $context, $isTeacher) {
    global $DB;

    $modinfo = get_fast_modinfo($course);

    // Section labels.
    $secrows = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC', 'id, section, name');
    $sectionNames = [];
    foreach ($secrows as $sec) {
        $label = trim(strip_tags($sec->name ?: ''));
        if ($label === '') { $label = get_string('section') . ' ' . $sec->section; }
        $sectionNames[$sec->section] = $label;
    }

    // Pinned cmids (cleaned of stale entries).
    $pinsJson  = get_config('block_toolio', 'pins_' . $course->id);
    $pinnedIds = ($pinsJson && $pinsJson !== '') ? json_decode($pinsJson, true) : [];
    $pinnedIds = is_array($pinnedIds) ? array_map('intval', $pinnedIds) : [];
    $valid = [];
    foreach ($pinnedIds as $pid) {
        try { $modinfo->get_cm($pid); $valid[] = $pid; } catch (Exception $e) {}
    }
    $pinnedIds = $valid;

    $mkitem = function($cm) use ($sectionNames) {
        return [
            'cmid'        => (int)$cm->id,
            'name'        => $cm->name,
            'sectionnum'  => (int)$cm->sectionnum,
            'sectionname' => $sectionNames[$cm->sectionnum] ?? '',
            'modname'     => $cm->modname,
            'visible'     => (bool)$cm->visible,
            'url'         => (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
        ];
    };

    // Pinned items. Students only see visible ones.
    $pinned = [];
    foreach ($pinnedIds as $pid) {
        $cm = $modinfo->get_cm($pid);
        if (!$isTeacher && !$cm->visible) { continue; }
        $it = $mkitem($cm);
        $it['pinned'] = true;
        $pinned[] = $it;
    }
    usort($pinned, function($a, $b) { return $a['sectionnum'] <=> $b['sectionnum']; });

    // Running sessions (teachers only).
    $sessions = [];
    if ($isTeacher && !empty($modinfo->instances['toolio'])) {
        $bySec = [];
        foreach ($modinfo->instances['toolio'] as $cm) {
            if (empty($cm->deletioninprogress)) { $bySec[$cm->sectionnum][] = $cm; }
        }
        ksort($bySec);
        foreach ($bySec as $secnum => $cms) {
            foreach ($cms as $cm) {
                $it = $mkitem($cm);
                $it['pinned'] = in_array((int)$cm->id, $pinnedIds, true);
                $sessions[] = $it;
            }
        }
    }

    // Change signature for cheap realtime diffing.
    $sigsrc = '';
    foreach ($pinned as $p) {
        $sigsrc .= 'P' . $p['cmid'] . ($p['visible'] ? '1' : '0') . $p['name'];
    }
    foreach ($sessions as $sx) {
        $sigsrc .= 'S' . $sx['cmid'] . ($sx['visible'] ? '1' : '0') . ($sx['pinned'] ? '1' : '0') . $sx['name'];
    }

    return [
        'isTeacher' => (bool)$isTeacher,
        'pinnedIds' => array_values($pinnedIds),
        'pinned'    => $pinned,
        'sessions'  => $sessions,
        'sig'       => md5($sigsrc),
    ];
}
