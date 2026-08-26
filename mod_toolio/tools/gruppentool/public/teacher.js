const socket = io();

const teacherShell = document.querySelector(".teacher-shell");
const sessionInfo = document.getElementById("sessionInfo");
const errorMessage = document.getElementById("errorMessage");
const topbar = document.querySelector(".topbar");

const whiteboardCanvas = document.getElementById("whiteboardCanvas");
const whiteboardGroupLayer = document.getElementById("whiteboardGroupLayer");
const whiteboardConnectorLayer = document.getElementById("whiteboardConnectorLayer");
const whiteboardLooseLayer = document.getElementById("whiteboardLooseLayer");
const whiteboardEmptyState = document.getElementById("whiteboardEmptyState");

const participantsPanel = document.getElementById("participantsPanel");
const participantsToggleButton = document.getElementById("participantsToggleButton");
const participantsHeaderLabel = document.getElementById("participantsHeaderLabel");
const participantsList = document.getElementById("participantsList");
const autoAssignButton = document.getElementById("autoAssignButton");

const groupModeSwitchButton = document.getElementById("groupModeSwitchButton");
const groupModeSwitchPairs = document.getElementById("groupModeSwitchPairs");
const groupModeSwitchGroups = document.getElementById("groupModeSwitchGroups");

const groupControlBar = document.querySelector(".group-control-bar");
const groupMinusButton = document.getElementById("groupMinusButton");
const groupCountButton = document.getElementById("groupCountButton");
const groupPlusButton = document.getElementById("groupPlusButton");
const MAX_PARTICIPANTS = 50;
const MAX_GROUPS = 50;
const GROUP_CIRCLE_RADIUS = 39;
const DOCK_RING_RADIUS = 108;
const DOCK_PREVIEW_MAX_DISTANCE = DOCK_RING_RADIUS + 46;
const DOCK_PREVIEW_CAPTURE_MARGIN = 44;
const DOCK_PREVIEW_RELEASE_MARGIN = 66;
const SWAP_CAPTURE_DISTANCE = 30;
const PAIR_SWAP_CAPTURE_DISTANCE = 22;
const PAIR_SWAP_RELEASE_DISTANCE = 30;
const PAIR_SWAP_CONFIRM_MS = 110;
const PAIR_MODE_LOCK_MS = 140;
const SLOT_SWITCH_HYSTERESIS_RATIO = 0.28;
const PAIR_PREVIEW_DEAD_ZONE = 14;
const GROUP_MEMBER_SPACING = 80;
const GROUP_SETTLE_FACTOR_IDLE = 0.2;
const GROUP_SETTLE_FACTOR_DRAG = 0.34;
const GROUP_SETTLE_EPSILON = 0.6;
const CONNECTOR_START_GAP = GROUP_CIRCLE_RADIUS + 10;
const CONNECTOR_END_GAP = 24;
const PAIR_CONNECTOR_END_GAP = 18;
const GROUP_MIN_SPACING = 24;
const GROUP_LAYOUT_PADDING = 54;
const FORCE_PROTOTYPE_BOARD = true;

const moodleConfig = window.GM_MOODLE || {};
const activityLabel = `Aktivitaet ${String(moodleConfig.cmid || "")}`;

const viewState = {
  participants: [],
  groups: [],
  groupLayout: [],
  groupAnchorsByStableId: {},
  groupRenderCentersByStableId: {},
  groupStartAngleByStableId: {},
  groupMemberSlotsByStableId: {},
  groupCount: 0,
  groupMode: "groups",
  totalParticipants: 0,
  participantsPanelOpen: true,
  behaviorV2Enabled: false,
};

const dragState = {
  participantId: null,
  origin: null,
  preview: null,
  pendingGroupInsertHint: null,
  connectionTargetId: null,
  skipFlipParticipantId: null,
  persistPreviewUntilGroupsUpdate: false,
};

const prototypeBoard = {
  circles: new Map(),
  groups: [],
  preview: null,
  activeDragCircleId: null,
  groupMergeTargetId: null,
  groupDragSourceId: null,
  groupHoverSourceId: null,
  nextGroupId: 1,
  desiredGroupCount: 0,
  frameRequested: false,
  rafId: null,
  swapAnimRafId: null,
  swapAnimToken: 0,
  pairSwapCandidate: null,
  pairModeLock: null,
  canvasEl: null,
  canvasCtx: null,
};

let groupSettleRafId = null;

function setError(message) {
  errorMessage.textContent = message;
  errorMessage.hidden = false;
}

function clearError() {
  errorMessage.hidden = true;
}

function getParticipantName(participant) {
  if (typeof participant === "string") {
    return participant.trim() || "Unbenannt";
  }

  if (participant && typeof participant === "object") {
    return String(participant.name || participant.displayName || participant.username || "").trim() || "Unbenannt";
  }

  return "Unbenannt";
}

function hashString(value) {
  const text = String(value || "");
  // FNV-1a style hashing distributes sequential ids more evenly.
  let hash = 2166136261;
  for (let index = 0; index < text.length; index += 1) {
    hash ^= text.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }
  return hash >>> 0;
}

function getParticipantColor(participantId) {
  const hash = hashString(participantId);
  const hue = hash % 360;
  const saturation = 68 + ((hash >>> 8) % 20);
  const lightness = 48 + ((hash >>> 16) % 10);
  return `hsl(${hue}, ${saturation}%, ${lightness}%)`;
}

function getParticipantInitials(name) {
  const safeName = String(name || "").trim();
  if (!safeName) {
    return "?";
  }

  const words = safeName.split(/\s+/).filter(Boolean);
  if (words.length === 1) {
    return words[0].slice(0, 1).toUpperCase();
  }

  return `${words[0].slice(0, 1)}${words[1].slice(0, 1)}`.toUpperCase();
}

function syncTopbarHeight() {
  const topbarHeight = Math.max(0, Math.round(topbar?.getBoundingClientRect().height || 0));
  if (!topbarHeight) {
    return;
  }

  document.documentElement.style.setProperty("--topbar-height", `${topbarHeight}px`);
}

function updateCanvasInsets() {
  if (!teacherShell) {
    return;
  }

  const leftInset = viewState.participantsPanelOpen
    ? Math.max(0, Math.round(participantsPanel?.getBoundingClientRect().width || 0))
    : 0;
  const rightInset = 0;

  teacherShell.style.setProperty("--canvas-left-offset", `${leftInset}px`);
  teacherShell.style.setProperty("--canvas-right-offset", `${rightInset}px`);
}

function normalizeParticipants(participants) {
  const source = Array.isArray(participants) ? participants : [];
  return source.map((entry, index) => ({
    participantId: String(entry?.participantId || `participant-${index}`),
    name: getParticipantName(entry),
    active: entry?.active !== false,
    groupId: entry?.groupId ? String(entry.groupId) : null,
    canvasPosition:
      Number.isFinite(Number(entry?.canvasPosition?.x)) && Number.isFinite(Number(entry?.canvasPosition?.y))
        ? {
            x: Math.max(0, Math.min(1, Number(entry.canvasPosition.x))),
            y: Math.max(0, Math.min(1, Number(entry.canvasPosition.y))),
          }
        : null,
  }));
}

function getGroupIdFromIndex(index) {
  return `group-${index + 1}`;
}

function normalizeGroups(payload) {
  const sourceGroups = Array.isArray(payload?.groups) ? payload.groups : [];
  const normalizedGroups = sourceGroups.map((group, index) => ({
    groupId: String(group?.groupId || `group-${index + 1}`),
    stableId: String(group?.stableId || group?.groupId || `group-${index + 1}`),
    label: String(group?.label || `Gruppe ${index + 1}`),
    capacity: Math.max(0, Number(group?.capacity) || 0),
    members: Array.isArray(group?.members)
      ? group.members.map((member, memberIndex) => ({
          participantId: String(member?.participantId || `${group?.groupId || "group"}-member-${memberIndex}`),
          name: getParticipantName(member),
        }))
      : [],
  }));

  return {
    groupCount: Math.max(0, Number(payload?.groupCount) || 0),
    groupMode: String(payload?.groupMode || "groups") === "partner" ? "partner" : "groups",
    totalParticipants: Math.max(0, Number(payload?.totalParticipants) || viewState.participants.length),
    groups: normalizedGroups,
  };
}

function renderGroupControls() {
  if (FORCE_PROTOTYPE_BOARD) {
    const participantCount = Math.max(0, Number(viewState.participants?.length) || 0);
    const isPartnerMode = viewState.groupMode === "partner";
    const maxGroups = MAX_GROUPS;
    const serverGroupCount = clampToBounds(Number(viewState.groupCount) || 0, 0, maxGroups);
    const currentGroupCount = clampToBounds(Number(prototypeBoard.groups?.length) || 0, 0, maxGroups);
    prototypeBoard.desiredGroupCount = clampToBounds(
      Number(viewState.groupCount) || Number(prototypeBoard.desiredGroupCount) || currentGroupCount,
      0,
      maxGroups
    );

    const effectiveCount = serverGroupCount > 0 ? serverGroupCount : currentGroupCount;

    groupCountButton.textContent = `${effectiveCount} Gruppen`;
    groupCountButton.title = "Aktuelle Anzahl Gruppen";

    groupModeSwitchButton.setAttribute("aria-checked", String(isPartnerMode));
    groupModeSwitchButton.classList.toggle("is-partner", isPartnerMode);
    groupModeSwitchButton.classList.toggle("is-groups", !isPartnerMode);
    groupModeSwitchPairs.classList.toggle("is-active", isPartnerMode);
    groupModeSwitchGroups.classList.toggle("is-active", !isPartnerMode);
    groupControlBar?.classList.remove("hidden");
    groupMinusButton.disabled = effectiveCount <= 0;
    groupPlusButton.disabled = effectiveCount >= maxGroups;
    autoAssignButton.disabled = isPartnerMode
      ? participantCount < 2
      : participantCount <= 0 || effectiveCount <= 0;
    autoAssignButton.title = isPartnerMode
      ? "Teilnehmende als Partnerteams zuordnen"
      : "Teilnehmende gleichmäßig auf Gruppen verteilen";
    return;
  }

  const count = viewState.groupCount;
  const isPartnerMode = viewState.groupMode === "partner";
  groupCountButton.textContent = `${count} Gruppen`;
  groupCountButton.title = "Aktuelle Anzahl Gruppen";
  autoAssignButton.title = isPartnerMode
    ? "Teilnehmende als Partnerteams zuordnen"
    : "Teilnehmende auf vorhandene Gruppen verteilen";

  groupModeSwitchButton.setAttribute("aria-checked", String(isPartnerMode));
  groupModeSwitchButton.classList.toggle("is-partner", isPartnerMode);
  groupModeSwitchButton.classList.toggle("is-groups", !isPartnerMode);
  groupModeSwitchPairs.classList.toggle("is-active", isPartnerMode);
  groupModeSwitchGroups.classList.toggle("is-active", !isPartnerMode);
  groupControlBar?.classList.remove("hidden");
  groupMinusButton.disabled = count <= 0;
  groupPlusButton.disabled = count >= MAX_GROUPS;
  autoAssignButton.disabled = isPartnerMode
    ? viewState.totalParticipants < 2
    : count <= 0 || viewState.totalParticipants <= 0;
}

function getUnassignedParticipants() {
  return viewState.participants
    .filter((participant) => {
      if (participant.active === false) {
        return true;
      }

      return !participant.groupId && !participant.canvasPosition;
    })
    .slice()
    .sort((a, b) => {
      if (a.active !== b.active) {
        return a.active ? -1 : 1;
      }
      return a.name.localeCompare(b.name);
    });
}

function getCanvasParticipants() {
  return viewState.participants
    .filter((participant) => participant.active !== false && !participant.groupId && participant.canvasPosition)
    .sort((a, b) => a.name.localeCompare(b.name));
}

function setParticipantsPanelOpen(isOpen) {
  viewState.participantsPanelOpen = Boolean(isOpen);
  participantsPanel.classList.toggle("is-open", viewState.participantsPanelOpen);
  participantsToggleButton.setAttribute("aria-expanded", String(viewState.participantsPanelOpen));
  participantsToggleButton.setAttribute(
    "aria-label",
    viewState.participantsPanelOpen ? "Teilnehmende ausblenden" : "Teilnehmende anzeigen"
  );
  updateCanvasInsets();
}

function renderParticipantsPanel() {
  const countText = `${viewState.totalParticipants}/${MAX_PARTICIPANTS}`;
  if (participantsHeaderLabel) {
    participantsHeaderLabel.textContent = `Teilnehmende ${countText}`;
  }
  participantsList.innerHTML = "";

  const unassigned = getUnassignedParticipants();
  if (unassigned.length === 0) {
    const empty = document.createElement("li");
    empty.className = "participants-item participants-item-empty";
    empty.textContent = "Keine verfuegbaren Teilnehmenden";
    participantsList.appendChild(empty);
    return;
  }

  unassigned.forEach((participant) => {
    const item = document.createElement("li");
    const isInactive = participant.active === false;
    item.className = `participants-item ${isInactive ? "participants-item-inactive" : "participants-item-draggable"}`;

    const content = document.createElement("div");
    content.className = "participants-item-content";

    const avatar = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    avatar.setAttribute("class", "participant-avatar-icon");
    avatar.setAttribute("viewBox", "0 0 24 24");
    avatar.setAttribute("aria-hidden", "true");

    const avatarPath = document.createElementNS("http://www.w3.org/2000/svg", "path");
    avatarPath.setAttribute(
      "d",
      "M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-4.42 0-8 2.02-8 4.5V21h16v-2.5c0-2.48-3.58-4.5-8-4.5Z"
    );
    avatarPath.setAttribute("fill", "currentColor");
    avatar.appendChild(avatarPath);

    const name = document.createElement("span");
    name.className = "participant-name";
    name.textContent = participant.name;

    const presenceButton = document.createElement("button");
    presenceButton.className = "participant-remove-button";
    presenceButton.type = "button";
    presenceButton.setAttribute(
      "aria-label",
      isInactive
        ? `${participant.name} als anwesend markieren`
        : `${participant.name} als abwesend markieren`
    );
    presenceButton.title = isInactive ? "Anwesend" : "Abwesend";
    presenceButton.innerHTML = isInactive ? `
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M12 5c4.8 0 8.6 3 10 7-0.53 1.51-1.45 2.87-2.66 3.98M9.88 7.1c0.69-0.2 1.4-0.3 2.12-0.3 4.8 0 8.6 3 10 7-1.4 4-5.2 7-10 7-4.8 0-8.6-3-10-7 0.56-1.6 1.55-3.03 2.86-4.16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    ` : `
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7-10-7-10-7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
      </svg>
    `;

    presenceButton.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      socket.emit("teacher:participant:deactivate", {
        participantId: participant.participantId,
      });
    });

    presenceButton.addEventListener("dragstart", (event) => {
      event.preventDefault();
      event.stopPropagation();
    });

    content.appendChild(avatar);
    content.appendChild(name);
    content.appendChild(presenceButton);
    item.appendChild(content);
    item.draggable = !isInactive;
    item.dataset.participantId = participant.participantId;

    item.addEventListener("dragstart", (event) => {
      if (isInactive) {
        event.preventDefault();
        return;
      }

      dragState.participantId = participant.participantId;
      dragState.origin = "list";
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", participant.participantId);
    });

    item.addEventListener("dragend", () => {
      dragState.participantId = null;
      dragState.origin = null;
      whiteboardCanvas.classList.remove("is-drop-active");
      if (!dragState.persistPreviewUntilGroupsUpdate) {
        clearDragPreview();
      }
      setConnectionTarget(null);
    });

    participantsList.appendChild(item);
  });
}

function getDraggedParticipantId(event) {
  const fromTransfer = String(event?.dataTransfer?.getData("text/plain") || "").trim();
  if (fromTransfer) {
    return fromTransfer;
  }

  return dragState.participantId;
}

function getCanvasDropPosition(clientX, clientY) {
  const rect = whiteboardCanvas.getBoundingClientRect();
  const rawX = (Number(clientX) - rect.left) / Math.max(1, rect.width);
  const rawY = (Number(clientY) - rect.top) / Math.max(1, rect.height);
  return {
    x: Math.max(0.03, Math.min(0.97, rawX)),
    y: Math.max(0.04, Math.min(0.96, rawY)),
  };
}

function getCanvasPoint(clientX, clientY) {
  const rect = whiteboardCanvas.getBoundingClientRect();
  return {
    x: Number(clientX) - rect.left,
    y: Number(clientY) - rect.top,
  };
}

function getNearestGroupLayout(point) {
  if (!point || viewState.groupLayout.length === 0) {
    return null;
  }

  let nearest = null;
  let nearestDistance = Number.POSITIVE_INFINITY;

  viewState.groupLayout.forEach((entry) => {
    const dx = point.x - entry.center.x;
    const dy = point.y - entry.center.y;
    const distance = (dx * dx) + (dy * dy);
    if (distance < nearestDistance) {
      nearestDistance = distance;
      nearest = entry;
    }
  });

  if (!nearest) {
    return null;
  }

  return {
    ...nearest,
    distance: Math.sqrt(nearestDistance),
  };
}

function getDockEndpoint(center, targetPoint, distance) {
  const dx = targetPoint.x - center.x;
  const dy = targetPoint.y - center.y;
  const length = Math.hypot(dx, dy) || 1;
  const ux = dx / length;
  const uy = dy / length;
  return {
    x: center.x + ux * distance,
    y: center.y + uy * distance,
  };
}

function getDockRadius(layoutEntry) {
  return Math.max(56, Math.min(DOCK_RING_RADIUS, Number(layoutEntry?.radius) || DOCK_RING_RADIUS));
}

// Snap-Reichweiten an die aktuelle Canvasgroesse koppeln: auf kleinen Boards
// enger, auf grossen Boards etwas weiter. Um 1.0 fuer typische Boardgroessen.
function prototypeSnapScale() {
  const bounds = prototypeGetBounds();
  const minDimension = Math.min(bounds.width, bounds.height);
  return clampToBounds(minDimension / 460, 0.85, 1.25);
}

function dockCaptureMargin() {
  return DOCK_PREVIEW_CAPTURE_MARGIN * prototypeSnapScale();
}

function dockReleaseMargin() {
  return DOCK_PREVIEW_RELEASE_MARGIN * prototypeSnapScale();
}

function isBehaviorV2Enabled() {
  return Boolean(viewState.behaviorV2Enabled);
}

function getPreviewCaptureDistance(layoutEntry) {
  return getDockRadius(layoutEntry) + dockCaptureMargin();
}

function getPreviewReleaseDistance(layoutEntry) {
  return getDockRadius(layoutEntry) + dockReleaseMargin();
}

function getGroupById(groupId) {
  return viewState.groups.find((group) => String(group.groupId) === String(groupId));
}

function getGroupLayoutById(groupId) {
  return viewState.groupLayout.find((layout) => String(layout.groupId) === String(groupId));
}

function getGroupStartAngle(stableId) {
  const stored = viewState.groupStartAngleByStableId[stableId];
  return Number.isFinite(stored) ? stored : (-Math.PI / 2);
}

function getPreviewSlotIndex(total, incomingAngle, previousSlotIndex) {
  if (!Number.isFinite(total) || total <= 0) {
    return 0;
  }

  const startAngle = -Math.PI / 2;
  const step = (Math.PI * 2) / total;
  let bestIndex = 0;
  let bestDelta = Number.POSITIVE_INFINITY;
  const deltas = [];

  for (let index = 0; index < total; index += 1) {
    const angle = startAngle + (index * step);
    const delta = Math.abs(Math.atan2(Math.sin(incomingAngle - angle), Math.cos(incomingAngle - angle)));
    deltas[index] = delta;
    if (delta < bestDelta) {
      bestDelta = delta;
      bestIndex = index;
    }
  }

  if (Number.isInteger(previousSlotIndex) && previousSlotIndex >= 0 && previousSlotIndex < total) {
    const previousDelta = deltas[previousSlotIndex];
    const hysteresis = step * SLOT_SWITCH_HYSTERESIS_RATIO;
    if (bestIndex !== previousSlotIndex && bestDelta + hysteresis >= previousDelta) {
      return previousSlotIndex;
    }
  }

  return bestIndex;
}

