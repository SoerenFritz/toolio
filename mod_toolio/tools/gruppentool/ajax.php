<?php
/**
 * Gruppentool-Engine — AJAX-Endpunkt (1:1-Port der Original-Engine mod_gruppentool).
 *
 * Aktionsprotokoll und Payload-Form sind bewusst identisch zum Original, damit
 * public/teacher.js und public/student.js unveraendert laufen. Der Zustand liegt in
 * den plugin-eigenen Tabellen toolio_gt_member (je Teilnehmer) und toolio_gt_state
 * (je Kursmodul). Realtime laeuft ueber sse.php (Moodle-nativ, kein Node-Server).
 */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_RAW_TRIMMED);

$cm = get_coursemodule_from_id('toolio', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);
$canmanage = has_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/mod/toolio/tools/gruppentool/ajax.php', ['id' => $id, 'action' => $action]);
header('Content-Type: application/json; charset=utf-8');

if (!confirm_sesskey()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid sesskey']);
    die();
}

$readonlyactions = ['init', 'rtc:state_load'];
if (!in_array($action, $readonlyactions, true) && !$canmanage) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not allowed']);
    die();
}

global $DB;

function gm_send_json($data, $statuscode = 200) {
    http_response_code($statuscode);
    echo json_encode($data);
    die();
}

function gm_get_state($cmid, $DB) {
    $state = $DB->get_record('toolio_gt_state', ['coursemoduleid' => $cmid]);
    if ($state) {
        return $state;
    }

    $state = (object)[
        'coursemoduleid' => $cmid,
        'groupcount' => 0,
        'groupmode' => 'groups',
        'groupstableidsjson' => json_encode([]),
        'grouplabelsjson' => json_encode((object)[]),
        'boardstatejson' => null,
        'stateversion' => 0,
        'timemodified' => time(),
    ];
    $state->id = $DB->insert_record('toolio_gt_state', $state);
    return $state;
}

function gm_bump_state_version($cmid, $DB) {
    $state = gm_get_state($cmid, $DB);
    $state->stateversion = (int)$state->stateversion + 1;
    $state->timemodified = time();
    $DB->update_record('toolio_gt_state', $state);
}

function gm_extract_group_index($groupid) {
    $matches = [];
    if (!preg_match('/^group-(\d+)$/', (string)$groupid, $matches)) {
        return null;
    }

    $index = (int)$matches[1];
    if ($index < 1 || $index > 50) {
        return null;
    }

    return $index;
}

function gm_ensure_groupcount_for_groupid($cmid, $groupid, $DB) {
    $target = gm_extract_group_index($groupid);
    if ($target === null) {
        return;
    }

    $state = gm_get_state($cmid, $DB);
    if ((int)$state->groupcount >= $target) {
        return;
    }

    $state->groupcount = $target;
    $state->timemodified = time();
    $DB->update_record('toolio_gt_state', $state);
}

/**
 * Eingeschriebene Teilnehmende OHNE Trainer:innen/Manager:innen.
 * Wer die Aktivitaeten verwalten darf (moodle/course:manageactivities), ist Lehrkraft
 * und damit kein:e Teilnehmende:r – solche Nutzer:innen werden ausgeschlossen.
 */
function gm_enrolled_participants($coursecontext) {
    $namefields = 'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename';
    $enrolled = get_enrolled_users($coursecontext, '', 0, $namefields);
    $managers = get_enrolled_users($coursecontext, 'moodle/course:manageactivities', 0, 'u.id');
    foreach ($enrolled as $key => $u) {
        if (isset($managers[$u->id])) {
            unset($enrolled[$key]);
        }
    }
    return $enrolled;
}

function gm_ensure_member_rows($cmid, $coursecontext, $DB) {
    $enrolled = gm_enrolled_participants($coursecontext);

    foreach ($enrolled as $u) {
        $exists = $DB->record_exists('toolio_gt_member', [
            'coursemoduleid' => $cmid,
            'userid' => (int)$u->id,
        ]);

        if ($exists) {
            continue;
        }

        $row = (object)[
            'coursemoduleid' => $cmid,
            'userid' => (int)$u->id,
            'active' => 1,
            'groupid' => null,
            'groupstableid' => null,
            'grouporder' => 0,
            'groupmode' => null,
            'canvasx' => null,
            'canvasy' => null,
            'timemodified' => time(),
        ];
        $DB->insert_record('toolio_gt_member', $row);
    }
}

