const socket = io();

const sessionInfo = document.getElementById('sessionInfo');
const errorMessage = document.getElementById('errorMessage');
const studentGroupSection = document.getElementById('studentGroupSection');
const studentGroupState = document.getElementById('studentGroupState');
const studentGroupMembers = document.getElementById('studentGroupMembers');

const GM = window.GM_MOODLE || {};
const participantId = String(GM.userid || '0');
const activityLabel = `Aktivitaet ${String(GM.cmid || '')}`;

function setError(message) {
  errorMessage.textContent = message;
  errorMessage.hidden = false;
}

function setSessionInfo(value) {
  sessionInfo.textContent = 'Kurs ';
  const sessionValue = document.createElement('span');
  sessionValue.className = 'session-id';
  sessionValue.textContent = value;
  sessionInfo.appendChild(sessionValue);
}

function setGroupSectionVisible(isVisible) {
  studentGroupSection.hidden = !isVisible;
}

function renderGroupStateMessage(message) {
  studentGroupState.textContent = message;
  studentGroupMembers.innerHTML = '';
}

function renderOwnGroupMembers(members) {
  studentGroupMembers.innerHTML = '';
  members.forEach((member) => {
    const item = document.createElement('li');
    item.className = 'student-group-member-row';
    item.textContent = member.name || 'Unbenannt';
    studentGroupMembers.appendChild(item);
  });
}

function getOwnGroupMembers(group) {
  const members = Array.isArray(group?.members) ? group.members : [];
  return members.filter((member) => String(member?.participantId || '') !== participantId);
}

function renderOwnGroupDetails(group) {
  const members = getOwnGroupMembers(group);
  const groupName = members.length === 1
    ? `Partnerarbeit mit ${members[0].name || 'Unbenannt'}`
    : String(group?.label || 'Deine Gruppe').trim() || 'Deine Gruppe';

  studentGroupState.textContent = groupName;
  if (members.length <= 1) {
    studentGroupMembers.innerHTML = '';
    return;
  }

  renderOwnGroupMembers(members);
}

function findOwnGroupFromPayload(payload) {
  const groups = Array.isArray(payload?.groups) ? payload.groups : [];
  return groups.find((group) =>
    Array.isArray(group?.members) &&
    group.members.some((member) => String(member?.participantId || '') === participantId)
  );
}

socket.on('connect', () => {
  socket.emit('init', { role: 'student' });
});

socket.on('session:error', ({ message }) => {
  setError(message || 'Daten konnten nicht geladen werden');
});

socket.on('groups:update', (payload) => {
  const ownGroup = findOwnGroupFromPayload(payload);
  if (!ownGroup) {
    renderGroupStateMessage('Noch keine Zuordnung');
    setGroupSectionVisible(true);
    return;
  }

  setGroupSectionVisible(true);
  renderOwnGroupDetails(ownGroup);
});

setSessionInfo(activityLabel);
renderGroupStateMessage('Noch keine Zuordnung');
setGroupSectionVisible(true);