function shouldKeepCurrentGroupPreview(pointer, participant) {
  const preview = dragState.preview;
  if (!isBehaviorV2Enabled() || !preview?.groupId || !pointer) {
    return false;
  }

  if (participant?.groupId && participant.groupId === preview.groupId) {
    return false;
  }

  const layout = getGroupLayoutById(preview.groupId);
  if (!layout?.center) {
    return false;
  }

  const distance = Math.hypot(pointer.x - layout.center.x, pointer.y - layout.center.y);
  return distance <= getPreviewReleaseDistance(layout);
}

function getMemberAngle(index, total, isPairHorizontal) {
  if (isPairHorizontal && total === 2) {
    return index === 0 ? Math.PI : 0;
  }

  return (-Math.PI / 2) + ((index / Math.max(1, total)) * Math.PI * 2);
}

function getRingRadiusForCount(count) {
  const safeCount = Math.max(0, Number(count) || 0);
  if (safeCount <= 1) {
    return 0;
  }

  return GROUP_MEMBER_SPACING / (2 * Math.sin(Math.PI / safeCount));
}

function lerp(a, b, t) {
  return a + ((b - a) * t);
}

function getLayoutPoint(center, radius, angle, bounds) {
  const x = center.x + Math.cos(angle) * radius;
  const y = center.y + Math.sin(angle) * radius;
  return {
    x: Math.max(20, Math.min(bounds.width - 20, x)),
    y: Math.max(24, Math.min(bounds.height - 24, y)),
  };
}

function smoothStep01(value) {
  const clamped = clampToBounds(value, 0, 1);
  return clamped * clamped * (3 - (2 * clamped));
}

function scheduleGroupSettleRender() {
  if (groupSettleRafId !== null) {
    return;
  }

  groupSettleRafId = window.requestAnimationFrame(() => {
    groupSettleRafId = null;
    renderGroups();
  });
}

function cancelGroupSettleRender() {
  if (groupSettleRafId === null) {
    return;
  }

  window.cancelAnimationFrame(groupSettleRafId);
  groupSettleRafId = null;
}

function setDragPreview(preview) {
  dragState.preview = preview;
  renderDragPreview();
}

function clearDragPreview() {
  dragState.preview = null;
  renderDragPreview();
}

function renderDragPreview() {
  whiteboardGroupLayer
    .querySelectorAll(".group-flower.is-opening")
    .forEach((entry) => {
      entry.classList.remove("is-opening");
      entry.style.removeProperty("--preview-strength");
      entry.style.removeProperty("--preview-angle");
    });

  const preview = dragState.preview;
  if (!preview) {
    return;
  }

  const targetFlower = whiteboardGroupLayer.querySelector(
    `.group-flower[data-group-id="${preview.groupId}"]`
  );
  const previewStrength = clampToBounds(Number(preview.strength) || 0, 0, 1);
  if (targetFlower) {
    targetFlower.classList.add("is-opening");
    targetFlower.style.setProperty("--preview-strength", previewStrength.toFixed(3));
    if (preview.center && preview.endpoint) {
      const previewAngle = Math.atan2(preview.endpoint.y - preview.center.y, preview.endpoint.x - preview.center.x);
      targetFlower.style.setProperty("--preview-angle", `${previewAngle}rad`);
    }
  }
}

function setConnectionTarget(targetParticipantId) {
  const nextId = targetParticipantId ? String(targetParticipantId) : null;
  if (dragState.connectionTargetId === nextId) {
    return;
  }

  if (dragState.connectionTargetId) {
    const previousTarget = whiteboardLooseLayer.querySelector(
      `.canvas-participant[data-participant-id="${dragState.connectionTargetId}"]`
    );
    previousTarget?.classList.remove("is-connection-target");
  }

  dragState.connectionTargetId = nextId;
  if (!dragState.connectionTargetId) {
    return;
  }

  const nextTarget = whiteboardLooseLayer.querySelector(
    `.canvas-participant[data-participant-id="${dragState.connectionTargetId}"]`
  );
  nextTarget?.classList.add("is-connection-target");
}

function getParticipantDropTarget(event, draggedParticipantId) {
  if (!(event.target instanceof Element)) {
    return null;
  }

  const targetCard = event.target.closest(".canvas-participant");
  if (!targetCard) {
    return null;
  }

  const targetParticipantId = String(targetCard.dataset.participantId || "").trim();
  if (!targetParticipantId || targetParticipantId === draggedParticipantId) {
    return null;
  }

  return targetParticipantId;
}

function renderEmptyState() {
  if (FORCE_PROTOTYPE_BOARD) {
    whiteboardEmptyState.hidden = prototypeBoard.circles.size > 0;
    return;
  }

  const hasGroups = Array.isArray(viewState.groups) && viewState.groups.length > 0;
  const hasCanvasParticipants = getCanvasParticipants().length > 0;
  const hasGroupedParticipants = getGroupedParticipants().length > 0;
  whiteboardEmptyState.hidden = hasGroups || hasCanvasParticipants || hasGroupedParticipants;
}

function createCanvasParticipantElement(participant, canvasPosition) {
  const item = document.createElement("div");
  item.className = "canvas-participant";
  item.draggable = true;
  item.dataset.participantId = participant.participantId;
  item.style.setProperty("--participant-color", getParticipantColor(participant.participantId));
  item.setAttribute("aria-label", participant.name);

  const dot = document.createElement("div");
  dot.className = "canvas-participant-dot";
  dot.setAttribute("aria-hidden", "true");
  dot.textContent = getParticipantInitials(participant.name);

  const label = document.createElement("span");
  label.className = "canvas-participant-label";
  label.textContent = participant.name;

  item.appendChild(dot);
  item.appendChild(label);

  item.style.left = `${Math.round(canvasPosition.x)}px`;
  item.style.top = `${Math.round(canvasPosition.y)}px`;

  item.addEventListener("dragstart", (event) => {
    dragState.participantId = participant.participantId;
    dragState.origin = "canvas";
    item.classList.add("is-dragging");
    if (participant.groupId) {
      whiteboardConnectorLayer
        .querySelectorAll(`.is-docked-connector[data-participant-id="${participant.participantId}"]`)
        .forEach((entry) => entry.classList.add("is-temporarily-hidden"));
    }
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", participant.participantId);

    // Show a clear moving preview while the original card stays hidden in place.
    const dragPreview = item.cloneNode(true);
    dragPreview.classList.remove("is-dragging");
    dragPreview.style.position = "fixed";
    dragPreview.style.top = "-9999px";
    dragPreview.style.left = "-9999px";
    dragPreview.style.opacity = "1";
    dragPreview.style.pointerEvents = "none";
    document.body.appendChild(dragPreview);
    event.dataTransfer.setDragImage(dragPreview, dragPreview.offsetWidth / 2, dragPreview.offsetHeight / 2);
    window.setTimeout(() => {
      dragPreview.remove();
    }, 0);
  });

  item.addEventListener("dragend", () => {
    item.classList.remove("is-dragging");
    dragState.participantId = null;
    dragState.origin = null;
    whiteboardCanvas.classList.remove("is-drop-active");
    if (!dragState.persistPreviewUntilGroupsUpdate) {
      clearDragPreview();
    }
    setConnectionTarget(null);
    whiteboardConnectorLayer
      .querySelectorAll(".is-docked-connector.is-temporarily-hidden")
      .forEach((entry) => entry.classList.remove("is-temporarily-hidden"));
  });

  return item;
}

function getGroupedParticipants() {
  return viewState.participants.filter((participant) => participant.groupId);
}

function buildGroupedParticipantLayout(bounds) {
  const placements = [];
  const pairLinks = [];
  const totalGroupCount = Math.max(1, viewState.groups.length);
  const preview = dragState.preview;

  viewState.groups.forEach((group) => {
    const stableId = String(group.stableId || group.groupId || "");
    const layout = viewState.groupLayout.find((entry) => entry.stableId === stableId || entry.groupId === group.groupId);
    if (!layout || !Array.isArray(group.members) || group.members.length === 0) {
      return;
    }

    const membersById = new Map(
      group.members.map((member) => [String(member.participantId || ""), member])
    );
    const currentMemberIds = Array.from(membersById.keys()).filter(Boolean);
    if (currentMemberIds.length === 0) {
      return;
    }

    const previousSlots = Array.isArray(viewState.groupMemberSlotsByStableId[stableId])
      ? viewState.groupMemberSlotsByStableId[stableId]
      : [];

    const nextSlots = previousSlots.filter((participantId) => membersById.has(participantId));
    const missingMemberIds = currentMemberIds.filter((participantId) => !nextSlots.includes(participantId));
    missingMemberIds.sort((a, b) => a.localeCompare(b));
    missingMemberIds.forEach((participantId) => nextSlots.push(participantId));

    const pendingHint = dragState.pendingGroupInsertHint;
    if (
      isBehaviorV2Enabled() &&
      pendingHint &&
      pendingHint.stableId === stableId &&
      currentMemberIds.includes(pendingHint.participantId)
    ) {
      const hintedPairIds = Array.isArray(pendingHint.pairMemberIds)
        ? pendingHint.pairMemberIds.filter((participantId) => currentMemberIds.includes(participantId))
        : [];

      if (hintedPairIds.length >= 1) {
        const desiredOrder = [pendingHint.participantId];
        if (pendingHint.pairVariant === "bottom") {
          desiredOrder.push(...hintedPairIds.slice().reverse());
          viewState.groupStartAngleByStableId[stableId] = Math.PI / 2;
        } else {
          desiredOrder.push(...hintedPairIds);
          viewState.groupStartAngleByStableId[stableId] = -Math.PI / 2;
        }

        const uniqueDesired = [];
        desiredOrder.forEach((participantId) => {
          if (currentMemberIds.includes(participantId) && !uniqueDesired.includes(participantId)) {
            uniqueDesired.push(participantId);
          }
        });

        const remaining = currentMemberIds.filter((participantId) => !uniqueDesired.includes(participantId));
        nextSlots.splice(0, nextSlots.length, ...uniqueDesired, ...remaining);
      }

      dragState.pendingGroupInsertHint = null;
    }

    viewState.groupMemberSlotsByStableId[stableId] = nextSlots;

    const orderedMembers = nextSlots
      .map((participantId) => membersById.get(participantId))
      .filter(Boolean);

    const memberCount = orderedMembers.length;
    const isPreviewGroup = Boolean(preview && preview.groupId === group.groupId && !preview.isInsideOwnGroup);
    const dockRadius = getDockRadius(layout);
    const maxAllowedRadius = Math.max(34, Math.min(dockRadius, DOCK_RING_RADIUS - Math.max(0, totalGroupCount - 1) * 2));
    const baseRadius = clampToBounds(getRingRadiusForCount(memberCount), 24, maxAllowedRadius);
    let renderStartAngle = getGroupStartAngle(stableId);

    const basePointsById = new Map();
    orderedMembers.forEach((member, index) => {
      const baseAngle = memberCount === 2
        ? getMemberAngle(index, memberCount, true)
        : renderStartAngle + ((index / Math.max(1, memberCount)) * Math.PI * 2);
      basePointsById.set(member.participantId, getLayoutPoint(layout.center, baseRadius, baseAngle, bounds));
    });

    const pointsById = new Map(basePointsById);
    if (isPreviewGroup && preview.endpoint) {
      const blendStrength = clampToBounds(Number(preview.strength) || 0, 0, 1);
      const targetRadius = clampToBounds(getRingRadiusForCount(memberCount + 1), 24, maxAllowedRadius);
      const blendedRadius = lerp(baseRadius, targetRadius, blendStrength);
      let targetStartAngle = renderStartAngle;
      let targetOrderIds = orderedMembers.map((member) => member.participantId);

      if (isBehaviorV2Enabled() && memberCount === 2 && (preview.pairVariant === "top" || preview.pairVariant === "bottom")) {
        if (preview.pairVariant === "bottom") {
          targetOrderIds = ["__preview__", orderedMembers[1].participantId, orderedMembers[0].participantId];
          targetStartAngle = Math.PI / 2;
        } else {
          targetOrderIds = ["__preview__", orderedMembers[0].participantId, orderedMembers[1].participantId];
          targetStartAngle = -Math.PI / 2;
        }
      } else {
        let previewInsertIndex = Math.max(0, Math.min(memberCount, Number(preview.slotIndex) || 0));
        if (!Number.isFinite(previewInsertIndex)) {
          previewInsertIndex = 0;
        }

        targetOrderIds = orderedMembers.map((member) => member.participantId);
        targetOrderIds.splice(previewInsertIndex, 0, "__preview__");
      }

      const totalTargetSlots = targetOrderIds.length;
      orderedMembers.forEach((member) => {
        const memberId = member.participantId;
        const targetIndex = targetOrderIds.indexOf(memberId);
        if (targetIndex < 0) {
          return;
        }

        const targetAngle = targetStartAngle + ((targetIndex / Math.max(1, totalTargetSlots)) * Math.PI * 2);
        const targetPoint = getLayoutPoint(layout.center, blendedRadius, targetAngle, bounds);
        const basePoint = basePointsById.get(memberId) || targetPoint;
        pointsById.set(memberId, {
          x: lerp(basePoint.x, targetPoint.x, blendStrength),
          y: lerp(basePoint.y, targetPoint.y, blendStrength),
        });
      });
    }

    orderedMembers.forEach((member) => {
      const point = pointsById.get(member.participantId);
      if (!point) {
        return;
      }

      placements.push({
        participantId: member.participantId,
        groupId: group.groupId,
        point,
      });
    });

    if (isBehaviorV2Enabled() && !isPreviewGroup) {
      viewState.groupStartAngleByStableId[stableId] = renderStartAngle;
    }

    if (memberCount === 2 && !isPreviewGroup) {
      const firstId = orderedMembers[0].participantId;
      const secondId = orderedMembers[1].participantId;
      const firstPoint = pointsById.get(firstId);
      const secondPoint = pointsById.get(secondId);

      if (firstPoint && secondPoint) {
        pairLinks.push({
          groupId: group.groupId,
          participantIds: [firstId, secondId],
          from: firstPoint,
          to: secondPoint,
        });
      }
    }
  });

  return {
    placements,
    pairLinks,
  };
}

function renderGroupedConnectors(groupedLayout) {
  if (!whiteboardConnectorLayer) {
    return;
  }

  whiteboardConnectorLayer
    .querySelectorAll(".is-docked-connector")
    .forEach((entry) => entry.remove());

  groupedLayout.pairLinks.forEach((pairLink) => {
    if (dragState.participantId && pairLink.participantIds.includes(dragState.participantId)) {
      return;
    }

    const dx = pairLink.to.x - pairLink.from.x;
    const dy = pairLink.to.y - pairLink.from.y;
    const angle = Math.atan2(dy, dx);
    const totalDistance = Math.hypot(dx, dy);
    const visualLength = Math.max(0, totalDistance - (PAIR_CONNECTOR_END_GAP * 2));
    const startX = pairLink.from.x + Math.cos(angle) * PAIR_CONNECTOR_END_GAP;
    const startY = pairLink.from.y + Math.sin(angle) * PAIR_CONNECTOR_END_GAP;

    const line = document.createElement("div");
    line.className = "group-connector-line is-docked is-docked-connector is-pair";
    line.style.left = `${startX}px`;
    line.style.top = `${startY}px`;
    line.style.width = `${Math.round(visualLength)}px`;
    line.style.transform = `rotate(${angle}rad)`;
    whiteboardConnectorLayer.appendChild(line);
  });

  groupedLayout.placements.forEach((placement) => {
    if (dragState.participantId && placement.participantId === dragState.participantId) {
      return;
    }

    const participant = viewState.participants.find((entry) => entry.participantId === placement.participantId);
    if (!participant || !participant.groupId) {
      return;
    }

    const layout = viewState.groupLayout.find((entry) => entry.groupId === participant.groupId);
    if (!layout) {
      return;
    }

    const groupData = viewState.groups.find((group) => group.groupId === participant.groupId);
    if (groupData?.members?.length === 2) {
      return;
    }

    const line = document.createElement("div");
    line.className = "group-connector-line is-docked is-docked-connector";
    line.dataset.participantId = placement.participantId;
    const dx = placement.point.x - layout.center.x;
    const dy = placement.point.y - layout.center.y;
    const angle = Math.atan2(dy, dx);
    const totalDistance = Math.hypot(dx, dy);
    const startGap = Math.min(CONNECTOR_START_GAP, Math.max(10, totalDistance * 0.42));
    const endGap = Math.min(CONNECTOR_END_GAP, Math.max(8, totalDistance * 0.3));
    const visualLength = Math.max(4, totalDistance - startGap - endGap);
    const startX = layout.center.x + Math.cos(angle) * startGap;
    const startY = layout.center.y + Math.sin(angle) * startGap;
    line.style.left = `${startX}px`;
    line.style.top = `${startY}px`;
    line.style.width = `${Math.round(visualLength)}px`;
    line.style.transform = `rotate(${angle}rad)`;
    whiteboardConnectorLayer.appendChild(line);
  });
}

function renderCanvasParticipants() {
  whiteboardLooseLayer.innerHTML = "";
  setConnectionTarget(null);

  const bounds = {
    width: Math.max(320, whiteboardCanvas.clientWidth || 320),
    height: Math.max(320, whiteboardCanvas.clientHeight || 320),
  };

  getCanvasParticipants().forEach((participant) => {
    const canvasPoint = {
      x: participant.canvasPosition.x * bounds.width,
      y: participant.canvasPosition.y * bounds.height,
    };
    whiteboardLooseLayer.appendChild(createCanvasParticipantElement(participant, canvasPoint));
  });

  const groupedLayout = buildGroupedParticipantLayout(bounds);

  groupedLayout.placements.forEach((placement) => {
    const participant = viewState.participants.find((entry) => entry.participantId === placement.participantId);
    if (!participant) {
      return;
    }

    whiteboardLooseLayer.appendChild(createCanvasParticipantElement(participant, placement.point));
  });

  renderDragPreview();
  renderGroupedConnectors(groupedLayout);
  renderEmptyState();
}

function clampToBounds(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function computeGroupRadius(memberCount, bounds, totalGroups = 1) {
  const safeWidth = Math.max(320, Number(bounds?.width) || 320);
  const safeHeight = Math.max(320, Number(bounds?.height) || 320);
  const minDimension = Math.min(safeWidth, safeHeight);
  const densityFactor = Math.max(0.78, Math.min(1.08, 1 - Math.min(0.24, Math.max(0, totalGroups - 2) * 0.03)));
  const rawRadius = getRingRadiusForCount(memberCount) * densityFactor;
  const maxRadius = Math.max(56, Math.min(128, minDimension * 0.22));
  return clampToBounds(rawRadius, 32, maxRadius);
}

function getLayoutPadding(bounds) {
  const minDimension = Math.min(bounds.width, bounds.height);
  return Math.round(clampToBounds(minDimension * 0.16, GROUP_LAYOUT_PADDING, 124));
}

function generateTriangularCandidates(bounds, spacing, padding) {
  const candidates = [];
  const safeSpacing = Math.max(96, Number(spacing) || 132);
  const verticalSpacing = safeSpacing * 0.8660254;
  const safePadding = Math.max(24, Number(padding) || GROUP_LAYOUT_PADDING);
  const minX = safePadding;
  const minY = safePadding;
  const maxX = Math.max(minX, bounds.width - safePadding);
  const maxY = Math.max(minY, bounds.height - safePadding);

  let row = 0;
  for (let y = minY; y <= maxY; y += verticalSpacing) {
    const offset = row % 2 === 0 ? 0 : safeSpacing / 2;
    for (let x = minX + offset; x <= maxX; x += safeSpacing) {
      candidates.push({ x, y });
    }
    row += 1;
  }

  if (candidates.length === 0) {
    candidates.push({
      x: Math.round(bounds.width / 2),
      y: Math.round(bounds.height / 2),
    });
  }

  return candidates;
}

function captureElementRects(layer, selector, dataAttributeName) {
  const map = new Map();
  if (!layer) {
    return map;
  }

  layer.querySelectorAll(selector).forEach((entry) => {
    const key = String(entry.dataset?.[dataAttributeName] || "").trim();
    if (!key) {
      return;
    }

    map.set(key, entry.getBoundingClientRect());
  });

  return map;
}

function playFlipAnimation(layer, selector, dataAttributeName, previousRects, options = {}) {
  if (!layer || !(previousRects instanceof Map) || previousRects.size === 0) {
    return;
  }

  const skipKeys = options.skipKeys instanceof Set ? options.skipKeys : null;

  const animations = [];

  layer.querySelectorAll(selector).forEach((entry) => {
    const key = String(entry.dataset?.[dataAttributeName] || "").trim();
    if (!key) {
      return;
    }

    if (skipKeys && skipKeys.has(key)) {
      return;
    }

    const previous = previousRects.get(key);
    if (!previous) {
      return;
    }

    const next = entry.getBoundingClientRect();
    const dx = previous.left - next.left;
    const dy = previous.top - next.top;
    if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) {
      return;
    }

    entry.classList.add("is-flip-prep");
    entry.style.setProperty("--flip-x", `${dx}px`);
    entry.style.setProperty("--flip-y", `${dy}px`);
    animations.push(entry);
  });

  if (animations.length === 0) {
    return;
  }

  window.requestAnimationFrame(() => {
    animations.forEach((entry) => {
      entry.classList.remove("is-flip-prep");
      entry.style.setProperty("--flip-x", "0px");
      entry.style.setProperty("--flip-y", "0px");
    });
  });
}