function gm_get_members($cmid, $coursecontext, $DB) {
    $enrolled = gm_enrolled_participants($coursecontext);

    $rows = $DB->get_records('toolio_gt_member', ['coursemoduleid' => $cmid]);
    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row->userid] = $row;
    }

    $participants = [];
    foreach ($enrolled as $u) {
        $entry = $map[(int)$u->id] ?? null;
        $participants[] = [
            'participantId' => (string)$u->id,
            'name' => fullname($u),
            'active' => $entry ? ((int)$entry->active === 1) : true,
            'groupId' => $entry && !empty($entry->groupid) ? (string)$entry->groupid : null,
            'groupStableId' => $entry && !empty($entry->groupstableid) ? (string)$entry->groupstableid : null,
            'groupOrder' => $entry ? (int)$entry->grouporder : 0,
            'canvasPosition' => ($entry && $entry->canvasx !== null && $entry->canvasy !== null)
                ? ['x' => (float)$entry->canvasx, 'y' => (float)$entry->canvasy]
                : null,
        ];
    }

    return $participants;
}

function gm_save_member($cmid, $userid, $mutator, $DB) {
    $row = $DB->get_record('toolio_gt_member', ['coursemoduleid' => $cmid, 'userid' => $userid]);
    if (!$row) {
        $row = (object)[
            'coursemoduleid' => $cmid,
            'userid' => $userid,
            'active' => 1,
            'groupid' => null,
            'groupstableid' => null,
            'grouporder' => 0,
            'groupmode' => null,
            'canvasx' => null,
            'canvasy' => null,
            'timemodified' => time(),
        ];
        $row->id = $DB->insert_record('toolio_gt_member', $row);
    }

    $mutator($row);
    $row->timemodified = time();
    $DB->update_record('toolio_gt_member', $row);
}

function gm_normalize_state($cmid, $coursecontext, $DB) {
    $state = gm_get_state($cmid, $DB);
    $participants = gm_get_members($cmid, $coursecontext, $DB);

    $stableids = json_decode((string)$state->groupstableidsjson, true);
    if (!is_array($stableids)) {
        $stableids = [];
    }

    $labels = json_decode((string)$state->grouplabelsjson, true);
    if (!is_array($labels)) {
        $labels = [];
    }

    while (count($stableids) < (int)$state->groupcount) {
        $stableids[] = random_string(20);
    }

    if (count($stableids) > (int)$state->groupcount) {
        $stableids = array_slice($stableids, 0, (int)$state->groupcount);
    }

    $nextlabels = [];
    for ($i = 0; $i < (int)$state->groupcount; $i++) {
        $groupid = 'group-' . ($i + 1);
        $raw = isset($labels[$groupid]) ? trim((string)$labels[$groupid]) : '';
        $nextlabels[$groupid] = $raw !== '' ? $raw : ('Gruppe ' . ($i + 1));
    }

    $state->groupstableidsjson = json_encode(array_values($stableids));
    $state->grouplabelsjson = json_encode($nextlabels);
    $state->timemodified = time();
    $DB->update_record('toolio_gt_state', $state);

    $groups = [];
    for ($i = 0; $i < (int)$state->groupcount; $i++) {
        $groupid = 'group-' . ($i + 1);
        $groups[] = [
            'groupId' => $groupid,
            'stableId' => (string)$stableids[$i],
            'label' => (string)$nextlabels[$groupid],
            'members' => [],
            'capacity' => (int)ceil(max(1, count($participants)) / max(1, (int)$state->groupcount)),
        ];
    }

    foreach ($participants as $participant) {
        if (!$participant['active'] || !$participant['groupId']) {
            continue;
        }

        $idx = (int)substr($participant['groupId'], 6) - 1;
        if ($idx < 0 || $idx >= count($groups)) {
            continue;
        }

        $groups[$idx]['members'][] = [
            'participantId' => $participant['participantId'],
            'name' => $participant['name'],
            'groupOrder' => (int)$participant['groupOrder'],
        ];
    }

    foreach ($groups as &$group) {
        usort($group['members'], static function($a, $b) {
            if ($a['groupOrder'] === $b['groupOrder']) {
                return strcmp($a['participantId'], $b['participantId']);
            }
            return $a['groupOrder'] <=> $b['groupOrder'];
        });

        $group['members'] = array_map(static function($m) {
            return ['participantId' => $m['participantId'], 'name' => $m['name']];
        }, $group['members']);
    }

    return [
        'state' => $state,
        'participants' => $participants,
        'groups' => $groups,
    ];
}

