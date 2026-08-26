(function() {
  const GM = window.GM_MOODLE || {};

  function createAdapter() {
    const handlers = new Map();
    const role = GM.isstudentview ? 'student' : 'teacher';
    const clientId = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
    let latestPayload = null;
    let latestVersion = 0;
    let source = null;
    let joined = false;

    function on(event, handler) {
      handlers.set(event, handler);
    }

    function off(event) {
      handlers.delete(event);
    }

    function trigger(event, payload) {
      const handler = handlers.get(event);
      if (typeof handler === 'function') {
        handler(payload);
      }
    }

    function apiRequest(action, data) {
      const params = new URLSearchParams();
      params.set('id', String(GM.cmid || 0));
      params.set('action', action);
      params.set('sesskey', String(GM.sesskey || ''));

      Object.entries(data || {}).forEach(([key, value]) => {
        if (value === undefined || value === null) {
          return;
        }
        params.set(key, String(value));
      });

      return fetch(String(GM.ajaxurl || ''), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        },
        body: params.toString(),
        credentials: 'same-origin',
      }).then((res) => res.json());
    }

    function dispatchPayload(payload) {
      latestPayload = payload;
      const participants = Array.isArray(payload?.participants) ? payload.participants : [];
      const groups = payload?.groups || {
        groupCount: 0,
        groupMode: 'groups',
        totalParticipants: 0,
        groups: [],
      };
      latestVersion = Number(payload?.statemeta?.version || latestVersion || 0);
      trigger('participants:update', participants);
      trigger('groups:update', groups);
    }

    function loadState(reason) {
      return apiRequest('rtc:state_load', {}).then((json) => {
        if (!json?.ok) {
          trigger('session:error', { message: json?.message || 'Laden fehlgeschlagen' });
          return;
        }

        dispatchPayload(json.payload || {});

        if (!joined) {
          joined = true;
          trigger('session:joined', {
            role,
            reason,
          });
        }
      }).catch((error) => {
        trigger('connect_error', {
          message: error && error.message ? error.message : 'Verbindung fehlgeschlagen',
        });
      });
    }

    function openSSE() {
      if (!GM.sseurl) {
        return;
      }

      if (source) {
        source.close();
      }

      const url = `${GM.sseurl}?id=${encodeURIComponent(String(GM.cmid || 0))}&sinceversion=${encodeURIComponent(String(latestVersion || 0))}`;
      source = new EventSource(url, { withCredentials: true });

      source.onopen = () => {
        // Connection established; no-op.
      };

      source.onerror = () => {
        // SSE reconnects automatically when the stream cycles; avoid false alarm UI noise.
      };

      source.addEventListener('state_version', (event) => {
        try {
          const data = JSON.parse(String(event.data || '{}'));
          const incomingVersion = Number(data.version || 0);
          if (incomingVersion <= latestVersion) {
            return;
          }
          latestVersion = incomingVersion;
          loadState('sse');
        } catch (_error) {
          // Ignore malformed SSE data.
        }
      });
    }

    function emit(event, payload) {
      const mappedAction = event;

      if (mappedAction === 'init' || mappedAction === 'rtc:state_load') {
        loadState(mappedAction);
        return;
      }

      apiRequest(mappedAction, payload || {}).then((json) => {
        if (!json?.ok) {
          trigger('session:error', { message: json?.message || 'Aktion fehlgeschlagen' });
          return;
        }

        if (json.payload) {
          dispatchPayload(json.payload);
        }
      }).catch((error) => {
        trigger('connect_error', {
          message: error && error.message ? error.message : 'Verbindung fehlgeschlagen',
        });
      });
    }

    function disconnect() {
      if (source) {
        source.close();
        source = null;
      }
    }

    window.setTimeout(() => {
      trigger('connect', {
        id: clientId,
        role,
      });
      openSSE();
    }, 0);

    return {
      on,
      off,
      emit,
      disconnect,
    };
  }

  window.io = function() {
    return createAdapter();
  };
})();