function getGroupCenters(groups, bounds) {
  if (!Array.isArray(groups) || groups.length === 0 || !bounds || !Number.isFinite(bounds.width) || !Number.isFinite(bounds.height)) {
    return [];
  }

  const activeStableIds = new Set(groups.map((group) => String(group?.stableId || group?.groupId || "")).filter(Boolean));
  const nextAnchors = {};
  Object.entries(viewState.groupAnchorsByStableId).forEach(([stableId, anchor]) => {
    if (!activeStableIds.has(stableId)) {
      return;
    }

    if (!anchor || !Number.isFinite(anchor.x) || !Number.isFinite(anchor.y)) {
      return;
    }

    nextAnchors[stableId] = {
      x: anchor.x,
      y: anchor.y,
    };
  });
  viewState.groupAnchorsByStableId = nextAnchors;

  const safeBounds = {
    width: Math.max(320, Math.round(bounds.width)),
    height: Math.max(320, Math.round(bounds.height)),
  };
  const layoutPadding = getLayoutPadding(safeBounds);
  const boardCenter = {
    x: Math.round(safeBounds.width / 2),
    y: Math.round(safeBounds.height / 2),
  };

  const centers = groups.map((group) => {
    const stableId = String(group?.stableId || group?.groupId || "");
    const memberCount = Array.isArray(group?.members) ? group.members.length : 0;
    const radius = computeGroupRadius(memberCount, safeBounds, groups.length);
    const anchor = viewState.groupAnchorsByStableId[stableId];
    const fallbackX = Math.round(safeBounds.width / 2);
    const fallbackY = Math.round(safeBounds.height / 2);
    return {
      groupId: String(group?.groupId || ""),
      stableId,
      radius,
      x: anchor?.x ?? fallbackX,
      y: anchor?.y ?? fallbackY,
      hasAnchor: Boolean(anchor),
    };
  });

  const approximateSpacing = Math.max(96, Math.min(170, Math.min(safeBounds.width, safeBounds.height) / Math.max(2, Math.sqrt(centers.length))));
  const candidates = generateTriangularCandidates(safeBounds, approximateSpacing, layoutPadding);

  centers
    .filter((entry) => !entry.hasAnchor)
    .forEach((entry) => {
      let bestCandidate = null;
      let bestScore = Number.NEGATIVE_INFINITY;

      candidates.forEach((candidate) => {
        const minDistance = centers
          .filter((other) => other !== entry)
          .reduce((acc, other) => {
            const distance = Math.hypot(candidate.x - other.x, candidate.y - other.y);
            return Math.min(acc, distance - other.radius);
          }, Number.POSITIVE_INFINITY);

        const edgeDistance = Math.min(
          candidate.x - layoutPadding,
          safeBounds.width - layoutPadding - candidate.x,
          candidate.y - layoutPadding,
          safeBounds.height - layoutPadding - candidate.y
        );
        const centerDistance = Math.hypot(candidate.x - boardCenter.x, candidate.y - boardCenter.y);
        const score = minDistance + (edgeDistance * 0.16) - (centerDistance * 0.08);

        if (score > bestScore) {
          bestScore = score;
          bestCandidate = candidate;
        }
      });

      entry.x = bestCandidate?.x ?? Math.round(safeBounds.width / 2);
      entry.y = bestCandidate?.y ?? Math.round(safeBounds.height / 2);
    });

  for (let iteration = 0; iteration < 12; iteration += 1) {
    for (let index = 0; index < centers.length; index += 1) {
      for (let otherIndex = index + 1; otherIndex < centers.length; otherIndex += 1) {
        const current = centers[index];
        const other = centers[otherIndex];
        const dx = other.x - current.x;
        const dy = other.y - current.y;
        const distance = Math.hypot(dx, dy) || 0.001;
        const required = current.radius + other.radius + GROUP_MIN_SPACING;

        if (distance >= required) {
          continue;
        }

        const overlap = (required - distance) / 2;
        const ux = dx / distance;
        const uy = dy / distance;

        let currentShare = 0.5;
        let otherShare = 0.5;
        if (current.hasAnchor && !other.hasAnchor) {
          currentShare = 0.15;
          otherShare = 0.85;
        } else if (!current.hasAnchor && other.hasAnchor) {
          currentShare = 0.85;
          otherShare = 0.15;
        }

        current.x -= ux * overlap * currentShare;
        current.y -= uy * overlap * currentShare;
        other.x += ux * overlap * otherShare;
        other.y += uy * overlap * otherShare;
      }
    }

    centers.forEach((entry) => {
      const anchor = viewState.groupAnchorsByStableId[entry.stableId];
      if (anchor) {
        entry.x += (anchor.x - entry.x) * 0.07;
        entry.y += (anchor.y - entry.y) * 0.07;
      } else {
        entry.x += (boardCenter.x - entry.x) * 0.035;
        entry.y += (boardCenter.y - entry.y) * 0.035;
      }

      entry.x = clampToBounds(entry.x, layoutPadding, safeBounds.width - layoutPadding);
      entry.y = clampToBounds(entry.y, layoutPadding, safeBounds.height - layoutPadding);
    });
  }

  centers.forEach((entry) => {
    viewState.groupAnchorsByStableId[entry.stableId] = {
      x: Math.round(entry.x),
      y: Math.round(entry.y),
    };
  });

  const nextRenderCentersByStableId = {};
  const settleFactor = dragState.participantId ? GROUP_SETTLE_FACTOR_DRAG : GROUP_SETTLE_FACTOR_IDLE;
  let hasPendingSettle = false;

  const settledCenters = centers.map((entry) => {
    const targetX = Math.round(entry.x);
    const targetY = Math.round(entry.y);
    const previousRenderCenter = viewState.groupRenderCentersByStableId[entry.stableId];

    let renderX = targetX;
    let renderY = targetY;
    if (
      previousRenderCenter &&
      Number.isFinite(previousRenderCenter.x) &&
      Number.isFinite(previousRenderCenter.y)
    ) {
      renderX = previousRenderCenter.x + ((targetX - previousRenderCenter.x) * settleFactor);
      renderY = previousRenderCenter.y + ((targetY - previousRenderCenter.y) * settleFactor);

      if (Math.abs(targetX - renderX) > GROUP_SETTLE_EPSILON || Math.abs(targetY - renderY) > GROUP_SETTLE_EPSILON) {
        hasPendingSettle = true;
      } else {
        renderX = targetX;
        renderY = targetY;
      }
    }

    nextRenderCentersByStableId[entry.stableId] = {
      x: renderX,
      y: renderY,
    };

    return {
      x: Math.round(renderX),
      y: Math.round(renderY),
      radius: entry.radius,
      stableId: entry.stableId,
      groupId: entry.groupId,
    };
  });

  viewState.groupRenderCentersByStableId = nextRenderCentersByStableId;

  if (hasPendingSettle && !dragState.participantId) {
    scheduleGroupSettleRender();
  }

  return settledCenters;
}

function beginInlineGroupRename(group, title) {
  if (!group || !group.groupId || !title) {
    return;
  }

  if (title.contentEditable === "true") {
    title.focus();
    return;
  }

  const originalLabel = String(group.label || "").trim();
  title.dataset.originalLabel = originalLabel;
  title.contentEditable = "true";
  title.classList.add("is-editing");
  title.textContent = "";
  title.focus();

  const finish = (shouldSave) => {
    if (title.contentEditable !== "true") {
      return;
    }

    const nextLabel = shouldSave ? String(title.textContent || "") : title.dataset.originalLabel || "";

    title.contentEditable = "false";
    title.classList.remove("is-editing");
    title.textContent = nextLabel;

    if (!shouldSave) {
      delete title.dataset.originalLabel;
      return;
    }

    socket.emit("teacher:group:rename", {
      groupId: group.groupId,
      label: nextLabel,
    });

    delete title.dataset.originalLabel;
  };

  const onKeyDown = (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      finish(true);
      return;
    }

    if (event.key === "Escape") {
      event.preventDefault();
      finish(false);
    }
  };

  const onBlur = () => {
    finish(true);
    title.removeEventListener("keydown", onKeyDown);
    title.removeEventListener("blur", onBlur);
  };

  title.addEventListener("keydown", onKeyDown);
  title.addEventListener("blur", onBlur);
}

function prototypeEnsureState() {
  if (!(prototypeBoard.clusterDirtyKeys instanceof Set)) {
    prototypeBoard.clusterDirtyKeys = new Set();
  }
  if (!Number.isFinite(prototypeBoard.nextGroupId) || prototypeBoard.nextGroupId < 1) {
    prototypeBoard.nextGroupId = 1;
  }
  if (typeof prototypeBoard.clusterTargetsDirty !== "boolean") {
    prototypeBoard.clusterTargetsDirty = true;
  }
  if (typeof prototypeBoard.clusterDirtyAll !== "boolean") {
    prototypeBoard.clusterDirtyAll = true;
  }
}

function prototypeGetBounds() {
  return {
    width: Math.max(320, whiteboardCanvas.clientWidth || 320),
    height: Math.max(320, whiteboardCanvas.clientHeight || 320),
  };
}

function prototypeGetCircle(circleId) {
  return prototypeBoard.circles.get(String(circleId)) || null;
}

function prototypeGetGroup(groupId) {
  return prototypeBoard.groups.find((group) => group.id === String(groupId)) || null;
}

function prototypeSetCirclePos(circle, x, y) {
  const bounds = prototypeGetBounds();
  const padding = 22;
  const clampedX = clampToBounds(Number(x) || 0, padding, Math.max(padding, bounds.width - padding));
  const clampedY = clampToBounds(Number(y) || 0, padding, Math.max(padding, bounds.height - padding));
  circle.x = clampedX;
  circle.y = clampedY;
  circle.el.style.left = `${clampedX}px`;
  circle.el.style.top = `${clampedY}px`;
}

function prototypeDistance(a, b) {
  return Math.hypot(a.x - b.x, a.y - b.y);
}

function prototypeGetGroupRadius(count) {
  const safeCount = Math.max(0, Number(count) || 0);
  if (safeCount <= 1) {
    return 34;
  }

  const bounds = prototypeGetBounds();
  const minDimension = Math.max(320, Math.min(bounds.width, bounds.height));
  const rawRadius = getRingRadiusForCount(safeCount);
  const minRadius = Math.max(34, Math.min(46, minDimension * 0.09));
  const maxRadius = Math.max(56, Math.min(108, minDimension * 0.18));
  return clampToBounds(rawRadius, minRadius, maxRadius);
}

function prototypeClampGroupCenter(group, centerX, centerY) {
  const bounds = prototypeGetBounds();
  const memberCount = Array.isArray(group?.members) ? group.members.length : 0;
  const radius = Math.max(34, Number(group?.radius) || prototypeGetGroupRadius(memberCount));
  const padding = Math.max(GROUP_CIRCLE_RADIUS + 12, radius + GROUP_CIRCLE_RADIUS + 18, 74);
  return {
    x: clampToBounds(Number(centerX) || 0, padding, Math.max(padding, bounds.width - padding)),
    y: clampToBounds(Number(centerY) || 0, padding, Math.max(padding, bounds.height - padding)),
  };
}

function prototypeGetSingleMemberPosition(group) {
  const bounds = prototypeGetBounds();
  const offset = GROUP_MEMBER_SPACING / 2;
  return {
    x: clampToBounds(group.centerX, GROUP_CIRCLE_RADIUS + 12, Math.max(GROUP_CIRCLE_RADIUS + 12, bounds.width - GROUP_CIRCLE_RADIUS - 12)),
    y: clampToBounds(group.centerY - offset, GROUP_CIRCLE_RADIUS + 12, Math.max(GROUP_CIRCLE_RADIUS + 12, bounds.height - GROUP_CIRCLE_RADIUS - 12)),
  };
}

function prototypeSetGroupCenter(group, centerX, centerY) {
  if (!group) {
    return;
  }

  const clamped = prototypeClampGroupCenter(group, centerX, centerY);

  group.centerX = clamped.x;
  group.centerY = clamped.y;
  group.targetCenterX = clamped.x;
  group.targetCenterY = clamped.y;

  if (!Array.isArray(group.members) || group.members.length === 0) {
    return;
  }

  if (group.members.length === 1) {
    const single = prototypeGetCircle(group.members[0]);
    if (single) {
      const point = prototypeGetSingleMemberPosition(group);
      prototypeSetCirclePos(single, point.x, point.y);
    }
    return;
  }

  prototypeApplyLayout(group, group.members, group.startAngle);
}

function prototypeFindGroupMergeTarget(sourceGroup, pointerX, pointerY) {
  if (!sourceGroup) {
    return null;
  }

  let targetGroup = null;
  let bestScore = Number.POSITIVE_INFINITY;
  prototypeBoard.groups.forEach((group) => {
    if (!group || group.id === sourceGroup.id) {
      return;
    }

    const dCenter = Math.hypot(pointerX - group.centerX, pointerY - group.centerY);
    const capture = Math.max(58, group.radius + 16);
    if (dCenter > capture) {
      return;
    }

    const score = dCenter - capture;
    if (score < bestScore) {
      bestScore = score;
      targetGroup = group;
    }
  });

  return targetGroup;
}

function prototypeRenumberGroupLabels() {
  const defaultPattern = /^Gruppe\s+\d+$/i;
  prototypeBoard.groups.forEach((group, index) => {
    const currentLabel = String(group?.label || "").trim();
    if (!currentLabel || defaultPattern.test(currentLabel)) {
      group.label = `Gruppe ${index + 1}`;
    }
  });
}

function prototypeSyncAssignmentsToServer() {
  if (!FORCE_PROTOTYPE_BOARD) {
    return;
  }

  const groups = Array.isArray(prototypeBoard.groups) ? prototypeBoard.groups : [];
  const assignmentByParticipantId = {};
  groups.forEach((group, index) => {
    const mappedGroupId = getGroupIdFromIndex(index);
    const memberIds = Array.isArray(group?.members) ? group.members : [];
    memberIds.forEach((memberId) => {
      assignmentByParticipantId[String(memberId)] = mappedGroupId;
    });
  });

  const currentGroupCount = Math.max(0, Number(viewState.groupCount) || 0);
  const targetGroupCount = groups.length;
  if (targetGroupCount > currentGroupCount) {
    for (let index = 0; index < targetGroupCount - currentGroupCount; index += 1) {
      socket.emit("teacher:group:increment");
    }
  } else if (targetGroupCount < currentGroupCount) {
    for (let index = 0; index < currentGroupCount - targetGroupCount; index += 1) {
      socket.emit("teacher:group:decrement");
    }
  }

  const serverGroupsById = new Map(
    (Array.isArray(viewState.groups) ? viewState.groups : []).map((group) => [String(group.groupId || ""), group])
  );
  groups.forEach((group, index) => {
    const groupId = getGroupIdFromIndex(index);
    const desiredLabel = String(group?.label || `Gruppe ${index + 1}`).trim() || `Gruppe ${index + 1}`;
    const currentServerLabel = String(serverGroupsById.get(groupId)?.label || "").trim();
    if (desiredLabel !== currentServerLabel) {
      socket.emit("teacher:group:rename", {
        groupId,
        label: desiredLabel,
      });
    }
  });

  const bounds = prototypeGetBounds();
  const participants = Array.isArray(viewState.participants) ? viewState.participants : [];
  participants.forEach((participant) => {
    const participantId = String(participant?.participantId || "");
    if (!participantId) {
      return;
    }

    const circle = prototypeGetCircle(participantId);
    const desiredGroupId = assignmentByParticipantId[participantId] || null;
    const currentGroupId = participant?.groupId ? String(participant.groupId) : null;

    if (!circle) {
      if (currentGroupId) {
        socket.emit("teacher:participant:unassign", { participantId });
      }
      return;
    }

    if (desiredGroupId) {
      if (currentGroupId !== desiredGroupId) {
        socket.emit("teacher:participant:assignToGroup", {
          participantId,
          groupId: desiredGroupId,
        });
      }
      return;
    }

    const x = clampToBounds(circle.x / Math.max(1, bounds.width), 0, 1);
    const y = clampToBounds(circle.y / Math.max(1, bounds.height), 0, 1);
    const currentX = Number(participant?.canvasPosition?.x);
    const currentY = Number(participant?.canvasPosition?.y);
    const positionChanged = !Number.isFinite(currentX) || !Number.isFinite(currentY) || Math.hypot(currentX - x, currentY - y) > 0.02;

    if (currentGroupId || positionChanged) {
      socket.emit("teacher:participant:placeOnCanvas", {
        participantId,
        x,
        y,
      });
    }
  });
}

function prototypeMarkClusterTargetsDirty(dirtyEntities) {
  prototypeEnsureState();
  prototypeBoard.clusterTargetsDirty = true;
  prototypeEnsureLoop();
  if (!Array.isArray(dirtyEntities) || dirtyEntities.length === 0) {
    prototypeBoard.clusterDirtyAll = true;
    prototypeBoard.clusterDirtyKeys.clear();
    return;
  }

  if (prototypeBoard.clusterDirtyAll) {
    return;
  }

  dirtyEntities.forEach((key) => prototypeBoard.clusterDirtyKeys.add(String(key)));
}

function prototypeEntityKey(type, id) {
  return `${type}:${id}`;
}

function prototypeShuffle(items) {
  const copy = items.slice();
  for (let index = copy.length - 1; index > 0; index -= 1) {
    const swapIndex = Math.floor(Math.random() * (index + 1));
    const tmp = copy[index];
    copy[index] = copy[swapIndex];
    copy[swapIndex] = tmp;
  }
  return copy;
}

function prototypeComputeGroupSizes(total, mode) {
  const safeTotal = Math.max(0, Number(total) || 0);
  if (safeTotal <= 0) {
    return [];
  }

  if (mode === "partner") {
    // In partner mode, prefer pairs and use one group of 3 for odd counts.
    if (safeTotal === 1) {
      return [1];
    }

    const sizes = [];
    if (safeTotal % 2 === 1) {
      const pairCount = Math.max(0, (safeTotal - 3) / 2);
      for (let index = 0; index < pairCount; index += 1) {
        sizes.push(2);
      }
      sizes.push(3);
      return sizes;
    }

    const pairCount = safeTotal / 2;
    for (let index = 0; index < pairCount; index += 1) {
      sizes.push(2);
    }
    return sizes;
  }

  const maxGroups = MAX_GROUPS;
  const groupCount = clampToBounds(Number(prototypeBoard.desiredGroupCount) || 0, 0, maxGroups);
  if (groupCount === 0) {
    return [];
  }
  const base = Math.floor(safeTotal / groupCount);
  const remainder = safeTotal % groupCount;
  return Array.from({ length: groupCount }, (_entry, index) => base + (index < remainder ? 1 : 0));
}