function gm_emit_payload($cmid, $coursecontext, $DB) {
    gm_ensure_member_rows($cmid, $coursecontext, $DB);
    $data = gm_normalize_state($cmid, $coursecontext, $DB);

    return [
        'participants' => array_map(static function($p) {
            return [
                'participantId' => $p['participantId'],
                'name' => $p['name'],
                'groupId' => $p['active'] ? $p['groupId'] : null,
                'canvasPosition' => $p['active'] ? $p['canvasPosition'] : null,
                'active' => $p['active'],
            ];
        }, $data['participants']),
        'groups' => [
            'groupCount' => (int)$data['state']->groupcount,
            'groupMode' => (string)$data['state']->groupmode,
            'totalParticipants' => count(array_filter($data['participants'], static fn($p) => $p['active'])),
            'groups' => $data['groups'],
        ],
        'statemeta' => [
            'version' => (int)$data['state']->stateversion,
            'boardstatejson' => (string)$data['state']->boardstatejson,
        ],
    ];
}

$participantid = optional_param('participantId', '', PARAM_ALPHANUMEXT);
$groupid = optional_param('groupId', '', PARAM_ALPHANUMEXT);
$mode = optional_param('mode', '', PARAM_ALPHA);
$label = optional_param('label', '', PARAM_TEXT);
$x = optional_param('x', null, PARAM_FLOAT);
$y = optional_param('y', null, PARAM_FLOAT);

if ($action === 'init' || $action === 'rtc:state_load') {
    $payload = gm_emit_payload($cm->id, $coursecontext, $DB);
    gm_send_json(['ok' => true, 'payload' => $payload]);
}

$mutated = false;

