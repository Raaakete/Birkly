(function () {
  'use strict';

  var API = (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function')
    ? Birkly.url('/api/index.php?route=events')
    : '/api/index.php?route=events';

  function apiCall(action, params) {
    var url = API + '&action=' + encodeURIComponent(action);
    if (params) {
      Object.keys(params).forEach(function (key) {
        if (params[key] !== '' && params[key] != null) {
          url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }
      });
    }
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function renderRegistrations(items) {
    var el = document.getElementById('events-registrations-list');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = '<div class="events-empty">No registrations yet.</div>';
      return;
    }
    el.innerHTML =
      '<table class="events-table"><thead><tr>' +
      '<th>Name</th><th>Email</th><th>Event</th><th>Ticket</th><th>Status</th><th>Payment</th><th>Registered</th>' +
      '</tr></thead><tbody>' +
      items.map(function (r) {
        var status = (r.status || '').toLowerCase();
        return '<tr>' +
          '<td>' + escapeHtml(r.name) + '</td>' +
          '<td>' + escapeHtml(r.email) + '</td>' +
          '<td>' + escapeHtml(r.event_title || r.event_id) + '</td>' +
          '<td>' + escapeHtml(r.ticket_name || r.ticket_type) + '</td>' +
          '<td><span class="status-pill ' + escapeHtml(status) + '">' + escapeHtml(r.status) + '</span></td>' +
          '<td><code style="font-size:0.75rem">' + escapeHtml(r.payment_id || '—') + '</code></td>' +
          '<td>' + escapeHtml(r.created_at || '') + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';
  }

  function loadEventsFilter() {
    apiCall('list_events').then(function (res) {
      var sel = document.getElementById('events-filter-event');
      if (!sel || res.status !== 'ok') return;
      var current = sel.value;
      sel.innerHTML = '<option value="">All events</option>' +
        (res.data.events || []).map(function (e) {
          return '<option value="' + escapeHtml(e.id) + '">' + escapeHtml(e.title) + '</option>';
        }).join('');
      if (current) sel.value = current;
    });
  }

  function loadRegistrations() {
    var eventId = document.getElementById('events-filter-event')?.value || '';
    apiCall('list_registrations', eventId ? { event_id: eventId } : null).then(function (res) {
      if (res.status === 'ok') {
        renderRegistrations(res.data.registrations || []);
      }
    });
  }

  function init() {
    loadEventsFilter();
    loadRegistrations();
    document.getElementById('events-refresh')?.addEventListener('click', loadRegistrations);
    document.getElementById('events-filter-event')?.addEventListener('change', loadRegistrations);
    document.getElementById('events-export-csv')?.addEventListener('click', function () {
      var eventId = document.getElementById('events-filter-event')?.value || '';
      var url = API + '&action=export_registrations';
      if (eventId) url += '&event_id=' + encodeURIComponent(eventId);
      window.location.href = url;
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