function prototypeCreateAutoGroups(mode) {
  const circles = prototypeShuffle(Array.from(prototypeBoard.circles.values()));
  prototypeBoard.circles.forEach((circle) => {
    circle.groupId = null;
    circle.targetX = circle.x;
    circle.targetY = circle.y;
  });

  const sizes = prototypeComputeGroupSizes(circles.length, mode);
  if (sizes.length === 0) {
    prototypeBoard.groups = [];
    prototypeMarkClusterTargetsDirty();
    return;
  }

  prototypeBoard.groups = [];

  const bounds = prototypeGetBounds();
  const centerX = bounds.width / 2;
  const centerY = bounds.height / 2;
  const orbit = Math.max(72, Math.min(bounds.width, bounds.height) * 0.22);
  let cursor = 0;

  sizes.forEach((size, index) => {
    const members = circles.slice(cursor, cursor + size);
    cursor += size;

    const angle = (index / Math.max(1, sizes.length)) * Math.PI * 2;
    const rawCx = sizes.length === 1 ? centerX : centerX + (Math.cos(angle) * orbit);
    const rawCy = sizes.length === 1 ? centerY : centerY + (Math.sin(angle) * orbit);

    const group = {
      id: `pg${prototypeBoard.nextGroupId++}`,
      label: `Gruppe ${index + 1}`,
      centerX: rawCx,
      centerY: rawCy,
      targetCenterX: rawCx,
      targetCenterY: rawCy,
      radius: members.length > 1 ? prototypeGetGroupRadius(members.length) : 34,
      members: members.map((circle) => circle.id),
      startAngle: members.length === 2 ? 0 : (-Math.PI / 2),
    };

    const clampedCenter = prototypeClampGroupCenter(group, group.centerX, group.centerY);
    group.centerX = clampedCenter.x;
    group.centerY = clampedCenter.y;
    group.targetCenterX = clampedCenter.x;
    group.targetCenterY = clampedCenter.y;

    members.forEach((circle) => {
      circle.groupId = group.id;
    });
    prototypeBoard.groups.push(group);
    if (group.members.length === 1) {
      const single = prototypeGetCircle(group.members[0]);
      if (single) {
        const point = prototypeGetSingleMemberPosition(group);
        prototypeSetCirclePos(single, point.x, point.y);
      }
    } else if (group.members.length > 1) {
      prototypeApplyLayout(group, group.members, group.startAngle);
    }
  });

  prototypeMarkClusterTargetsDirty();
}

function prototypeAddEmptyGroup() {
  const bounds = prototypeGetBounds();
  const centerX = bounds.width / 2;
  const centerY = bounds.height / 2;
  const index = prototypeBoard.groups.length;
  const angle = -Math.PI / 2 + (index * (Math.PI / 3));
  const orbit = Math.max(72, Math.min(bounds.width, bounds.height) * 0.22);
  const rawCx = index === 0 ? centerX : centerX + (Math.cos(angle) * orbit);
  const rawCy = index === 0 ? centerY : centerY + (Math.sin(angle) * orbit);

  const group = {
    id: `pg${prototypeBoard.nextGroupId++}`,
    centerX: rawCx,
    centerY: rawCy,
    targetCenterX: rawCx,
    targetCenterY: rawCy,
    radius: 34,
    members: [],
    startAngle: -Math.PI / 2,
    preserveWhenEmpty: true,
  };
  prototypeBoard.groups.push(group);

  const clampedCenter = prototypeClampGroupCenter(group, rawCx, rawCy);
  group.centerX = clampedCenter.x;
  group.centerY = clampedCenter.y;
  group.targetCenterX = clampedCenter.x;
  group.targetCenterY = clampedCenter.y;

  prototypeBoard.desiredGroupCount = prototypeBoard.groups.length;
  prototypeMarkClusterTargetsDirty();
  renderGroupControls();
  prototypeSyncAssignmentsToServer();
}

function prototypeRemoveOneGroup() {
  if (prototypeBoard.groups.length <= 0) {
    return;
  }

  let emptyIndex = -1;
  for (let index = prototypeBoard.groups.length - 1; index >= 0; index -= 1) {
    const group = prototypeBoard.groups[index];
    if ((group?.members?.length || 0) === 0) {
      emptyIndex = index;
      break;
    }
  }
  const removeIndex = emptyIndex >= 0 ? emptyIndex : (prototypeBoard.groups.length - 1);
  const [removed] = prototypeBoard.groups.splice(removeIndex, 1);
  if (removed && Array.isArray(removed.members) && removed.members.length > 0) {
    removed.members.forEach((memberId) => {
      prototypeRemoveCircleByParticipantId(memberId);
      socket.emit("teacher:participant:unassign", {
        participantId: memberId,
      });
    });
    renderParticipantsPanel();
    renderEmptyState();
  }

  prototypeBoard.desiredGroupCount = Math.max(0, prototypeBoard.groups.length);
  prototypeMarkClusterTargetsDirty();
  renderGroupControls();
  prototypeSyncAssignmentsToServer();
}

function prototypeAutoAssign(mode) {
  const safeMode = mode === "partner" ? "partner" : "groups";
  if (safeMode === "groups" && prototypeBoard.groups.length <= 0) {
    const initialCount = Math.max(
      1,
      Number(prototypeBoard.desiredGroupCount) || Number(viewState.groupCount) || 0
    );
    for (let index = 0; index < initialCount; index += 1) {
      prototypeAddEmptyGroup();
    }
  }

  const bounds = prototypeGetBounds();
  viewState.participants.forEach((participant, index) => {
    if (participant?.active === false) {
      return;
    }

    if (prototypeGetCircle(participant.participantId)) {
      return;
    }

    const seedAngle = (index / Math.max(1, viewState.participants.length)) * Math.PI * 2;
    const seedRadius = Math.max(36, Math.min(bounds.width, bounds.height) * 0.06);
    ensurePrototypeCircleElement({
      ...participant,
      canvasPosition: {
        x: clampToBounds((bounds.width * 0.5 + Math.cos(seedAngle) * seedRadius) / Math.max(1, bounds.width), 0.03, 0.97),
        y: clampToBounds((bounds.height * 0.5 + Math.sin(seedAngle) * seedRadius) / Math.max(1, bounds.height), 0.04, 0.96),
      },
    }, bounds);
  });

  prototypeClearPreview();
  prototypeCreateAutoGroups(safeMode);
  renderParticipantsPanel();
  renderGroupControls();
  renderEmptyState();
  prototypeSyncAssignmentsToServer();
}

function prototypeGetLayoutPositions(group, order, startAngle, radius = group.radius) {
  const positions = new Map();
  const total = Array.isArray(order) ? order.length : 0;
  if (total <= 0) {
    return positions;
  }

  const step = (Math.PI * 2) / total;
  for (let index = 0; index < total; index += 1) {
    const angle = startAngle + (index * step);
    positions.set(order[index], {
      x: group.centerX + (Math.cos(angle) * radius),
      y: group.centerY + (Math.sin(angle) * radius),
    });
  }

  return positions;
}

function prototypeApplyLayout(group, order, startAngle, radius = group.radius) {
  const positions = prototypeGetLayoutPositions(group, order, startAngle, radius);
  positions.forEach((point, circleId) => {
    const circle = prototypeGetCircle(circleId);
    if (!circle) {
      return;
    }

    prototypeSetCirclePos(circle, point.x, point.y);
  });
}

function prototypeCollectLayoutTargets(group, order, startAngle, radius = group.radius, targetMap = new Map()) {
  const positions = prototypeGetLayoutPositions(group, order, startAngle, radius);
  positions.forEach((point, circleId) => {
    targetMap.set(circleId, point);
  });
  return targetMap;
}

function prototypeCancelSwapAnimation() {
  if (Number.isFinite(prototypeBoard.swapAnimRafId)) {
    window.cancelAnimationFrame(prototypeBoard.swapAnimRafId);
  }
  prototypeBoard.swapAnimRafId = null;
  prototypeBoard.swapAnimToken = Number(prototypeBoard.swapAnimToken || 0) + 1;
}

function prototypeAnimateCirclesToTargets(targetMap, duration = 180) {
  if (!(targetMap instanceof Map) || targetMap.size === 0) {
    return;
  }

  prototypeCancelSwapAnimation();
  const token = prototypeBoard.swapAnimToken;
  const entries = [];
  targetMap.forEach((target, circleId) => {
    const circle = prototypeGetCircle(circleId);
    if (!circle || !target) {
      return;
    }

    entries.push({
      circle,
      fromX: circle.x,
      fromY: circle.y,
      toX: Number(target.x),
      toY: Number(target.y),
    });
  });

  if (entries.length === 0) {
    return;
  }

  const startedAt = performance.now();
  const safeDuration = Math.max(40, Number(duration) || 180);

  const tick = (now) => {
    if (token !== prototypeBoard.swapAnimToken) {
      return;
    }

    const raw = clampToBounds((now - startedAt) / safeDuration, 0, 1);
    const eased = smoothStep01(raw);

    entries.forEach((entry) => {
      prototypeSetCirclePos(
        entry.circle,
        lerp(entry.fromX, entry.toX, eased),
        lerp(entry.fromY, entry.toY, eased)
      );
    });

    if (raw < 1) {
      prototypeBoard.swapAnimRafId = window.requestAnimationFrame(tick);
      return;
    }

    prototypeBoard.swapAnimRafId = null;
  };

  prototypeBoard.swapAnimRafId = window.requestAnimationFrame(tick);
}

function prototypeApplyPreviewBlend(group, activeId, newOrder, newStart, strength) {
  const baseRadius = group.radius;
  const targetRadius = prototypeGetGroupRadius(newOrder.length);
  const radius = lerp(baseRadius, targetRadius, strength);
  const basePos = prototypeGetLayoutPositions(group, group.members, group.startAngle, baseRadius);
  const targetPos = prototypeGetLayoutPositions(group, newOrder, newStart, radius);

  group.members.forEach((memberId) => {
    const circle = prototypeGetCircle(memberId);
    const from = basePos.get(memberId);
    const to = targetPos.get(memberId);
    if (!circle || !from || !to) {
      return;
    }

    prototypeSetCirclePos(circle, lerp(from.x, to.x, strength), lerp(from.y, to.y, strength));
  });
}

function prototypeSetGroupPreviewHighlight(groupId, enabled) {
  const group = prototypeGetGroup(groupId);
  if (!group || !Array.isArray(group.members)) {
    return;
  }

  group.members.forEach((memberId) => {
    const circle = prototypeGetCircle(memberId);
    if (!circle?.el) {
      return;
    }

    circle.el.classList.toggle("is-preview-highlight", Boolean(enabled));
  });
}

function prototypeSetSwapCandidateHighlight(circle, enabled) {
  if (!circle?.el) {
    return;
  }

  circle.el.classList.toggle("is-swap-candidate", Boolean(enabled));
}

function prototypeClearSwapCandidateHighlights() {
  document.querySelectorAll(".proto-circle.is-swap-candidate").forEach((entry) => {
    entry.classList.remove("is-swap-candidate");
  });
}

function prototypeClearPreview() {
  const preview = prototypeBoard.preview;
  if (!preview) {
    prototypeBoard.pairSwapCandidate = null;
    prototypeBoard.pairModeLock = null;
    prototypeClearSwapCandidateHighlights();
    return;
  }

  if (preview.groupId) {
    const group = prototypeGetGroup(preview.groupId);
    if (group) {
      prototypeApplyLayout(group, group.members, group.startAngle);
    }
    prototypeSetGroupPreviewHighlight(preview.groupId, false);
  }

  const active = prototypeGetCircle(preview.activeId);
  const target = prototypeGetCircle(preview.targetId);
  active?.el.classList.remove("is-preview-highlight");
  target?.el.classList.remove("is-preview-highlight");
  prototypeBoard.pairSwapCandidate = null;
  prototypeBoard.pairModeLock = null;
  prototypeClearSwapCandidateHighlights();
  prototypeBoard.preview = null;
}

function prototypeGetPairToThreePlan(group, activeId, activeY, previousPreview) {
  const deadZone = 12;
  let variant = activeY < group.centerY ? "top" : "bottom";

  if (
    previousPreview &&
    previousPreview.groupId === group.id &&
    previousPreview.total === 3 &&
    Math.abs(activeY - group.centerY) < deadZone
  ) {
    variant = previousPreview.variant || variant;
  }

  if (variant === "top") {
    return {
      total: 3,
      variant,
      newOrder: [activeId, group.members[0], group.members[1]],
      newStart: -Math.PI / 2,
    };
  }

  return {
    total: 3,
    variant,
    newOrder: [activeId, group.members[1], group.members[0]],
    newStart: Math.PI / 2,
  };
}

function prototypeAngularDistance(a, b) {
  return Math.abs(Math.atan2(Math.sin(a - b), Math.cos(a - b)));
}

function prototypeGetSnappedSlot(start, incomingAngle, total, previousIndex) {
  const step = (Math.PI * 2) / total;
  let bestIndex = 0;
  let bestDelta = Number.POSITIVE_INFINITY;
  const deltas = [];

  for (let index = 0; index < total; index += 1) {
    const angle = start + (index * step);
    const delta = prototypeAngularDistance(incomingAngle, angle);
    deltas[index] = delta;
    if (delta < bestDelta) {
      bestDelta = delta;
      bestIndex = index;
    }
  }

  if (Number.isInteger(previousIndex) && previousIndex >= 0 && previousIndex < total) {
    const previousDelta = deltas[previousIndex];
    const hysteresis = step * 0.30;
    if (bestIndex !== previousIndex && bestDelta + hysteresis >= previousDelta) {
      bestIndex = previousIndex;
    }
  }

  return {
    insertIndex: bestIndex,
    snappedAngle: start + (bestIndex * step),
    newStart: start,
  };
}

function prototypeGetInsertionPlan(group, incomingAngle, previousPreview) {
  const total = group.members.length + 1;
  const start = group.startAngle;
  const previousSlot =
    previousPreview && previousPreview.groupId === group.id && previousPreview.total === total
      ? previousPreview.slotIndex
      : null;
  const snapped = prototypeGetSnappedSlot(start, incomingAngle, total, previousSlot);

  return {
    total,
    slotIndex: snapped.insertIndex,
    snappedAngle: snapped.snappedAngle,
    newStart: snapped.newStart,
  };
}

function prototypeNormalizeAngleDelta(angle) {
  const tau = Math.PI * 2;
  let value = angle % tau;
  if (value < 0) {
    value += tau;
  }
  return value;
}

function prototypeGetSlotBlend(group, incomingAngle) {
  const total = Math.max(1, (group?.members?.length || 0) + 1);
  const step = (Math.PI * 2) / total;
  const normalized = prototypeNormalizeAngleDelta(incomingAngle - group.startAngle);
  const slotFloat = normalized / step;
  const lower = Math.floor(slotFloat) % total;
  const upper = (lower + 1) % total;
  const fraction = smoothStep01(slotFloat - Math.floor(slotFloat));
  return {
    lower,
    upper,
    fraction,
    total,
  };
}

function prototypeApplyPreviewBetweenSlots(group, activeId, slotBlend, strength) {
  const orderA = group.members.slice();
  orderA.splice(slotBlend.lower, 0, activeId);
  const orderB = group.members.slice();
  orderB.splice(slotBlend.upper, 0, activeId);

  const baseRadius = group.radius;
  const targetRadius = prototypeGetGroupRadius(orderA.length);
  const radius = lerp(baseRadius, targetRadius, strength);
  const basePos = prototypeGetLayoutPositions(group, group.members, group.startAngle, baseRadius);
  const posA = prototypeGetLayoutPositions(group, orderA, group.startAngle, radius);
  const posB = prototypeGetLayoutPositions(group, orderB, group.startAngle, radius);

  group.members.forEach((memberId) => {
    const circle = prototypeGetCircle(memberId);
    const from = basePos.get(memberId);
    const a = posA.get(memberId);
    const b = posB.get(memberId);
    if (!circle || !from || !a || !b) {
      return;
    }

    const tx = lerp(a.x, b.x, slotBlend.fraction);
    const ty = lerp(a.y, b.y, slotBlend.fraction);
    prototypeSetCirclePos(circle, lerp(from.x, tx, strength), lerp(from.y, ty, strength));
  });
}

function prototypeApplySourceGroupClosePreview(active) {
  const source = active?.dragDetachSource;
  if (!source || !source.groupId) {
    return;
  }

  const group = prototypeGetGroup(source.groupId);
  if (!group || !Array.isArray(group.members) || group.members.length <= 1) {
    return;
  }

  const fromPos = prototypeGetLayoutPositions(
    group,
    source.membersBefore,
    source.startAngleBefore,
    source.radiusBefore
  );
  const toPos = prototypeGetLayoutPositions(group, group.members, group.startAngle, group.radius);

  const distanceToGroup = Math.hypot(active.x - group.centerX, active.y - group.centerY);
  const inner = Math.max(18, source.radiusBefore * 0.72);
  const outer = source.radiusBefore + dockCaptureMargin();
  const closeStrength = smoothStep01((distanceToGroup - inner) / Math.max(outer - inner, 1));

  group.members.forEach((memberId) => {
    const circle = prototypeGetCircle(memberId);
    const from = fromPos.get(memberId);
    const to = toPos.get(memberId);
    if (!circle || !from || !to) {
      return;
    }

    prototypeSetCirclePos(
      circle,
      lerp(from.x, to.x, closeStrength),
      lerp(from.y, to.y, closeStrength)
    );
  });
}

function prototypeFinalizeSourceGroupClosePreview(circle) {
  const source = circle?.dragDetachSource;
  if (!source) {
    return;
  }

  const group = prototypeGetGroup(source.groupId);
  if (group && Array.isArray(group.members) && group.members.length > 1) {
    prototypeApplyLayout(group, group.members, group.startAngle);
  }

  circle.dragDetachSource = null;
}

function prototypeGetPreviewStrength(active, group) {
  const outer = group.radius + dockCaptureMargin();
  const inner = group.radius;
  const dCenter = Math.hypot(active.x - group.centerX, active.y - group.centerY);
  const raw = (outer - dCenter) / Math.max(outer - inner, 1);
  return smoothStep01(raw);
}

function prototypeGetPairCenterBias(active, group) {
  const dCenter = Math.hypot(active.x - group.centerX, active.y - group.centerY);
  const nearCenter = Math.max(8, group.radius * 0.2);
  const fullDeflection = group.radius + 10;
  const raw = (dCenter - nearCenter) / Math.max(fullDeflection - nearCenter, 1);
  return smoothStep01(raw);
}

function prototypeShouldKeepCurrentPreview(active) {
  const preview = prototypeBoard.preview;
  if (!preview || preview.activeId !== active.id) {
    return false;
  }

  const target = prototypeGetCircle(preview.targetId);
  if (!target) {
    if (preview.groupId) {
      const group = prototypeGetGroup(preview.groupId);
      if (!group) {
        return false;
      }

      const dCenter = Math.hypot(active.x - group.centerX, active.y - group.centerY);
      return dCenter <= group.radius + dockReleaseMargin();
    }
    return false;
  }

  if (!target.groupId) {
    return prototypeDistance(active, target) < 92;
  }

  const group = prototypeGetGroup(target.groupId);
  if (!group) {
    return false;
  }

  const dCenter = Math.hypot(active.x - group.centerX, active.y - group.centerY);
  return dCenter <= group.radius + dockReleaseMargin();
}

function prototypeFindNearestTarget(active) {
  let nearestGroup = null;
  let bestGroupScore = Number.POSITIVE_INFINITY;
  let nearestSingle = null;
  let minSingleDistance = Number.POSITIVE_INFINITY;

  prototypeBoard.groups.forEach((group) => {
    if (active.groupId === group.id) {
      return;
    }

    const dCenter = Math.hypot(active.x - group.centerX, active.y - group.centerY);
    if (dCenter > group.radius + dockCaptureMargin()) {
      return;
    }

    const score = dCenter <= group.radius ? 0 : dCenter - group.radius;
    if (score < bestGroupScore) {
      bestGroupScore = score;
      nearestGroup = group;
    }
  });

  prototypeBoard.circles.forEach((candidate) => {
    if (candidate.id === active.id) {
      return;
    }

    if (candidate.groupId !== null && active.groupId === candidate.groupId) {
      return;
    }

    const distance = prototypeDistance(active, candidate);
    if (distance >= 72) {
      return;
    }

    if (candidate.groupId === null && distance < minSingleDistance) {
      minSingleDistance = distance;
      nearestSingle = candidate;
    }
  });

  if (nearestGroup) {
    if (!Array.isArray(nearestGroup.members) || nearestGroup.members.length === 0) {
      return {
        id: `group:${nearestGroup.id}`,
        groupId: nearestGroup.id,
        x: nearestGroup.centerX,
        y: nearestGroup.centerY,
      };
    }

    let nearestMember = null;
    let memberDistance = Number.POSITIVE_INFINITY;
    nearestGroup.members.forEach((memberId) => {
      const member = prototypeGetCircle(memberId);
      if (!member) {
        return;
      }

      const d = prototypeDistance(active, member);
      if (d < memberDistance) {
        memberDistance = d;
        nearestMember = member;
      }
    });

    return nearestMember || nearestSingle;
  }

  return nearestSingle;
}