switch ($action) {
    case 'teacher:participant:deactivate':
    case 'teacher:participant:remove':
        if ($participantid === '') {
            gm_send_json(['ok' => false, 'message' => 'missing participant id'], 400);
        }

        $uid = (int)$participantid;
        gm_save_member($cm->id, $uid, static function($row) {
            $nextactive = ((int)$row->active === 1) ? 0 : 1;
            $row->active = $nextactive;
            if ($nextactive === 0) {
                $row->groupid = null;
                $row->groupstableid = null;
                $row->grouporder = 0;
                $row->canvasx = null;
                $row->canvasy = null;
            }
        }, $DB);
        $mutated = true;
        break;

    case 'teacher:participant:unassign':
        $uid = (int)$participantid;
        gm_save_member($cm->id, $uid, static function($row) {
            $row->groupid = null;
            $row->groupstableid = null;
            $row->grouporder = 0;
        }, $DB);
        $mutated = true;
        break;

    case 'teacher:participant:assignToGroup':
        $uid = (int)$participantid;
        $gid = trim($groupid);
        gm_ensure_groupcount_for_groupid($cm->id, $gid, $DB);
        gm_save_member($cm->id, $uid, static function($row) use ($gid) {
            $row->groupid = $gid;
            $row->canvasx = null;
            $row->canvasy = null;
        }, $DB);
        $mutated = true;
        break;

    case 'teacher:participant:placeOnCanvas':
        $uid = (int)$participantid;
        $cx = max(0, min(1, (float)$x));
        $cy = max(0, min(1, (float)$y));
        gm_save_member($cm->id, $uid, static function($row) use ($cx, $cy) {
            $row->groupid = null;
            $row->groupstableid = null;
            $row->grouporder = 0;
            $row->canvasx = $cx;
            $row->canvasy = $cy;
        }, $DB);
        $mutated = true;
        break;

    case 'teacher:group:increment':
        $state = gm_get_state($cm->id, $DB);
        $state->groupcount = min(50, (int)$state->groupcount + 1);
        $state->timemodified = time();
        $DB->update_record('toolio_gt_state', $state);
        $mutated = true;
        break;

    case 'teacher:group:decrement':
        $state = gm_get_state($cm->id, $DB);
        $state->groupcount = max(0, (int)$state->groupcount - 1);
        $state->timemodified = time();
        $DB->update_record('toolio_gt_state', $state);
        $mutated = true;
        break;

    case 'teacher:group:rename':
        $state = gm_get_state($cm->id, $DB);
        gm_ensure_groupcount_for_groupid($cm->id, $groupid, $DB);
        $state = gm_get_state($cm->id, $DB);
        $labels = json_decode((string)$state->grouplabelsjson, true);
        if (!is_array($labels)) {
            $labels = [];
        }

        $clean = trim(core_text::substr($label, 0, 80));
        $labels[$groupid] = $clean;
        $state->grouplabelsjson = json_encode($labels);
        $state->timemodified = time();
        $DB->update_record('toolio_gt_state', $state);
        $mutated = true;
        break;

    case 'teacher:group:togglePartnerMode':
        $state = gm_get_state($cm->id, $DB);
        $state->groupmode = ($mode === 'partner') ? 'partner' : 'groups';
        $state->timemodified = time();
        $DB->update_record('toolio_gt_state', $state);
        $mutated = true;
        break;

    case 'teacher:group:autoAssign':
        $state = gm_get_state($cm->id, $DB);
        $state->groupmode = ($mode === 'partner') ? 'partner' : 'groups';
        $DB->update_record('toolio_gt_state', $state);

        $all = gm_get_members($cm->id, $coursecontext, $DB);
        $active = array_values(array_filter($all, static fn($p) => $p['active']));
        shuffle($active);

        if ($state->groupmode === 'partner') {
            $groupcount = count($active) > 1 ? (int)floor(count($active) / 2) : 0;
            $state->groupcount = $groupcount;
            $DB->update_record('toolio_gt_state', $state);
        }

        $groupcount = (int)$state->groupcount;
        if ($groupcount > 0) {
            $orderbygroup = array_fill(0, $groupcount, 0);
            foreach ($active as $index => $p) {
                $gindex = $index % $groupcount;
                $gid = 'group-' . ($gindex + 1);
                $order = $orderbygroup[$gindex]++;
                gm_save_member($cm->id, (int)$p['participantId'], static function($row) use ($gid, $order) {
                    $row->groupid = $gid;
                    $row->grouporder = $order;
                    $row->canvasx = null;
                    $row->canvasy = null;
                }, $DB);
            }
        }
        $mutated = true;
        break;

    case 'teacher:resetParticipants':
        $DB->set_field('toolio_gt_member', 'groupid', null, ['coursemoduleid' => $cm->id]);
        $DB->set_field('toolio_gt_member', 'groupstableid', null, ['coursemoduleid' => $cm->id]);
        $DB->set_field('toolio_gt_member', 'grouporder', 0, ['coursemoduleid' => $cm->id]);
        $DB->set_field('toolio_gt_member', 'canvasx', null, ['coursemoduleid' => $cm->id]);
        $DB->set_field('toolio_gt_member', 'canvasy', null, ['coursemoduleid' => $cm->id]);
        $mutated = true;
        break;

    case 'teacher:group:merge':
        $sourcegroupid = optional_param('sourceGroupId', '', PARAM_ALPHANUMEXT);
        $targetgroupid = optional_param('targetGroupId', '', PARAM_ALPHANUMEXT);
        gm_ensure_groupcount_for_groupid($cm->id, $sourcegroupid, $DB);
        gm_ensure_groupcount_for_groupid($cm->id, $targetgroupid, $DB);

        if ($sourcegroupid !== '' && $targetgroupid !== '' && $sourcegroupid !== $targetgroupid) {
            $rows = $DB->get_records('toolio_gt_member', ['coursemoduleid' => $cm->id, 'groupid' => $sourcegroupid]);
            $nextorder = count($DB->get_records('toolio_gt_member', ['coursemoduleid' => $cm->id, 'groupid' => $targetgroupid]));
            foreach ($rows as $row) {
                $row->groupid = $targetgroupid;
                $row->grouporder = $nextorder++;
                $row->timemodified = time();
                $DB->update_record('toolio_gt_member', $row);
            }
            $mutated = true;
        }
        break;

    case 'teacher:group:createFromPair':
        $sourceparticipantid = optional_param('sourceParticipantId', '', PARAM_ALPHANUMEXT);
        $targetparticipantid = optional_param('targetParticipantId', '', PARAM_ALPHANUMEXT);

        if ($sourceparticipantid !== '' && $targetparticipantid !== '') {
            $state = gm_get_state($cm->id, $DB);
            $state->groupcount = min(50, (int)$state->groupcount + 1);
            $state->timemodified = time();
            $DB->update_record('toolio_gt_state', $state);
            $newgroupid = 'group-' . $state->groupcount;

            gm_save_member($cm->id, (int)$sourceparticipantid, static function($row) use ($newgroupid) {
                $row->groupid = $newgroupid;
                $row->grouporder = 0;
                $row->canvasx = null;
                $row->canvasy = null;
            }, $DB);
            gm_save_member($cm->id, (int)$targetparticipantid, static function($row) use ($newgroupid) {
                $row->groupid = $newgroupid;
                $row->grouporder = 1;
                $row->canvasx = null;
                $row->canvasy = null;
            }, $DB);
            $mutated = true;
        }
        break;

    case 'teacher:debug:addFakeParticipants':
        // Ignored in Moodle context.
        break;

    default:
        gm_send_json(['ok' => false, 'message' => 'Unknown action'], 400);
}

if ($mutated) {
    gm_bump_state_version($cm->id, $DB);
}

$payload = gm_emit_payload($cm->id, $coursecontext, $DB);
gm_send_json(['ok' => true, 'payload' => $payload]);