function prototypeApplyPreview(active, target) {
  prototypeClearSwapCandidateHighlights();

  if (!target.groupId) {
    const originGroupId = active.dragOriginGroupId ? String(active.dragOriginGroupId) : null;
    const originGroup = originGroupId ? prototypeGetGroup(originGroupId) : null;
    const canSwapWithFree = Boolean(
      originGroup &&
      target.id !== active.id &&
      target.groupId === null
    );

    if (canSwapWithFree) {
      prototypeSetGroupPreviewHighlight(originGroup.id, true);
      prototypeSetSwapCandidateHighlight(active, true);
      prototypeSetSwapCandidateHighlight(target, true);
    }

    active.el.classList.add("is-preview-highlight");
    target.el.classList.add("is-preview-highlight");
    prototypeBoard.preview = {
      activeId: active.id,
      targetId: target.id,
      groupId: canSwapWithFree ? originGroup.id : null,
      order: null,
      startAngle: null,
      incomingAngle: null,
      swapTargetId: canSwapWithFree ? target.id : null,
      swapWithFree: canSwapWithFree,
    };
    return;
  }

  const group = prototypeGetGroup(target.groupId);
  if (!group) {
    return;
  }

  const incomingAngle = Math.atan2(active.y - group.centerY, active.x - group.centerX);
  const strength = prototypeGetPreviewStrength(active, group);
  const isPairTargetGroup = (group.members?.length || 0) === 2;
  const isNearPairMidline = isPairTargetGroup && Math.abs(active.y - group.centerY) <= (PAIR_PREVIEW_DEAD_ZONE + 4);
  let targetCircle = null;
  let swapDistance = Number.POSITIVE_INFINITY;
  if (isNearPairMidline) {
    const pairMembers = group.members
      .map((memberId) => prototypeGetCircle(memberId))
      .filter(Boolean)
      .sort((a, b) => a.x - b.x);
    if (pairMembers.length === 2) {
      targetCircle = active.x <= group.centerX ? pairMembers[0] : pairMembers[1];
      swapDistance = prototypeDistance(active, targetCircle);
    }
  }

  if (!targetCircle) {
    group.members.forEach((memberId) => {
      if (memberId === active.id) {
        return;
      }

      const member = prototypeGetCircle(memberId);
      if (!member) {
        return;
      }

      const distance = prototypeDistance(active, member);
      if (distance < swapDistance) {
        swapDistance = distance;
        targetCircle = member;
      }
    });
  }

  let pairSwapTargetId = null;
  if (isPairTargetGroup && targetCircle && targetCircle.id !== active.id && targetCircle.groupId === group.id) {
    const nearPairMidline = Math.abs(active.y - group.centerY) <= (PAIR_PREVIEW_DEAD_ZONE + 4);
    const nearPairCenterX = Math.abs(active.x - group.centerX) <= Math.max(12, group.radius * 0.35);
    const suppressPairSwap = nearPairMidline && nearPairCenterX;
    const previousPreview = prototypeBoard.preview;
    const wasSwappingSamePair = Boolean(
      previousPreview &&
      previousPreview.activeId === active.id &&
      previousPreview.groupId === group.id &&
      previousPreview.swapTargetId === targetCircle.id
    );
    const pairEnterDistance = PAIR_SWAP_CAPTURE_DISTANCE;
    const pairKeepDistance = Math.max(pairEnterDistance + 4, PAIR_SWAP_RELEASE_DISTANCE);
    const isWithinPairSwapDistance = swapDistance <= (wasSwappingSamePair ? pairKeepDistance : pairEnterDistance);
    const isFromGroup = Boolean(active.dragOriginGroupId);

    if (!suppressPairSwap && isWithinPairSwapDistance && isFromGroup) {
      pairSwapTargetId = targetCircle.id;
      prototypeBoard.pairSwapCandidate = null;
    } else if (!suppressPairSwap && isWithinPairSwapDistance) {
      const now = performance.now();
      const candidate = prototypeBoard.pairSwapCandidate;
      const sameCandidate = Boolean(
        candidate &&
        candidate.activeId === active.id &&
        candidate.targetId === targetCircle.id &&
        candidate.groupId === group.id
      );
      if (!sameCandidate) {
        prototypeBoard.pairSwapCandidate = {
          activeId: active.id,
          targetId: targetCircle.id,
          groupId: group.id,
          since: now,
        };
      } else if (now - candidate.since >= PAIR_SWAP_CONFIRM_MS) {
        pairSwapTargetId = targetCircle.id;
      }
    } else {
      const candidate = prototypeBoard.pairSwapCandidate;
      if (candidate && candidate.activeId === active.id) {
        prototypeBoard.pairSwapCandidate = null;
      }
    }
  }

  let shouldSwap = false;
  if (targetCircle && targetCircle.id !== active.id && targetCircle.groupId === group.id) {
    if (!isPairTargetGroup) {
      shouldSwap = swapDistance <= SWAP_CAPTURE_DISTANCE;
      prototypeBoard.pairSwapCandidate = null;
      prototypeBoard.pairModeLock = null;
    }
  }

  if (shouldSwap) {
    const plan = prototypeGetInsertionPlan(group, incomingAngle, prototypeBoard.preview);
    const slotBlend = prototypeGetSlotBlend(group, incomingAngle);
    const newOrder = group.members.slice();
    newOrder.splice(plan.slotIndex, 0, active.id);
    prototypeApplyPreviewBetweenSlots(group, active.id, slotBlend, strength);
    prototypeSetGroupPreviewHighlight(group.id, true);
    prototypeSetSwapCandidateHighlight(active, true);
    prototypeSetSwapCandidateHighlight(targetCircle, true);
    active.el.classList.add("is-preview-highlight");
    targetCircle.el?.classList.add("is-preview-highlight");
    prototypeBoard.preview = {
      activeId: active.id,
      targetId: target.id,
      groupId: group.id,
      order: newOrder,
      startAngle: plan.newStart,
      incomingAngle: plan.snappedAngle,
      total: group.members.length,
      variant: null,
      slotIndex: plan.slotIndex,
      swapTargetId: targetCircle.id,
      strength,
    };
    return;
  }

  if (group.members.length === 2) {
    const pairPlan = prototypeGetPairToThreePlan(group, active.id, active.y, prototypeBoard.preview);
    const pairStrength = strength * prototypeGetPairCenterBias(active, group);
    prototypeApplyPreviewBlend(group, active.id, pairPlan.newOrder, pairPlan.newStart, pairStrength);
    prototypeSetGroupPreviewHighlight(group.id, true);
    if (pairSwapTargetId) {
      prototypeSetSwapCandidateHighlight(active, true);
      const swapTargetCircle = prototypeGetCircle(pairSwapTargetId);
      prototypeSetSwapCandidateHighlight(swapTargetCircle, true);
      swapTargetCircle?.el?.classList.add("is-preview-highlight");
    }
    active.el.classList.add("is-preview-highlight");
    prototypeBoard.preview = {
      activeId: active.id,
      targetId: pairSwapTargetId || target.id,
      groupId: group.id,
      order: pairPlan.newOrder,
      startAngle: pairPlan.newStart,
      incomingAngle,
      total: pairPlan.total,
      variant: pairPlan.variant,
      slotIndex: 0,
      swapTargetId: pairSwapTargetId,
      strength: pairStrength,
    };
    return;
  }

  const plan = prototypeGetInsertionPlan(group, incomingAngle, prototypeBoard.preview);
  const slotBlend = prototypeGetSlotBlend(group, incomingAngle);
  const newOrder = group.members.slice();
  newOrder.splice(plan.slotIndex, 0, active.id);
  prototypeApplyPreviewBetweenSlots(group, active.id, slotBlend, strength);
  prototypeSetGroupPreviewHighlight(group.id, true);
  active.el.classList.add("is-preview-highlight");
  prototypeBoard.preview = {
    activeId: active.id,
    targetId: target.id,
    groupId: group.id,
    order: newOrder,
    startAngle: plan.newStart,
    incomingAngle: plan.snappedAngle,
    total: plan.total,
    variant: null,
    slotIndex: plan.slotIndex,
    swapTargetId: null,
    strength,
  };
}

function prototypeCommitConnection(active, target) {
  if (!target.groupId) {
    const targetCircle = prototypeGetCircle(target.id);
    const wantsSwapWithFree = Boolean(
      prototypeBoard.preview &&
      prototypeBoard.preview.activeId === active.id &&
      prototypeBoard.preview.swapWithFree &&
      prototypeBoard.preview.swapTargetId === target.id
    );
    const originGroupId = active.dragOriginGroupId ? String(active.dragOriginGroupId) : null;
    const originGroup = originGroupId ? prototypeGetGroup(originGroupId) : null;

    if (
      wantsSwapWithFree &&
      targetCircle &&
      targetCircle.id !== active.id &&
      targetCircle.groupId === null &&
      originGroup
    ) {
      const animationTargets = new Map();
      const freeX = targetCircle.x;
      const freeY = targetCircle.y;
      const originX = Number.isFinite(Number(active.dragOriginX)) ? Number(active.dragOriginX) : active.x;
      const originY = Number.isFinite(Number(active.dragOriginY)) ? Number(active.dragOriginY) : active.y;
      const incomingAngle = Math.atan2(originY - originGroup.centerY, originX - originGroup.centerX);
      const plan = prototypeGetInsertionPlan(originGroup, incomingAngle, null);
      const insertIndex = clampToBounds(
        Number.isFinite(Number(plan?.slotIndex)) ? Number(plan.slotIndex) : originGroup.members.length,
        0,
        originGroup.members.length
      );

      targetCircle.groupId = originGroup.id;
      originGroup.members.splice(insertIndex, 0, targetCircle.id);
      originGroup.startAngle = Number.isFinite(Number(plan?.newStart)) ? Number(plan.newStart) : originGroup.startAngle;
      originGroup.radius = originGroup.members.length > 1 ? prototypeGetGroupRadius(originGroup.members.length) : 34;
      originGroup.targetCenterX = originGroup.centerX;
      originGroup.targetCenterY = originGroup.centerY;

      if (originGroup.members.length === 1) {
        const point = prototypeGetSingleMemberPosition(originGroup);
        animationTargets.set(targetCircle.id, point);
      } else {
        prototypeCollectLayoutTargets(
          originGroup,
          originGroup.members,
          originGroup.startAngle,
          originGroup.radius,
          animationTargets
        );
      }

      animationTargets.set(active.id, { x: freeX, y: freeY });
      active.targetX = freeX;
      active.targetY = freeY;
      prototypeAnimateCirclesToTargets(animationTargets);
      prototypeMarkClusterTargetsDirty([
        prototypeEntityKey("group", originGroup.id),
        prototypeEntityKey("single", active.id),
      ]);
      return;
    }

    const side = active.x >= target.x ? 1 : -1;
    const snappedActiveX = target.x + (side * GROUP_MEMBER_SPACING);
    const snappedActiveY = target.y;
    prototypeSetCirclePos(active, snappedActiveX, snappedActiveY);

    const centerX = (snappedActiveX + target.x) / 2;
    const centerY = (snappedActiveY + target.y) / 2;
    const group = {
      id: `pg${prototypeBoard.nextGroupId++}`,
      label: `Gruppe ${prototypeBoard.nextGroupId - 1}`,
      centerX,
      centerY,
      targetCenterX: centerX,
      targetCenterY: centerY,
      radius: prototypeGetGroupRadius(2),
      members: side > 0 ? [active.id, target.id] : [target.id, active.id],
      startAngle: 0,
      preserveWhenEmpty: false,
    };

    prototypeBoard.groups.push(group);
    active.groupId = group.id;
    target.groupId = group.id;
    prototypeApplyLayout(group, group.members, group.startAngle);
    prototypeMarkClusterTargetsDirty([prototypeEntityKey("group", group.id)]);
    return;
  }

  const group = prototypeGetGroup(target.groupId);
  if (!group) {
    return;
  }

  const targetCircle = prototypeGetCircle(target.id);
  const wantsSwap = Boolean(
    prototypeBoard.preview &&
    prototypeBoard.preview.activeId === active.id &&
    prototypeBoard.preview.swapTargetId === target.id
  );
  const canSwapWithTarget = Boolean(
    wantsSwap &&
    targetCircle &&
    targetCircle.id !== active.id &&
    targetCircle.groupId === group.id
  );
  if (canSwapWithTarget) {
    const targetIndex = group.members.indexOf(targetCircle.id);
    if (targetIndex >= 0) {
      const animationTargets = new Map();
      const originGroupId = active.dragOriginGroupId ? String(active.dragOriginGroupId) : null;
      const originGroup = originGroupId ? prototypeGetGroup(originGroupId) : null;
      const isSameGroupSwap = Boolean(originGroup && originGroup.id === group.id);
      const originIndex = Number.isInteger(active.dragOriginMemberIndex)
        ? Number(active.dragOriginMemberIndex)
        : group.members.length;

      group.members[targetIndex] = active.id;
      active.groupId = group.id;

      if (isSameGroupSwap) {
        const insertIndex = clampToBounds(originIndex, 0, group.members.length);
        group.members.splice(insertIndex, 0, targetCircle.id);
        targetCircle.groupId = group.id;
      }

      group.radius = group.members.length > 1 ? prototypeGetGroupRadius(group.members.length) : 34;
      group.targetCenterX = group.centerX;
      group.targetCenterY = group.centerY;
      if (group.members.length === 1) {
        const point = prototypeGetSingleMemberPosition(group);
        animationTargets.set(active.id, point);
      } else {
        prototypeCollectLayoutTargets(
          group,
          group.members,
          group.startAngle,
          group.radius,
          animationTargets
        );
      }
      const originX = Number.isFinite(Number(active.dragOriginX)) ? Number(active.dragOriginX) : active.x;
      const originY = Number.isFinite(Number(active.dragOriginY)) ? Number(active.dragOriginY) : active.y;

      if (isSameGroupSwap) {
        prototypeAnimateCirclesToTargets(animationTargets);
        prototypeMarkClusterTargetsDirty([
          prototypeEntityKey("group", group.id),
        ]);
        return;
      }

      targetCircle.groupId = null;
      if (originGroup && originGroup.id !== group.id) {
        originGroup.members.push(targetCircle.id);
        targetCircle.groupId = originGroup.id;
        originGroup.radius = originGroup.members.length > 1 ? prototypeGetGroupRadius(originGroup.members.length) : 34;
        originGroup.targetCenterX = originGroup.centerX;
        originGroup.targetCenterY = originGroup.centerY;
        if (originGroup.members.length === 1) {
          const point = prototypeGetSingleMemberPosition(originGroup);
          animationTargets.set(targetCircle.id, point);
        } else {
          prototypeCollectLayoutTargets(
            originGroup,
            originGroup.members,
            originGroup.startAngle,
            originGroup.radius,
            animationTargets
          );
        }
        prototypeAnimateCirclesToTargets(animationTargets);
        prototypeMarkClusterTargetsDirty([
          prototypeEntityKey("group", group.id),
          prototypeEntityKey("group", originGroup.id),
        ]);
        return;
      }

      animationTargets.set(targetCircle.id, { x: originX, y: originY });
      targetCircle.targetX = originX;
      targetCircle.targetY = originY;
      prototypeAnimateCirclesToTargets(animationTargets);
      prototypeMarkClusterTargetsDirty([
        prototypeEntityKey("group", group.id),
        prototypeEntityKey("single", targetCircle.id),
      ]);
      return;
    }
  }

  const incomingAngle = Math.atan2(active.y - group.centerY, active.x - group.centerX);
  if (group.members.length === 2) {
    const pairPlan = prototypeGetPairToThreePlan(group, active.id, active.y, prototypeBoard.preview);
    group.members = pairPlan.newOrder.slice();
    group.startAngle = pairPlan.newStart;
    group.radius = prototypeGetGroupRadius(group.members.length);
    group.targetCenterX = group.centerX;
    group.targetCenterY = group.centerY;
    active.groupId = group.id;
    prototypeApplyLayout(group, group.members, group.startAngle);
    prototypeMarkClusterTargetsDirty([prototypeEntityKey("group", group.id)]);
    return;
  }

  const plan = prototypeGetInsertionPlan(group, incomingAngle, prototypeBoard.preview);
  group.members.splice(plan.slotIndex, 0, active.id);
  group.startAngle = plan.newStart;
  group.radius = prototypeGetGroupRadius(group.members.length);
  group.targetCenterX = group.centerX;
  group.targetCenterY = group.centerY;
  active.groupId = group.id;
  prototypeApplyLayout(group, group.members, group.startAngle);
  prototypeMarkClusterTargetsDirty([prototypeEntityKey("group", group.id)]);
}

function prototypeRemoveCircleFromGroup(circle) {
  if (circle.groupId === null) {
    return;
  }

  const group = prototypeGetGroup(circle.groupId);
  if (!group) {
    circle.groupId = null;
    return;
  }

  const previousMembers = Array.isArray(group.members) ? group.members.slice() : [];
  const previousStartAngle = group.startAngle;
  const previousRadius = group.radius;
  const shouldTrackSoftClose = Boolean(
    circle.dragOriginGroupId && String(circle.dragOriginGroupId) === group.id
  );

  group.members = group.members.filter((memberId) => memberId !== circle.id);
  circle.groupId = null;

  if (shouldTrackSoftClose) {
    circle.dragDetachSource = {
      groupId: group.id,
      membersBefore: previousMembers,
      startAngleBefore: previousStartAngle,
      radiusBefore: previousRadius,
    };
  }

  if (group.members.length === 0) {
    if (group.preserveWhenEmpty) {
      group.radius = 34;
      prototypeMarkClusterTargetsDirty([
        prototypeEntityKey("group", group.id),
        prototypeEntityKey("single", circle.id),
      ]);
      return;
    }

    prototypeBoard.groups = prototypeBoard.groups.filter((entry) => entry.id !== group.id);
    prototypeMarkClusterTargetsDirty([prototypeEntityKey("single", circle.id)]);
    return;
  }

  if (group.members.length === 1 && group.preserveWhenEmpty) {
    const lone = prototypeGetCircle(group.members[0]);
    if (lone) {
      lone.groupId = group.id;
      const point = prototypeGetSingleMemberPosition(group);
      prototypeSetCirclePos(lone, point.x, point.y);
    }
    group.radius = 34;
    prototypeMarkClusterTargetsDirty([
      prototypeEntityKey("group", group.id),
      prototypeEntityKey("single", circle.id),
    ]);
    return;
  }

  if (group.members.length <= 1) {
    const loneId = group.members[0];
    if (loneId) {
      const lone = prototypeGetCircle(loneId);
      if (lone) {
        lone.groupId = null;
        lone.targetX = lone.x;
        lone.targetY = lone.y;
      }
    }
    prototypeBoard.groups = prototypeBoard.groups.filter((entry) => entry.id !== group.id);
    prototypeMarkClusterTargetsDirty(loneId ? [prototypeEntityKey("single", loneId)] : []);
    return;
  }

  group.radius = prototypeGetGroupRadius(group.members.length);
  prototypeApplyLayout(group, group.members, group.startAngle);
  prototypeMarkClusterTargetsDirty([
    prototypeEntityKey("group", group.id),
    prototypeEntityKey("single", circle.id),
  ]);
}

function prototypeRemoveCircleByParticipantId(participantId) {
  const circle = prototypeGetCircle(participantId);
  if (!circle) {
    return;
  }

  prototypeRemoveCircleFromGroup(circle);
  circle.el.remove();
  prototypeBoard.circles.delete(String(participantId));
  prototypeMarkClusterTargetsDirty();
}

function prototypeMakeDraggable(circle) {
  let startX = 0;
  let startY = 0;
  let origX = 0;
  let origY = 0;
  let isActive = false;
  let detachOnMove = false;
  let hasDetached = false;
  let pendingX = 0;
  let pendingY = 0;
  let lastClientX = 0;
  let lastClientY = 0;
  let isOverParticipantsPanel = false;
  let frameRequested = false;

  function isInsideParticipantsPanel(clientX, clientY) {
    const rect = participantsPanel.getBoundingClientRect();
    return clientX >= rect.left && clientX <= rect.right && clientY >= rect.top && clientY <= rect.bottom;
  }

  function scheduleMove(x, y) {
    pendingX = x;
    pendingY = y;
    if (frameRequested) {
      return;
    }

    frameRequested = true;
    window.requestAnimationFrame(() => {
      frameRequested = false;
      moveTo(pendingX, pendingY);
    });
  }

  function moveTo(clientX, clientY) {
    if (!isActive) {
      return;
    }

    lastClientX = clientX;
    lastClientY = clientY;
    isOverParticipantsPanel = isInsideParticipantsPanel(clientX, clientY);
    participantsPanel.classList.toggle("is-drop-active", isOverParticipantsPanel);

    const dx = clientX - startX;
    const dy = clientY - startY;
    if (detachOnMove && !hasDetached && Math.hypot(dx, dy) > 8) {
      prototypeRemoveCircleFromGroup(circle);
      detachOnMove = false;
      hasDetached = true;
      origX = circle.x;
      origY = circle.y;
      startX = clientX;
      startY = clientY;
    }

    prototypeSetCirclePos(circle, origX + dx, origY + dy);
    prototypeApplySourceGroupClosePreview(circle);

    if (prototypeShouldKeepCurrentPreview(circle)) {
      const currentTarget = prototypeGetCircle(prototypeBoard.preview.targetId);
      if (currentTarget && currentTarget.groupId !== null) {
        prototypeApplyPreview(circle, currentTarget);
        return;
      }
    }

    const nearest = prototypeFindNearestTarget(circle);
    if (!nearest) {
      prototypeClearPreview();
      return;
    }

    const preview = prototypeBoard.preview;
    const sameActivePreview = Boolean(preview && preview.activeId === circle.id);
    const samePreviewGroup = Boolean(
      preview &&
      nearest.groupId !== null &&
      preview.groupId === nearest.groupId
    );
    const changed = !preview ||
      !sameActivePreview ||
      (!samePreviewGroup && preview.targetId !== nearest.id);

    if (changed) {
      prototypeClearPreview();
      prototypeApplyPreview(circle, nearest);
      return;
    }

    if (nearest.groupId !== null && preview && preview.groupId === nearest.groupId) {
      prototypeApplyPreview(circle, nearest);
    }
  }

  function finishDrag() {
    if (!isActive) {
      return;
    }

    isActive = false;
    prototypeBoard.activeDragCircleId = null;
    frameRequested = false;
    circle.el.classList.remove("is-dragging");
    participantsPanel.classList.remove("is-drop-active");

    if (isOverParticipantsPanel || isInsideParticipantsPanel(lastClientX, lastClientY)) {
      prototypeClearPreview();
      prototypeFinalizeSourceGroupClosePreview(circle);
      prototypeRemoveCircleByParticipantId(circle.id);
      renderParticipantsPanel();
      renderGroupControls();
      renderEmptyState();
      socket.emit("teacher:participant:unassign", {
        participantId: circle.id,
      });
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
      document.removeEventListener("touchmove", onTouchMove);
      document.removeEventListener("touchend", onTouchEnd);
      return;
    }

    if (prototypeBoard.preview && prototypeBoard.preview.activeId === circle.id && circle.groupId === null) {
      let target = prototypeGetCircle(prototypeBoard.preview.targetId);
      if (!target && prototypeBoard.preview.groupId) {
        const group = prototypeGetGroup(prototypeBoard.preview.groupId);
        if (group) {
          target = {
            id: `group:${group.id}`,
            groupId: group.id,
            x: group.centerX,
            y: group.centerY,
          };
        }
      }

      if (target) {
        prototypeCommitConnection(circle, target);
      }
    }

    if (circle.groupId === null) {
      circle.targetX = circle.x;
      circle.targetY = circle.y;
      prototypeMarkClusterTargetsDirty([prototypeEntityKey("single", circle.id)]);
    }

    prototypeSyncAssignmentsToServer();

    prototypeFinalizeSourceGroupClosePreview(circle);

    circle.dragOriginGroupId = null;
    circle.dragOriginGroupSize = null;
    circle.dragOriginX = null;
    circle.dragOriginY = null;
    circle.dragOriginMemberIndex = null;
    circle.dragDetachSource = null;

    prototypeClearPreview();
    document.removeEventListener("mousemove", onMouseMove);
    document.removeEventListener("mouseup", onMouseUp);
    document.removeEventListener("touchmove", onTouchMove);
    document.removeEventListener("touchend", onTouchEnd);
  }

  function onMouseMove(event) {
    lastClientX = event.clientX;
    lastClientY = event.clientY;
    scheduleMove(event.clientX, event.clientY);
  }

  function onTouchMove(event) {
    event.preventDefault();
    const touch = event.touches[0];
    if (!touch) {
      return;
    }
    lastClientX = touch.clientX;
    lastClientY = touch.clientY;
    scheduleMove(touch.clientX, touch.clientY);
  }

  function onMouseUp(event) {
    lastClientX = event.clientX;
    lastClientY = event.clientY;
    if (event.target instanceof Node && participantsPanel.contains(event.target)) {
      isOverParticipantsPanel = true;
    }
    finishDrag();
  }

  function onTouchEnd() {
    finishDrag();
  }

  function onMouseDown(event) {
    event.preventDefault();
    prototypeBoard.pairSwapCandidate = null;
    prototypeBoard.pairModeLock = null;
    prototypeCancelSwapAnimation();
    isActive = true;
    prototypeBoard.activeDragCircleId = circle.id;
    prototypeEnsureLoop();
    startX = event.clientX;
    startY = event.clientY;
    lastClientX = event.clientX;
    lastClientY = event.clientY;
    isOverParticipantsPanel = false;
    origX = circle.x;
    origY = circle.y;
    circle.dragOriginGroupId = circle.groupId;
    circle.dragOriginGroupSize = null;
    circle.dragOriginX = circle.x;
    circle.dragOriginY = circle.y;
    circle.dragOriginMemberIndex = null;
    if (circle.groupId !== null) {
      const originGroup = prototypeGetGroup(circle.groupId);
      circle.dragOriginGroupSize = originGroup ? originGroup.members.length : 0;
      circle.dragOriginMemberIndex = originGroup ? originGroup.members.indexOf(circle.id) : null;
    }
    circle.dragDetachSource = null;
    detachOnMove = circle.groupId !== null;
    hasDetached = false;
    frameRequested = false;
    circle.el.classList.add("is-dragging");
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  }

  function onTouchStart(event) {
    event.preventDefault();
    prototypeBoard.pairSwapCandidate = null;
    prototypeBoard.pairModeLock = null;
    prototypeCancelSwapAnimation();
    const touch = event.touches[0];
    if (!touch) {
      return;
    }

    isActive = true;
    prototypeBoard.activeDragCircleId = circle.id;
    prototypeEnsureLoop();
    startX = touch.clientX;
    startY = touch.clientY;
    lastClientX = touch.clientX;
    lastClientY = touch.clientY;
    isOverParticipantsPanel = false;
    origX = circle.x;
    origY = circle.y;
    circle.dragOriginGroupId = circle.groupId;
    circle.dragOriginGroupSize = null;
    circle.dragOriginX = circle.x;
    circle.dragOriginY = circle.y;
    circle.dragOriginMemberIndex = null;
    if (circle.groupId !== null) {
      const originGroup = prototypeGetGroup(circle.groupId);
      circle.dragOriginGroupSize = originGroup ? originGroup.members.length : 0;
      circle.dragOriginMemberIndex = originGroup ? originGroup.members.indexOf(circle.id) : null;
    }
    circle.dragDetachSource = null;
    detachOnMove = circle.groupId !== null;
    hasDetached = false;
    frameRequested = false;
    circle.el.classList.add("is-dragging");
    document.addEventListener("touchmove", onTouchMove, { passive: false });
    document.addEventListener("touchend", onTouchEnd);
  }

  circle.el.addEventListener("mousedown", onMouseDown);
  circle.el.addEventListener("touchstart", onTouchStart, { passive: false });
}

function prototypeCreateCircle(participant, x, y) {
  const circleId = String(participant.participantId);
  const element = document.createElement("div");
  element.className = "proto-circle";
  element.draggable = true;
  element.dataset.participantId = circleId;
  element.setAttribute("aria-label", participant.name);
  element.title = participant.name;
  element.textContent = getParticipantInitials(participant.name);
  const color = getParticipantColor(circleId);
  element.style.background = color;
  whiteboardLooseLayer.appendChild(element);

  const circle = {
    id: circleId,
    participantId: circleId,
    name: participant.name,
    color,
    el: element,
    x,
    y,
    targetX: x,
    targetY: y,
    groupId: null,
  };

  prototypeBoard.circles.set(circle.id, circle);
  prototypeSetCirclePos(circle, x, y);

  element.addEventListener("dragstart", (event) => {
    dragState.participantId = circle.id;
    dragState.origin = "canvas";
    circle.el.classList.add("is-dragging");
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", circle.id);
  });

  element.addEventListener("dragend", () => {
    circle.el.classList.remove("is-dragging");
    dragState.participantId = null;
    dragState.origin = null;
    participantsPanel.classList.remove("is-drop-active");
    whiteboardCanvas.classList.remove("is-drop-active");
  });

  prototypeMakeDraggable(circle);
  return circle;
}

function prototypeEnsureCanvas() {
  const bounds = prototypeGetBounds();
  if (!prototypeBoard.canvasEl) {
    const canvas = document.createElement("canvas");
    canvas.className = "proto-lines-canvas";
    whiteboardConnectorLayer.appendChild(canvas);
    prototypeBoard.canvasEl = canvas;
    prototypeBoard.canvasCtx = canvas.getContext("2d");
  }

  if (prototypeBoard.canvasEl.width !== bounds.width || prototypeBoard.canvasEl.height !== bounds.height) {
    prototypeBoard.canvasEl.width = bounds.width;
    prototypeBoard.canvasEl.height = bounds.height;
  }
}

function prototypeComputeClusterTargets() {
  prototypeEnsureState();
  const bounds = prototypeGetBounds();
  const centerX = bounds.width / 2;
  const centerY = bounds.height / 2;
  const boundaryMargin = 40;
  const maxSearchRadius = Math.max(160, Math.min(bounds.width, bounds.height) * 0.42);

  const entities = [];
  prototypeBoard.groups.forEach((group) => {
    entities.push({
      type: "group",
      ref: group,
      key: prototypeEntityKey("group", group.id),
      radius: group.radius,
      currentX: group.targetCenterX ?? group.centerX,
      currentY: group.targetCenterY ?? group.centerY,
    });
  });

  prototypeBoard.circles.forEach((circle) => {
    if (circle.groupId !== null) {
      return;
    }
    entities.push({
      type: "single",
      ref: circle,
      key: prototypeEntityKey("single", circle.id),
      radius: 24,
      currentX: circle.targetX ?? circle.x,
      currentY: circle.targetY ?? circle.y,
    });
  });

  const stable = [];
  const dirty = [];
  entities.forEach((entity) => {
    const isDirty = prototypeBoard.clusterDirtyAll || prototypeBoard.clusterDirtyKeys.has(entity.key);
    if (isDirty) {
      dirty.push(entity);
    } else {
      stable.push(entity);
    }
  });

  stable.sort((a, b) => b.radius - a.radius);
  dirty.sort((a, b) => b.radius - a.radius);
  const placed = [];

  function fits(candidate, radius) {
    if (
      candidate.x < boundaryMargin + radius ||
      candidate.x > bounds.width - boundaryMargin - radius ||
      candidate.y < boundaryMargin + radius ||
      candidate.y > bounds.height - boundaryMargin - radius
    ) {
      return false;
    }

    for (const other of placed) {
      const minDistance = radius + other.radius + 42;
      if (Math.hypot(candidate.x - other.x, candidate.y - other.y) < minDistance) {
        return false;
      }
    }
    return true;
  }

  function candidateScore(candidate, radius, currentX, currentY) {
    let minClearance = Number.POSITIVE_INFINITY;
    for (const other of placed) {
      const minDistance = radius + other.radius + 42;
      const clearance = Math.hypot(candidate.x - other.x, candidate.y - other.y) - minDistance;
      minClearance = Math.min(minClearance, clearance);
    }
    if (!placed.length) {
      minClearance = 200;
    }

    const centerDistance = Math.hypot(candidate.x - centerX, candidate.y - centerY);
    const moveDistance = Math.hypot(candidate.x - currentX, candidate.y - currentY);
    return (minClearance * 1.25) - (centerDistance * 0.18) - (moveDistance * 0.35);
  }

  function sampleBestPosition(entity, isDirty) {
    if (fits({ x: entity.currentX, y: entity.currentY }, entity.radius)) {
      return { x: entity.currentX, y: entity.currentY };
    }

    let best = null;
    let bestScore = Number.NEGATIVE_INFINITY;
    const searchRadius = !prototypeBoard.clusterDirtyAll && isDirty ? 110 : maxSearchRadius;
    for (let index = 0; index < 240; index += 1) {
      const angle = Math.random() * Math.PI * 2;
      const dist = Math.sqrt(Math.random()) * searchRadius;
      const candidate = {
        x: clampToBounds(entity.currentX + (Math.cos(angle) * dist), boundaryMargin + entity.radius, bounds.width - boundaryMargin - entity.radius),
        y: clampToBounds(entity.currentY + (Math.sin(angle) * dist), boundaryMargin + entity.radius, bounds.height - boundaryMargin - entity.radius),
      };

      if (!fits(candidate, entity.radius)) {
        continue;
      }

      const score = candidateScore(candidate, entity.radius, entity.currentX, entity.currentY);
      if (score > bestScore) {
        bestScore = score;
        best = candidate;
      }
    }

    if (best) {
      return best;
    }

    return {
      x: clampToBounds(entity.currentX, boundaryMargin + entity.radius, bounds.width - boundaryMargin - entity.radius),
      y: clampToBounds(entity.currentY, boundaryMargin + entity.radius, bounds.height - boundaryMargin - entity.radius),
    };
  }

  [...stable, ...dirty].forEach((entity, index) => {
    const isDirty = index >= stable.length;
    const target = sampleBestPosition(entity, isDirty);
    placed.push({ x: target.x, y: target.y, radius: entity.radius });
    if (entity.type === "group") {
      entity.ref.targetCenterX = target.x;
      entity.ref.targetCenterY = target.y;
    } else {
      entity.ref.targetX = target.x;
      entity.ref.targetY = target.y;
    }
  });

  prototypeBoard.clusterTargetsDirty = false;
  prototypeBoard.clusterDirtyAll = false;
  prototypeBoard.clusterDirtyKeys.clear();
}

function prototypeSettleClusterTargets() {
  if (
    prototypeBoard.activeDragCircleId !== null ||
    prototypeBoard.preview ||
    prototypeBoard.groupDragSourceId !== null
  ) {
    return false;
  }

  if (prototypeBoard.clusterTargetsDirty) {
    prototypeComputeClusterTargets();
  }

  let moved = false;

  prototypeBoard.groups.forEach((group) => {
    // Ziel innerhalb der Canvas-Grenzen halten, damit die ganze Gruppe
    // hineinwandert statt einzelne Mitglieder am Rand zu verzerren.
    const clampedTarget = prototypeClampGroupCenter(
      group,
      group.targetCenterX ?? group.centerX,
      group.targetCenterY ?? group.centerY
    );
    const tx = clampedTarget.x;
    const ty = clampedTarget.y;
    const dx = tx - group.centerX;
    const dy = ty - group.centerY;
    if (group.members.length === 1) {
      if (Math.abs(dx) >= 0.05 || Math.abs(dy) >= 0.05) {
        group.centerX += dx * 0.16;
        group.centerY += dy * 0.16;
        moved = true;
      }
      const single = prototypeGetCircle(group.members[0]);
      if (single) {
        const point = prototypeGetSingleMemberPosition(group);
        prototypeSetCirclePos(single, point.x, point.y);
      }
      return;
    }

    if (Math.abs(dx) < 0.05 && Math.abs(dy) < 0.05) {
      return;
    }

    group.centerX += dx * 0.16;
    group.centerY += dy * 0.16;
    prototypeApplyLayout(group, group.members, group.startAngle);
    moved = true;
  });

  prototypeBoard.circles.forEach((circle) => {
    if (circle.groupId !== null || circle.id === prototypeBoard.activeDragCircleId) {
      return;
    }

    const tx = circle.targetX ?? circle.x;
    const ty = circle.targetY ?? circle.y;
    const dx = tx - circle.x;
    const dy = ty - circle.y;
    if (Math.abs(dx) < 0.05 && Math.abs(dy) < 0.05) {
      return;
    }

    prototypeSetCirclePos(circle, circle.x + (dx * 0.16), circle.y + (dy * 0.16));
    moved = true;
  });

  return moved;
}

function prototypeDrawLines() {
  prototypeEnsureCanvas();
  const ctx = prototypeBoard.canvasCtx;
  if (!ctx) {
    return;
  }

  ctx.clearRect(0, 0, prototypeBoard.canvasEl.width, prototypeBoard.canvasEl.height);

  prototypeBoard.groups.forEach((group) => {
    const members = group.members.map((memberId) => prototypeGetCircle(memberId)).filter(Boolean);
    ctx.beginPath();
    ctx.arc(group.centerX, group.centerY, group.radius, 0, Math.PI * 2);
    ctx.setLineDash([7, 7]);
    ctx.lineWidth = 1.5;
    ctx.strokeStyle = "#2b2b2b";
    ctx.globalAlpha = 0.35;
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.globalAlpha = 1;

    ctx.beginPath();
    ctx.arc(group.centerX, group.centerY, group.radius + dockCaptureMargin(), 0, Math.PI * 2);
    ctx.setLineDash([3, 8]);
    ctx.lineWidth = 1.2;
    ctx.strokeStyle = "#0b6b3f";
    ctx.globalAlpha = 0.28;
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.globalAlpha = 1;

    if (prototypeBoard.groupMergeTargetId && group.id === prototypeBoard.groupMergeTargetId) {
      ctx.beginPath();
      ctx.arc(group.centerX, group.centerY, group.radius + 6, 0, Math.PI * 2);
      ctx.setLineDash([]);
      ctx.lineWidth = 2.4;
      ctx.strokeStyle = "#9ec8ff";
      ctx.globalAlpha = 0.85;
      ctx.stroke();
      ctx.globalAlpha = 1;
    }

    if (
      prototypeBoard.groupHoverSourceId &&
      group.id === prototypeBoard.groupHoverSourceId &&
      !prototypeBoard.groupDragSourceId
    ) {
      ctx.beginPath();
      ctx.arc(group.centerX, group.centerY, group.radius + 9, 0, Math.PI * 2);
      ctx.setLineDash([4, 6]);
      ctx.lineWidth = 2;
      ctx.strokeStyle = "#9ec8ff";
      ctx.globalAlpha = 0.52;
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.globalAlpha = 1;
    }

    if (prototypeBoard.groupDragSourceId && group.id === prototypeBoard.groupDragSourceId) {
      ctx.beginPath();
      ctx.arc(group.centerX, group.centerY, group.radius + 11, 0, Math.PI * 2);
      ctx.setLineDash([5, 6]);
      ctx.lineWidth = 2.2;
      ctx.strokeStyle = "#9ec8ff";
      ctx.globalAlpha = 0.62;
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.globalAlpha = 1;
    }

    if (members.length === 2) {
      const [a, b] = members;
      const dx = b.x - a.x;
      const dy = b.y - a.y;
      const length = Math.hypot(dx, dy) || 1;
      const ux = dx / length;
      const uy = dy / length;
      const gap = 12;
      const startX = a.x + (ux * gap);
      const startY = a.y + (uy * gap);
      const endX = b.x - (ux * gap);
      const endY = b.y - (uy * gap);

      ctx.beginPath();
      ctx.moveTo(startX, startY);
      ctx.lineTo(endX, endY);
      ctx.strokeStyle = a.color;
      ctx.globalAlpha = 0.4;
      ctx.lineWidth = 2;
      ctx.stroke();
      ctx.globalAlpha = 1;
      return;
    }

    members.forEach((member) => {
      const dx = group.centerX - member.x;
      const dy = group.centerY - member.y;
      const length = Math.hypot(dx, dy) || 1;
      const ux = dx / length;
      const uy = dy / length;
      const startGap = 12;
      const endGap = 22;
      const startX = member.x + (ux * startGap);
      const startY = member.y + (uy * startGap);
      const endX = group.centerX - (ux * endGap);
      const endY = group.centerY - (uy * endGap);

      ctx.beginPath();
      ctx.moveTo(startX, startY);
      ctx.lineTo(endX, endY);
      ctx.strokeStyle = member.color;
      ctx.globalAlpha = 0.34;
      ctx.lineWidth = 1.8;
      ctx.stroke();
      ctx.globalAlpha = 1;
    });
  });
}

function prototypeCommitGroupLabel(groupId, labelEl, shouldSave) {
  const group = prototypeGetGroup(groupId);
  if (!group || !labelEl) {
    return;
  }

  const previousLabel = String(labelEl.dataset.previousLabel || group.label || "").trim();
  const nextLabel = shouldSave ? String(labelEl.textContent || "").trim() : previousLabel;
  group.label = nextLabel || previousLabel || "Gruppe";
  labelEl.textContent = group.label;
  labelEl.contentEditable = "false";
  labelEl.classList.remove("is-editing");

  if (shouldSave) {
    prototypeSyncAssignmentsToServer();
  }
}

function prototypeCreateGroupLabel(group, index) {
  const label = document.createElement("div");
  label.className = "proto-group-label";
  label.dataset.groupId = group.id;
  label.tabIndex = 0;
  group.label = String(group.label || `Gruppe ${index + 1}`);
  label.textContent = group.label;

  let dragStartX = 0;
  let dragStartY = 0;
  let offsetX = 0;
  let offsetY = 0;
  let dragging = false;
  let moved = false;
  let activeTargetGroupId = null;
  let suppressClickEdit = false;
  const dragEdgeThreshold = 32;

  function updateLabelDragZone(clientX) {
    if (label.contentEditable === "true") {
      label.classList.remove("is-drag-zone");
      if (prototypeBoard.groupHoverSourceId === group.id) {
        prototypeBoard.groupHoverSourceId = null;
      }
      return;
    }

    const rect = label.getBoundingClientRect();
    const localX = clientX - rect.left;
    const isEdge = localX <= dragEdgeThreshold || localX >= (rect.width - dragEdgeThreshold);
    label.classList.toggle("is-drag-zone", isEdge);
    if (isEdge) {
      prototypeBoard.groupHoverSourceId = group.id;
    } else if (prototypeBoard.groupHoverSourceId === group.id) {
      prototypeBoard.groupHoverSourceId = null;
    }
  }

  function clearMergeHighlight() {
    if (!activeTargetGroupId) {
      prototypeBoard.groupMergeTargetId = null;
      return;
    }
    prototypeSetGroupPreviewHighlight(activeTargetGroupId, false);
    activeTargetGroupId = null;
    prototypeBoard.groupMergeTargetId = null;
  }

  function setMergeHighlight(groupId) {
    if (activeTargetGroupId === groupId) {
      return;
    }

    clearMergeHighlight();
    if (!groupId) {
      prototypeBoard.groupMergeTargetId = null;
      return;
    }

    prototypeSetGroupPreviewHighlight(groupId, true);
    activeTargetGroupId = groupId;
    prototypeBoard.groupMergeTargetId = groupId;
  }

  function onDragMove(clientX, clientY) {
    const liveGroup = prototypeGetGroup(group.id);
    if (!liveGroup) {
      return;
    }

    const bounds = prototypeGetBounds();
    const targetX = clampToBounds(clientX - offsetX, 28, bounds.width - 28);
    const targetY = clampToBounds(clientY - offsetY, 28, bounds.height - 28);
    const dx = clientX - dragStartX;
    const dy = clientY - dragStartY;
    if (!moved && Math.hypot(dx, dy) > 4) {
      moved = true;
      suppressClickEdit = true;
    }

    if (!moved) {
      return;
    }

    prototypeSetGroupCenter(liveGroup, targetX, targetY);
    const mergeTarget = prototypeFindGroupMergeTarget(liveGroup, targetX, targetY);
    setMergeHighlight(mergeTarget?.id || null);
    prototypeMarkClusterTargetsDirty([prototypeEntityKey("group", liveGroup.id)]);
  }

  function finishGroupDrag() {
    if (!dragging) {
      return;
    }

    dragging = false;
    prototypeBoard.groupDragSourceId = null;
    label.classList.remove("is-dragging");
    const sourceGroup = prototypeGetGroup(group.id);
    const targetGroup = activeTargetGroupId ? prototypeGetGroup(activeTargetGroupId) : null;

    if (sourceGroup && targetGroup && sourceGroup.id !== targetGroup.id) {
      const incoming = Array.isArray(sourceGroup.members) ? sourceGroup.members.slice() : [];
      incoming.forEach((memberId) => {
        const circle = prototypeGetCircle(memberId);
        if (circle) {
          circle.groupId = targetGroup.id;
        }
      });

      const mergedMembers = [...targetGroup.members, ...incoming];
      targetGroup.members = Array.from(new Set(mergedMembers));
      targetGroup.radius = targetGroup.members.length > 1 ? prototypeGetGroupRadius(targetGroup.members.length) : 34;
      if (targetGroup.members.length === 1) {
        const single = prototypeGetCircle(targetGroup.members[0]);
        if (single) {
          const point = prototypeGetSingleMemberPosition(targetGroup);
          prototypeSetCirclePos(single, point.x, point.y);
        }
      } else if (targetGroup.members.length > 1) {
        prototypeApplyLayout(targetGroup, targetGroup.members, targetGroup.startAngle);
      }

      prototypeBoard.groups = prototypeBoard.groups.filter((entry) => entry.id !== sourceGroup.id);
      prototypeRenumberGroupLabels();
      prototypeMarkClusterTargetsDirty();
      renderGroupControls();
      renderEmptyState();
      prototypeSyncAssignmentsToServer();
    }

    clearMergeHighlight();
    window.removeEventListener("mousemove", onMouseMove);
    window.removeEventListener("mouseup", onMouseUp);
    window.removeEventListener("touchmove", onTouchMove);
    window.removeEventListener("touchend", onTouchEnd);
  }

  function onMouseMove(event) {
    onDragMove(event.clientX, event.clientY);
  }

  function onTouchMove(event) {
    const touch = event.touches[0];
    if (!touch) {
      return;
    }
    event.preventDefault();
    onDragMove(touch.clientX, touch.clientY);
  }

  function onMouseUp() {
    finishGroupDrag();
  }

  function onTouchEnd() {
    finishGroupDrag();
  }

  function startGroupDrag(clientX, clientY) {
    const liveGroup = prototypeGetGroup(group.id);
    if (!liveGroup) {
      return;
    }

    dragging = true;
    prototypeBoard.groupDragSourceId = liveGroup.id;
    prototypeEnsureLoop();
    prototypeBoard.groupHoverSourceId = null;
    moved = false;
    dragStartX = clientX;
    dragStartY = clientY;
    offsetX = clientX - liveGroup.centerX;
    offsetY = clientY - liveGroup.centerY;
    label.classList.add("is-dragging");
    window.addEventListener("mousemove", onMouseMove);
    window.addEventListener("mouseup", onMouseUp);
    window.addEventListener("touchmove", onTouchMove, { passive: false });
    window.addEventListener("touchend", onTouchEnd);
  }

  label.addEventListener("mousedown", (event) => {
    if (label.contentEditable === "true") {
      return;
    }
    updateLabelDragZone(event.clientX);
    if (!label.classList.contains("is-drag-zone")) {
      return;
    }
    event.preventDefault();
    startGroupDrag(event.clientX, event.clientY);
  });

  label.addEventListener("touchstart", (event) => {
    if (label.contentEditable === "true") {
      return;
    }
    const touch = event.touches[0];
    if (!touch) {
      return;
    }
    event.preventDefault();
    startGroupDrag(touch.clientX, touch.clientY);
  }, { passive: false });

  label.addEventListener("mousemove", (event) => {
    updateLabelDragZone(event.clientX);
  });

  label.addEventListener("mouseleave", () => {
    if (label.contentEditable !== "true") {
      label.classList.remove("is-drag-zone");
    }
    if (prototypeBoard.groupHoverSourceId === group.id) {
      prototypeBoard.groupHoverSourceId = null;
    }
  });

  label.addEventListener("click", () => {
    if (suppressClickEdit) {
      suppressClickEdit = false;
      return;
    }

    if (label.contentEditable === "true") {
      return;
    }

    label.dataset.previousLabel = String(group.label || label.textContent || "");
    label.contentEditable = "true";
    label.classList.add("is-editing");
    label.focus();
    document.execCommand("selectAll", false, null);
  });

  label.addEventListener("keydown", (event) => {
    if (label.contentEditable !== "true") {
      return;
    }

    if (event.key === "Enter") {
      event.preventDefault();
      prototypeCommitGroupLabel(group.id, label, true);
      return;
    }

    if (event.key === "Escape") {
      event.preventDefault();
      prototypeCommitGroupLabel(group.id, label, false);
    }
  });

  label.addEventListener("blur", () => {
    if (label.contentEditable === "true") {
      prototypeCommitGroupLabel(group.id, label, true);
    }
  });

  return label;
}

function prototypeSyncGroupLabels() {
  prototypeRenumberGroupLabels();

  const activeIds = new Set(
    prototypeBoard.groups
      .filter((group) => Array.isArray(group.members) && group.members.length !== 2)
      .map((group) => group.id)
  );
  whiteboardGroupLayer.querySelectorAll(".proto-group-label").forEach((entry) => {
    const groupId = String(entry.dataset.groupId || "");
    if (!activeIds.has(groupId)) {
      entry.remove();
    }
  });

  prototypeBoard.groups.forEach((group, index) => {
    if (!Array.isArray(group.members) || group.members.length === 2) {
      return;
    }

    let label = whiteboardGroupLayer.querySelector(`.proto-group-label[data-group-id="${group.id}"]`);
    if (!label) {
      label = prototypeCreateGroupLabel(group, index);
      whiteboardGroupLayer.appendChild(label);
    }

    if (label.contentEditable !== "true") {
      group.label = String(group.label || `Gruppe ${index + 1}`);
      label.textContent = group.label;
    }

    label.style.left = `${Math.round(group.centerX)}px`;
    label.style.top = `${Math.round(group.centerY)}px`;
  });
}

function prototypeComputeSeedPosition(index, total, bounds) {
  // Verteile ungruppierte Teilnehmende gleichmaessig ueber das Board (Raster),
  // damit sie beim Laden nicht auf einem Stapel liegen.
  const count = Math.max(1, Number(total) || 1);
  const cols = Math.max(1, Math.ceil(Math.sqrt(count)));
  const rows = Math.max(1, Math.ceil(count / cols));
  const col = index % cols;
  const row = Math.floor(index / cols);
  const marginX = Math.max(48, bounds.width * 0.1);
  const marginY = Math.max(48, bounds.height * 0.12);
  const usableW = Math.max(1, bounds.width - marginX * 2);
  const usableH = Math.max(1, bounds.height - marginY * 2);
  const x = marginX + (cols === 1 ? usableW / 2 : (col / (cols - 1)) * usableW);
  const y = marginY + (rows === 1 ? usableH / 2 : (row / Math.max(1, rows - 1)) * usableH);
  return {
    x: clampToBounds(x / Math.max(1, bounds.width), 0.04, 0.96),
    y: clampToBounds(y / Math.max(1, bounds.height), 0.05, 0.95),
  };
}

function prototypeEnsureAllCircles(bounds = prototypeGetBounds()) {
  // Alle anwesenden Teilnehmenden liegen direkt auf dem Board (kein Seitenpanel mehr).
  const participants = (Array.isArray(viewState.participants) ? viewState.participants : [])
    .filter((entry) => entry?.active !== false);
  participants.forEach((participant, index) => {
    const participantId = String(participant.participantId);
    const existing = prototypeGetCircle(participantId);
    if (existing) {
      existing.name = participant.name;
      existing.color = getParticipantColor(participantId);
      existing.el.style.background = existing.color;
      existing.el.textContent = getParticipantInitials(participant.name);
      existing.el.setAttribute("aria-label", participant.name);
      existing.el.title = participant.name;
      return;
    }

    const pos = participant.canvasPosition;
    const hasPos = pos && Number.isFinite(Number(pos.x)) && Number.isFinite(Number(pos.y));
    const seed = hasPos ? pos : prototypeComputeSeedPosition(index, participants.length, bounds);
    ensurePrototypeCircleElement({ ...participant, canvasPosition: seed }, bounds);
  });
}

function prototypeHydrateGroupsFromServer(bounds = prototypeGetBounds()) {
  // Einmalige Rehydration: gespeicherte Gruppen (toolio_gt_*) beim Laden aufs Board holen.
  const serverGroups = Array.isArray(viewState.groups) ? viewState.groups : [];
  const populated = serverGroups.filter(
    (group) => Array.isArray(group.members) && group.members.length > 0
  );
  if (populated.length === 0) {
    return;
  }

  const centerX = bounds.width / 2;
  const centerY = bounds.height / 2;
  const orbit = Math.max(78, Math.min(bounds.width, bounds.height) * 0.20);

  populated.forEach((group, index) => {
    const memberIds = group.members
      .map((member) => String(member.participantId || ""))
      .filter((memberId) => prototypeBoard.circles.has(memberId));
    if (memberIds.length === 0) {
      return;
    }

    const angle = (index / Math.max(1, populated.length)) * Math.PI * 2;
    const protoGroup = {
      id: `pg${prototypeBoard.nextGroupId++}`,
      label: String(group.label || `Gruppe ${index + 1}`),
      radius: memberIds.length > 1 ? prototypeGetGroupRadius(memberIds.length) : 34,
      members: memberIds,
      startAngle: memberIds.length === 2 ? 0 : -Math.PI / 2,
      preserveWhenEmpty: false,
    };

    const rawCx = populated.length === 1 ? centerX : centerX + Math.cos(angle) * orbit;
    const rawCy = populated.length === 1 ? centerY : centerY + Math.sin(angle) * orbit;
    const clampedCenter = prototypeClampGroupCenter(protoGroup, rawCx, rawCy);
    protoGroup.centerX = clampedCenter.x;
    protoGroup.centerY = clampedCenter.y;
    protoGroup.targetCenterX = clampedCenter.x;
    protoGroup.targetCenterY = clampedCenter.y;

    memberIds.forEach((memberId) => {
      const circle = prototypeGetCircle(memberId);
      if (circle) {
        circle.groupId = protoGroup.id;
      }
    });

    prototypeBoard.groups.push(protoGroup);
    if (memberIds.length === 1) {
      const single = prototypeGetCircle(memberIds[0]);
      if (single) {
        const point = prototypeGetSingleMemberPosition(protoGroup);
        prototypeSetCirclePos(single, point.x, point.y);
      }
    } else {
      prototypeApplyLayout(protoGroup, protoGroup.members, protoGroup.startAngle);
    }
  });

  prototypeBoard.desiredGroupCount = Math.max(
    Number(prototypeBoard.desiredGroupCount) || 0,
    prototypeBoard.groups.length
  );
  prototypeMarkClusterTargetsDirty();
}

function prototypeSyncFromParticipants() {
  prototypeEnsureState();
  const bounds = prototypeGetBounds();
  const participants = (Array.isArray(viewState.participants) ? viewState.participants : [])
    .filter((entry) => entry?.active !== false);
  const participantIds = new Set(participants.map((entry) => String(entry.participantId)));

  Array.from(prototypeBoard.circles.keys()).forEach((circleId) => {
    if (participantIds.has(circleId)) {
      return;
    }

    prototypeRemoveCircleByParticipantId(circleId);
  });

  prototypeEnsureAllCircles(bounds);

  if (FORCE_PROTOTYPE_BOARD && !prototypeBoard.hydratedFromServer && Array.isArray(viewState.groups) && viewState.groups.length > 0) {
    prototypeBoard.hydratedFromServer = true;
    prototypeHydrateGroupsFromServer(bounds);
  }

  prototypeBoard.groups = prototypeBoard.groups
    .map((group) => ({
      ...group,
      members: group.members.filter((memberId) => prototypeBoard.circles.has(memberId)),
    }));

  prototypeBoard.groups.forEach((group) => {
    group.radius = group.members.length > 1 ? prototypeGetGroupRadius(group.members.length) : 34;
    group.members.forEach((memberId) => {
      const circle = prototypeGetCircle(memberId);
      if (circle) {
        circle.groupId = group.id;
      }
    });
    if (group.members.length === 1) {
      const single = prototypeGetCircle(group.members[0]);
      if (single) {
        const point = prototypeGetSingleMemberPosition(group);
        prototypeSetCirclePos(single, point.x, point.y);
      }
      return;
    }

    if (group.members.length > 1) {
      prototypeApplyLayout(group, group.members, group.startAngle);
    }
  });
}

function prototypeLoopTick() {
  const settling = prototypeSettleClusterTargets();
  prototypeDrawLines();
  prototypeSyncGroupLabels();
  const busy =
    prototypeBoard.activeDragCircleId !== null ||
    prototypeBoard.preview ||
    prototypeBoard.groupDragSourceId !== null ||
    prototypeBoard.clusterTargetsDirty ||
    settling;
  if (busy) {
    prototypeBoard.rafId = window.requestAnimationFrame(prototypeLoopTick);
  } else {
    // Alles sitzt ruhig -> Loop anhalten (kein Dauer-Jitter, keine CPU-Last).
    prototypeBoard.rafId = null;
  }
}

function prototypeEnsureLoop() {
  if (prototypeBoard.rafId !== null) {
    return;
  }

  prototypeBoard.rafId = window.requestAnimationFrame(prototypeLoopTick);
}

function ensurePrototypeCircleElement(participant, bounds = prototypeGetBounds()) {
  const participantId = String(participant.participantId);
  const existing = prototypeGetCircle(participantId);
  if (existing) {
    return existing;
  }

  const hasCanvasPosition = Boolean(
    participant.canvasPosition &&
    Number.isFinite(Number(participant.canvasPosition.x)) &&
    Number.isFinite(Number(participant.canvasPosition.y))
  );
  const x = hasCanvasPosition
    ? clampToBounds(Number(participant.canvasPosition.x) * bounds.width, GROUP_CIRCLE_RADIUS + 12, bounds.width - GROUP_CIRCLE_RADIUS - 12)
    : clampToBounds(bounds.width * 0.5, GROUP_CIRCLE_RADIUS + 12, bounds.width - GROUP_CIRCLE_RADIUS - 12);
  const y = hasCanvasPosition
    ? clampToBounds(Number(participant.canvasPosition.y) * bounds.height, GROUP_CIRCLE_RADIUS + 12, bounds.height - GROUP_CIRCLE_RADIUS - 12)
    : clampToBounds(bounds.height * 0.5, GROUP_CIRCLE_RADIUS + 12, bounds.height - GROUP_CIRCLE_RADIUS - 12);

  const circle = prototypeCreateCircle(participant, x, y);
  prototypeMarkClusterTargetsDirty([prototypeEntityKey("single", circle.id)]);
  return circle;
}

function setPrototypeCirclePosition(circle, x, y) {
  prototypeSetCirclePos(circle, x, y);
}

function removePrototypeCircleFromGroup(circle) {
  prototypeRemoveCircleFromGroup(circle);
}

function handlePrototypeBoardDrop(event) {
  const participantId = getDraggedParticipantId(event);
  const participant = viewState.participants.find((entry) => entry.participantId === participantId);
  if (!participantId || !participant || participant.active === false) {
    return;
  }

  const bounds = prototypeGetBounds();
  const pointer = getCanvasPoint(event.clientX, event.clientY);
  const x = clampToBounds(pointer.x, 24, bounds.width - 24);
  const y = clampToBounds(pointer.y, 24, bounds.height - 24);
  const circle = ensurePrototypeCircleElement(
    {
      ...participant,
      canvasPosition: {
        x: clampToBounds(x / Math.max(1, bounds.width), 0.03, 0.97),
        y: clampToBounds(y / Math.max(1, bounds.height), 0.04, 0.96),
      },
    },
    bounds
  );

  let dropGroup = null;
  let dropScore = Number.POSITIVE_INFINITY;
  prototypeBoard.groups.forEach((group) => {
    const dCenter = Math.hypot(x - group.centerX, y - group.centerY);
    const score = dCenter <= group.radius ? 0 : dCenter - group.radius;
    if (score <= dockCaptureMargin() && score < dropScore) {
      dropScore = score;
      dropGroup = group;
    }
  });

  if (dropGroup) {
    prototypeRemoveCircleFromGroup(circle);
    circle.groupId = dropGroup.id;
    dropGroup.members.push(circle.id);
    if (dropGroup.members.length > 1) {
      dropGroup.radius = prototypeGetGroupRadius(dropGroup.members.length);
      prototypeApplyLayout(dropGroup, dropGroup.members, dropGroup.startAngle);
    } else {
      dropGroup.radius = 34;
      const point = prototypeGetSingleMemberPosition(dropGroup);
      prototypeSetCirclePos(circle, point.x, point.y);
    }
    prototypeMarkClusterTargetsDirty([prototypeEntityKey("group", dropGroup.id)]);
  } else {
  prototypeRemoveCircleFromGroup(circle);
  prototypeSetCirclePos(circle, x, y);
  circle.targetX = x;
  circle.targetY = y;
  prototypeMarkClusterTargetsDirty([prototypeEntityKey("single", circle.id)]);
  }

  renderParticipantsPanel();
  renderEmptyState();
  prototypeSyncAssignmentsToServer();
}

function renderPrototypeBoard() {
  whiteboardGroupLayer.innerHTML = "";
  prototypeSyncFromParticipants();
  prototypeSyncGroupLabels();
  renderEmptyState();
  prototypeEnsureLoop();
}

function renderGroups() {
  if (FORCE_PROTOTYPE_BOARD) {
    renderPrototypeBoard();
    return;
  }

  const previousGroupRects = captureElementRects(whiteboardGroupLayer, ".group-flower", "groupStableId");
  const previousParticipantRects = captureElementRects(whiteboardLooseLayer, ".canvas-participant", "participantId");
  const skipParticipantId = String(dragState.skipFlipParticipantId || "").trim();
  const participantFlipOptions = skipParticipantId
    ? { skipKeys: new Set([skipParticipantId]) }
    : {};

  whiteboardGroupLayer.innerHTML = "";

  const groups = Array.isArray(viewState.groups) ? viewState.groups : [];
  viewState.groupLayout = [];

  const activeStableIds = new Set(groups.map((group) => String(group.stableId || group.groupId || "")).filter(Boolean));
  Object.keys(viewState.groupMemberSlotsByStableId).forEach((stableId) => {
    if (!activeStableIds.has(stableId)) {
      delete viewState.groupMemberSlotsByStableId[stableId];
    }
  });
  Object.keys(viewState.groupStartAngleByStableId).forEach((stableId) => {
    if (!activeStableIds.has(stableId)) {
      delete viewState.groupStartAngleByStableId[stableId];
    }
  });

  if (groups.length === 0) {
    cancelGroupSettleRender();
    viewState.groupRenderCentersByStableId = {};
    renderCanvasParticipants();
    renderEmptyState();
    playFlipAnimation(
      whiteboardLooseLayer,
      ".canvas-participant",
      "participantId",
      previousParticipantRects,
      participantFlipOptions
    );
    dragState.skipFlipParticipantId = null;
    return;
  }

  const bounds = {
    width: Math.max(320, whiteboardCanvas.clientWidth || 320),
    height: Math.max(320, whiteboardCanvas.clientHeight || 320),
  };
  const centers = getGroupCenters(groups, bounds);

  groups.forEach((group, index) => {
    const center = centers[index];
    if (!center) {
      return;
    }
    const memberCount = Array.isArray(group.members) ? group.members.length : 0;
    const flower = document.createElement("section");
    flower.className = "group-flower";
    flower.dataset.groupStableId = String(center.stableId || group.stableId || group.groupId || "");
    flower.dataset.groupId = String(group.groupId || "");
    const totalGroups = Math.max(1, groups.length);
    const countFactor = Math.max(0.8, Math.min(1.18, 1.14 - Math.min(0.36, (totalGroups - 1) * 0.035)));
    const memberFactor = Math.max(0.86, Math.min(1.06, 1 - Math.max(0, memberCount - 3) * 0.028));
    const scale = Math.max(0.68, Math.min(1.16, ((center.radius || 56) / 76) * countFactor * memberFactor));
    flower.style.setProperty("--group-scale", scale.toFixed(2));
    flower.style.setProperty("--group-radius", `${Math.round(center.radius || 56)}px`);
    flower.style.left = `${center.x}px`;
    flower.style.top = `${center.y}px`;

    const frame = document.createElement("div");
    frame.className = "group-frame";
    frame.setAttribute("aria-hidden", "true");
    flower.appendChild(frame);

    if (memberCount !== 2) {
      const labelWrap = document.createElement("div");
      labelWrap.className = "group-label-wrap";

      const title = document.createElement("h3");
      title.className = "group-label";
      title.textContent = group.label;
      title.tabIndex = 0;
      title.setAttribute("aria-label", `${group.label} umbenennen`);
      title.addEventListener("click", () => beginInlineGroupRename(group, title));
      title.addEventListener("keydown", (event) => {
        if (title.contentEditable === "true") {
          return;
        }

        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          beginInlineGroupRename(group, title);
        }
      });

      labelWrap.appendChild(title);
      flower.appendChild(labelWrap);
    }

    whiteboardGroupLayer.appendChild(flower);
    viewState.groupLayout.push({
      groupId: group.groupId,
      stableId: String(center.stableId || group.stableId || group.groupId || ""),
      center,
      radius: center.radius,
    });
  });

  renderCanvasParticipants();
  renderEmptyState();
  playFlipAnimation(whiteboardGroupLayer, ".group-flower", "groupStableId", previousGroupRects);
  playFlipAnimation(
    whiteboardLooseLayer,
    ".canvas-participant",
    "participantId",
    previousParticipantRects,
    participantFlipOptions
  );
  dragState.skipFlipParticipantId = null;
}

if (sessionInfo) {
  sessionInfo.textContent = activityLabel;
}

socket.on("connect", () => {
  socket.emit("init", { role: "teacher" });
});

socket.on("session:joined", () => {
  clearError();
});

socket.on("session:error", ({ message }) => {
  setError(message || "Daten konnten nicht geladen werden");
});

socket.on("participants:update", (participants) => {
  viewState.participants = normalizeParticipants(participants);
  viewState.totalParticipants = viewState.participants.filter((participant) => participant.active !== false).length;
  renderParticipantsPanel();
  renderGroupControls();
  renderGroups();
});

socket.on("groups:update", (payload) => {
  const normalized = normalizeGroups(payload);
  viewState.groupCount = normalized.groupCount;
  viewState.groupMode = normalized.groupMode;
  viewState.totalParticipants = normalized.totalParticipants;
  viewState.groups = normalized.groups;
  if (dragState.pendingGroupInsertHint) {
    const pending = dragState.pendingGroupInsertHint;
    const matchingGroup = viewState.groups.find((group) => String(group.stableId || group.groupId || "") === pending.stableId);
    const stillRelevant = Boolean(
      matchingGroup &&
      Array.isArray(matchingGroup.members) &&
      matchingGroup.members.some((member) => String(member.participantId || "") === pending.participantId)
    );

    if (!stillRelevant) {
      dragState.pendingGroupInsertHint = null;
    }
  }
  if (dragState.persistPreviewUntilGroupsUpdate) {
    dragState.persistPreviewUntilGroupsUpdate = false;
    dragState.preview = null;
  }
  renderGroupControls();
  renderGroups();
  renderParticipantsPanel();
});

socket.on("connect_error", (error) => {
  setError(error.message || "Verbindung fehlgeschlagen");
});

participantsToggleButton.addEventListener("click", () => {
  setParticipantsPanelOpen(!viewState.participantsPanelOpen);
});

participantsPanel.addEventListener("dragover", (event) => {
  const participantId = getDraggedParticipantId(event);
  if (!participantId) {
    return;
  }

  const participant = viewState.participants.find((entry) => entry.participantId === participantId);
  if (!participant) {
    return;
  }

  event.preventDefault();
  participantsPanel.classList.add("is-drop-active");
});

participantsPanel.addEventListener("dragleave", (event) => {
  const nextTarget = event.relatedTarget;
  if (nextTarget instanceof Node && participantsPanel.contains(nextTarget)) {
    return;
  }

  participantsPanel.classList.remove("is-drop-active");
});

participantsPanel.addEventListener("drop", (event) => {
  event.preventDefault();
  participantsPanel.classList.remove("is-drop-active");

  const participantId = getDraggedParticipantId(event);
  if (!participantId) {
    return;
  }

  dragState.skipFlipParticipantId = participantId;

  if (FORCE_PROTOTYPE_BOARD) {
    prototypeRemoveCircleByParticipantId(participantId);
    renderParticipantsPanel();
    renderEmptyState();
  }

  socket.emit("teacher:participant:unassign", {
    participantId,
  });
});

whiteboardCanvas.addEventListener("dragover", (event) => {
  participantsPanel.classList.remove("is-drop-active");

  if (FORCE_PROTOTYPE_BOARD) {
    const participantId = getDraggedParticipantId(event);
    const participant = viewState.participants.find((entry) => entry.participantId === participantId);
    if (!participantId) {
      whiteboardCanvas.classList.remove("is-drop-active");
      return;
    }

    if (!participant || participant.active === false) {
      whiteboardCanvas.classList.remove("is-drop-active");
      return;
    }

    event.preventDefault();
    whiteboardCanvas.classList.add("is-drop-active");
    return;
  }

  const participantId = getDraggedParticipantId(event);
  if (!participantId) {
    clearDragPreview();
    setConnectionTarget(null);
    return;
  }

  const participant = viewState.participants.find((entry) => entry.participantId === participantId);
  if (!participant || participant.active === false) {
    clearDragPreview();
    setConnectionTarget(null);
    return;
  }

  event.preventDefault();
  whiteboardCanvas.classList.add("is-drop-active");

  const connectionTargetId = getParticipantDropTarget(event, participantId);
  setConnectionTarget(connectionTargetId);
  if (connectionTargetId) {
    clearDragPreview();
    return;
  }

  const pointer = getCanvasPoint(event.clientX, event.clientY);
  let nearestGroup = getNearestGroupLayout(pointer);
  if (!nearestGroup) {
    if (shouldKeepCurrentGroupPreview(pointer, participant)) {
      return;
    }

    clearDragPreview();
    return;
  }

  if (
    dragState.origin === "canvas" &&
    participant.groupId &&
    nearestGroup.groupId === participant.groupId
  ) {
    clearDragPreview();
    return;
  }

  const dockRadius = getDockRadius(nearestGroup);
  const captureDistance = isBehaviorV2Enabled()
    ? getPreviewCaptureDistance(nearestGroup)
    : dockRadius + 46;

  if (nearestGroup.distance > captureDistance) {
    if (shouldKeepCurrentGroupPreview(pointer, participant)) {
      const currentLayout = getGroupLayoutById(dragState.preview.groupId);
      if (currentLayout) {
        nearestGroup = {
          ...currentLayout,
          distance: Math.hypot(pointer.x - currentLayout.center.x, pointer.y - currentLayout.center.y),
        };
      } else {
        clearDragPreview();
        return;
      }
    } else {
      clearDragPreview();
      return;
    }
  }

  const endpoint = getDockEndpoint(nearestGroup.center, pointer, dockRadius);
  const rawStrength = (captureDistance - nearestGroup.distance) / Math.max(1, captureDistance - dockRadius);
  const previewStrength = smoothStep01(rawStrength);
  const expansionFactor = 1 + (previewStrength * 0.22);
  const previewGroup = getGroupById(nearestGroup.groupId);
  const memberCount = Array.isArray(previewGroup?.members) ? previewGroup.members.length : 0;
  const pairMemberIds = memberCount === 2
    ? previewGroup.members.map((member) => String(member.participantId || "")).filter(Boolean)
    : [];
  const previousSlotIndex =
    dragState.preview?.groupId === nearestGroup.groupId && Number.isInteger(dragState.preview?.slotIndex)
      ? dragState.preview.slotIndex
      : null;
  const previousPairVariant =
    dragState.preview?.groupId === nearestGroup.groupId && typeof dragState.preview?.pairVariant === "string"
      ? dragState.preview.pairVariant
      : null;

  let slotIndex = 0;
  let pairVariant = null;
  if (isBehaviorV2Enabled() && memberCount > 0) {
    if (memberCount === 2) {
      const hasDeadZone = Math.abs(endpoint.y - nearestGroup.center.y) < PAIR_PREVIEW_DEAD_ZONE;
      if (hasDeadZone && previousPairVariant) {
        pairVariant = previousPairVariant;
      } else {
        pairVariant = endpoint.y < nearestGroup.center.y ? "top" : "bottom";
      }

      slotIndex = pairVariant === "top" ? 0 : 2;
    } else {
      const incomingAngle = Math.atan2(endpoint.y - nearestGroup.center.y, endpoint.x - nearestGroup.center.x);
      slotIndex = getPreviewSlotIndex(memberCount + 1, incomingAngle, previousSlotIndex);
    }
  }

  setDragPreview({
    groupId: nearestGroup.groupId,
    stableId: nearestGroup.stableId,
    center: nearestGroup.center,
    dockRadius,
    endpoint,
    strength: previewStrength,
    expansionFactor,
    slotIndex,
    pairVariant,
    pairMemberIds,
    participantColor: getParticipantColor(participantId),
    draggedParticipantId: participantId,
    isInsideOwnGroup: Boolean(participant.groupId && nearestGroup.groupId === participant.groupId),
  });
});

whiteboardCanvas.addEventListener("dragleave", (event) => {
  const nextTarget = event.relatedTarget;
  if (nextTarget instanceof Node && whiteboardCanvas.contains(nextTarget)) {
    return;
  }

  whiteboardCanvas.classList.remove("is-drop-active");
  clearDragPreview();
  setConnectionTarget(null);
  participantsPanel.classList.remove("is-drop-active");
});

whiteboardCanvas.addEventListener("drop", (event) => {
  event.preventDefault();
  whiteboardCanvas.classList.remove("is-drop-active");
  participantsPanel.classList.remove("is-drop-active");

  if (FORCE_PROTOTYPE_BOARD) {
    handlePrototypeBoardDrop(event);
    return;
  }

  const participantId = getDraggedParticipantId(event);
  const connectionTargetId = getParticipantDropTarget(event, participantId);
  const preview = dragState.preview;
  const pointer = getCanvasPoint(event.clientX, event.clientY);
  const nearestGroup = getNearestGroupLayout(pointer);
  const participant = viewState.participants.find((entry) => entry.participantId === participantId);
  if (!participant || participant.active === false) {
    return;
  }

  const shouldPersistPreview = Boolean(preview?.groupId && !preview.isInsideOwnGroup && !connectionTargetId);
  dragState.persistPreviewUntilGroupsUpdate = shouldPersistPreview;
  if (!shouldPersistPreview) {
    clearDragPreview();
  }
  setConnectionTarget(null);

  if (!participantId) {
    return;
  }
  dragState.skipFlipParticipantId = null;

  if (connectionTargetId) {
    const targetParticipant = viewState.participants.find((entry) => entry.participantId === connectionTargetId);
    if (
      participant?.groupId &&
      targetParticipant?.groupId &&
      participant.groupId !== targetParticipant.groupId
    ) {
      socket.emit("teacher:group:merge", {
        sourceGroupId: participant.groupId,
        targetGroupId: targetParticipant.groupId,
      });
      return;
    }

    socket.emit("teacher:group:createFromPair", {
      sourceParticipantId: participantId,
      targetParticipantId: connectionTargetId,
    });
    return;
  }

  if (preview?.groupId && !preview.isInsideOwnGroup) {
    dragState.persistPreviewUntilGroupsUpdate = true;

    if (
      isBehaviorV2Enabled() &&
      preview.stableId &&
      participantId &&
      (preview.pairVariant === "top" || preview.pairVariant === "bottom")
    ) {
      dragState.pendingGroupInsertHint = {
        stableId: String(preview.stableId),
        participantId: String(participantId),
        pairVariant: preview.pairVariant,
        pairMemberIds: Array.isArray(preview.pairMemberIds)
          ? preview.pairMemberIds.map((id) => String(id || "")).filter(Boolean)
          : [],
      };
    }

    socket.emit("teacher:participant:assignToGroup", {
      participantId,
      groupId: preview.groupId,
    });
    return;
  }

  if (
    nearestGroup?.groupId &&
    nearestGroup.distance <= getDockRadius(nearestGroup) &&
    !(participant?.groupId && nearestGroup.groupId === participant.groupId)
  ) {
    socket.emit("teacher:participant:assignToGroup", {
      participantId,
      groupId: nearestGroup.groupId,
    });
    return;
  }

  dragState.skipFlipParticipantId = participantId;
  const position = getCanvasDropPosition(event.clientX, event.clientY);
  socket.emit("teacher:participant:placeOnCanvas", {
    participantId,
    x: position.x,
    y: position.y,
  });
});

groupPlusButton.addEventListener("click", () => {
  if (FORCE_PROTOTYPE_BOARD) {
    if (prototypeBoard.groups.length < MAX_GROUPS) {
      prototypeAddEmptyGroup();
    }
    return;
  }

  socket.emit("teacher:group:increment");
});

groupMinusButton.addEventListener("click", () => {
  if (FORCE_PROTOTYPE_BOARD) {
    prototypeRemoveOneGroup();
    return;
  }

  socket.emit("teacher:group:decrement");
});

groupModeSwitchButton.addEventListener("click", () => {
  const nextMode = viewState.groupMode === "partner" ? "groups" : "partner";
  viewState.groupMode = nextMode;
  renderGroupControls();
  if (FORCE_PROTOTYPE_BOARD) {
    return;
  }

  socket.emit("teacher:group:togglePartnerMode", { mode: nextMode });
});

autoAssignButton.addEventListener("click", () => {
  if (FORCE_PROTOTYPE_BOARD) {
    prototypeAutoAssign(viewState.groupMode);
    return;
  }

  socket.emit("teacher:group:autoAssign", { mode: viewState.groupMode });
});

let resizeRafId = null;
window.addEventListener("resize", () => {
  if (resizeRafId) {
    window.cancelAnimationFrame(resizeRafId);
  }

  resizeRafId = window.requestAnimationFrame(() => {
    resizeRafId = null;
    syncTopbarHeight();
    updateCanvasInsets();
    renderGroups();
  });
});

// Der Canvas bekommt seine echte Groesse erst nach dem ersten Layout. Ein
// ResizeObserver stellt sicher, dass die Teilnehmenden auch bei breitem
// Fenster korrekt platziert werden (nicht nur nach manuellem Resize).
if (typeof ResizeObserver !== "undefined" && whiteboardCanvas) {
  let canvasSizeRafId = null;
  const canvasResizeObserver = new ResizeObserver(() => {
    if (canvasSizeRafId) {
      window.cancelAnimationFrame(canvasSizeRafId);
    }
    canvasSizeRafId = window.requestAnimationFrame(() => {
      canvasSizeRafId = null;
      updateCanvasInsets();
      renderGroups();
    });
  });
  canvasResizeObserver.observe(whiteboardCanvas);
}

setParticipantsPanelOpen(true);
syncTopbarHeight();
updateCanvasInsets();
renderParticipantsPanel();
renderGroupControls();
renderGroups();
