(function () {
  'use strict';

  var API = (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function')
    ? Birkly.url('/api/index.php?route=events')
    : '/api/index.php?route=events';

  var calMonth = new Date();

  function apiCall(action, params, options) {
    options = options || {};
    var url = API + '&action=' + encodeURIComponent(action);
    if (params) {
      Object.keys(params).forEach(function (key) {
        if (params[key] !== '' && params[key] != null) {
          url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }
      });
    }
    var fetchOpts = { credentials: 'same-origin' };
    if (options.method) fetchOpts.method = options.method;
    if (options.body) {
      fetchOpts.method = 'POST';
      fetchOpts.headers = { 'Content-Type': 'application/json' };
      fetchOpts.body = JSON.stringify(options.body);
    }
    return fetch(url, fetchOpts).then(function (r) { return r.json(); });
  }

  function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function formatDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString(); } catch (e) { return iso; }
  }

  function switchTab(tabId) {
    document.querySelectorAll('.events-tab').forEach(function (btn) {
      var active = btn.getAttribute('data-tab') === tabId;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('.events-tab-panel').forEach(function (panel) {
      var show = panel.id === 'events-tab-' + tabId;
      panel.hidden = !show;
      panel.classList.toggle('active', show);
    });
    if (tabId === 'overview') loadOverview();
    if (tabId === 'calendar') loadCalendar();
    if (tabId === 'events-list') loadIndexedEvents();
    if (tabId === 'attendees') { loadEventsFilter(); loadRegistrations(); }
    if (tabId === 'messages') loadMessageEvents();
    if (tabId === 'documentation' && window.BirklyPluginDocs) {
      window.BirklyPluginDocs.activate('events', 'events-tab-documentation');
    }
  }

  function loadMessageEvents() {
    var sel = document.getElementById('events-msg-event');
    if (!sel) return;
    Promise.all([
      apiCall('list_indexed_events'),
      apiCall('list_events'),
    ]).then(function (results) {
      var indexed = (results[0].status === 'ok' ? results[0].data.events : []) || [];
      var legacy = (results[1].status === 'ok' ? results[1].data.events : []) || [];
      var current = sel.value;
      var html = '<option value="">Select an event…</option>';
      indexed.forEach(function (e) {
        html += '<option value="entry:' + escapeHtml(e.entry_id) + '" data-entry-id="' + escapeHtml(e.entry_id) + '" data-collection="' + escapeHtml(e.collection_slug || '') + '">' +
          escapeHtml(e.entry_title) + ' (indexed)</option>';
      });
      legacy.forEach(function (e) {
        html += '<option value="legacy:' + escapeHtml(e.id) + '" data-event-id="' + escapeHtml(e.id) + '">' +
          escapeHtml(e.title) + ' (legacy)</option>';
      });
      sel.innerHTML = html;
      if (current) sel.value = current;
      if (sel.value) loadMessageAttendees();
    });
  }

  function renderMessageAttendees(items) {
    var list = document.getElementById('events-msg-attendees-list');
    var selectAll = document.getElementById('events-msg-select-all');
    if (!list) return;
    if (!items.length) {
      list.innerHTML = '<p class="form-text">No attendees for this event.</p>';
      if (selectAll) selectAll.checked = false;
      return;
    }
    list.innerHTML = items.map(function (r, index) {
      var email = r.email || '';
      return '<label class="events-msg-attendee-row">' +
        '<input type="checkbox" class="events-msg-attendee-cb" value="' + escapeHtml(email) + '" checked data-index="' + index + '">' +
        '<span class="events-msg-attendee-meta"><strong>' + escapeHtml(r.name || 'Attendee') + '</strong>' +
        '<span>' + escapeHtml(email) + '</span></span></label>';
    }).join('');
    if (selectAll) {
      selectAll.checked = true;
      selectAll.onchange = function () {
        list.querySelectorAll('.events-msg-attendee-cb').forEach(function (cb) {
          cb.checked = selectAll.checked;
        });
      };
    }
    list.querySelectorAll('.events-msg-attendee-cb').forEach(function (cb) {
      cb.addEventListener('change', function () {
        if (!selectAll) return;
        var boxes = list.querySelectorAll('.events-msg-attendee-cb');
        selectAll.checked = Array.prototype.every.call(boxes, function (box) { return box.checked; });
      });
    });
  }

  function loadMessageAttendees() {
    var sel = document.getElementById('events-msg-event');
    var list = document.getElementById('events-msg-attendees-list');
    if (!sel || !list) return;
    var value = sel.value || '';
    if (!value) {
      list.innerHTML = '<p class="form-text">Select an event to load attendees.</p>';
      return;
    }
    list.innerHTML = '<p class="form-text">Loading attendees…</p>';
    var params = {};
    if (value.indexOf('entry:') === 0) {
      params.entry_id = value.slice(6);
      var opt = sel.options[sel.selectedIndex];
      if (opt && opt.getAttribute('data-collection')) {
        params.collection = opt.getAttribute('data-collection');
      }
    } else if (value.indexOf('legacy:') === 0) {
      params.event_id = value.slice(7);
    }
    apiCall('list_attendees', params).then(function (res) {
      if (res.status === 'ok') {
        renderMessageAttendees(res.data.attendees || res.data.registrations || []);
      } else {
        list.innerHTML = '<p class="form-text">Failed to load attendees.</p>';
      }
    });
  }

  function getSelectedMessageEmails() {
    return Array.prototype.map.call(
      document.querySelectorAll('.events-msg-attendee-cb:checked'),
      function (cb) { return cb.value; }
    ).filter(Boolean);
  }

  function loadOverview() {
    apiCall('overview').then(function (res) {
      var el = document.getElementById('events-overview-stats');
      if (!el || res.status !== 'ok') return;
      var d = res.data || {};
      var revenue = ((d.revenue_cents || 0) / 100).toFixed(2);
      el.innerHTML =
        '<div class="events-stat-card"><span class="events-stat-value">' + escapeHtml(d.upcoming) + '</span><span class="events-stat-label">Upcoming occurrences</span></div>' +
        '<div class="events-stat-card"><span class="events-stat-value">' + escapeHtml(d.past) + '</span><span class="events-stat-label">Past occurrences</span></div>' +
        '<div class="events-stat-card"><span class="events-stat-value">' + escapeHtml(d.indexed_events) + '</span><span class="events-stat-label">Indexed events</span></div>' +
        '<div class="events-stat-card"><span class="events-stat-value">' + escapeHtml(d.attendees) + '</span><span class="events-stat-label">Attendees</span></div>' +
        '<div class="events-stat-card"><span class="events-stat-value">$' + escapeHtml(revenue) + '</span><span class="events-stat-label">Legacy paid revenue</span></div>';
      var feedLink = document.getElementById('events-feed-link');
      if (feedLink && d.feed_url) feedLink.href = d.feed_url;
    });
  }

  function monthRange(date) {
    var y = date.getFullYear();
    var m = date.getMonth();
    var from = y + '-' + String(m + 1).padStart(2, '0') + '-01';
    var last = new Date(y, m + 1, 0).getDate();
    var to = y + '-' + String(m + 1).padStart(2, '0') + '-' + String(last).padStart(2, '0');
    return { from: from, to: to };
  }

  function loadCalendar() {
    var range = monthRange(calMonth);
    var titleEl = document.getElementById('events-cal-title');
    if (titleEl) titleEl.textContent = calMonth.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    apiCall('list_occurrences', range).then(function (res) {
      var grid = document.getElementById('events-calendar-grid');
      if (!grid || res.status !== 'ok') return;
      var occs = res.data.occurrences || [];
      var byDay = {};
      occs.forEach(function (o) {
        var day = (o.starts_at || '').substring(0, 10);
        if (!byDay[day]) byDay[day] = [];
        byDay[day].push(o);
      });
      var y = calMonth.getFullYear();
      var m = calMonth.getMonth();
      var firstDow = new Date(y, m, 1).getDay();
      var daysInMonth = new Date(y, m + 1, 0).getDate();
      var html = '<div class="events-cal-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div><div class="events-cal-days">';
      for (var i = 0; i < firstDow; i++) html += '<div class="events-cal-day empty"></div>';
      for (var d = 1; d <= daysInMonth; d++) {
        var key = y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        var items = byDay[key] || [];
        html += '<div class="events-cal-day"><span class="events-cal-day-num">' + d + '</span>';
        items.slice(0, 3).forEach(function (o) {
          html += '<div class="events-cal-event" title="' + escapeHtml(o.entry_title) + '">' + escapeHtml(o.entry_title) + '</div>';
        });
        if (items.length > 3) html += '<div class="events-cal-more">+' + (items.length - 3) + ' more</div>';
        html += '</div>';
      }
      html += '</div>';
      grid.innerHTML = html;
    });
  }

  function loadIndexedEvents() {
    apiCall('list_indexed_events').then(function (res) {
      var el = document.getElementById('events-indexed-list');
      if (!el || res.status !== 'ok') return;
      var items = res.data.events || [];
      if (!items.length) {
        el.innerHTML = '<div class="events-empty">No indexed events. Add an <strong>event</strong> fieldtype to entries and reindex.</div>';
        return;
      }
      el.innerHTML =
        '<table class="events-table smart-table"><thead><tr>' +
        '<th>Title</th><th>Collection</th><th>Starts</th><th>Recurrence</th><th>Occurrences</th><th></th>' +
        '</tr></thead><tbody>' +
        items.map(function (e) {
          var ev = e.event || {};
          var rec = ev.recurrence && ev.recurrence.enabled ? ev.recurrence.frequency : '—';
          var editUrl = '/admin/index.php?page=entry-editor&collection=' + encodeURIComponent(e.collection_slug) + '&entry=' + encodeURIComponent(e.entry_id);
          return '<tr>' +
            '<td>' + escapeHtml(e.entry_title) + '</td>' +
            '<td><code>' + escapeHtml(e.collection_slug) + '</code></td>' +
            '<td>' + escapeHtml(formatDate(ev.starts_at)) + '</td>' +
            '<td>' + escapeHtml(rec) + '</td>' +
            '<td>' + escapeHtml((e.occurrences || []).length) + '</td>' +
            '<td><a class="btn btn-secondary btn-sm" href="' + escapeHtml(editUrl) + '">Edit</a></td>' +
            '</tr>';
        }).join('') +
        '</tbody></table>';
    });
  }

  function renderRegistrations(items) {
    var el = document.getElementById('events-registrations-list');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = '<div class="events-empty">No attendees yet.</div>';
      return;
    }
    el.innerHTML =
      '<table class="events-table smart-table"><thead><tr>' +
      '<th>Source</th><th>Name</th><th>Email</th><th>Event</th><th>Ticket</th><th>Status</th><th>Registered</th>' +
      '</tr></thead><tbody>' +
      items.map(function (r) {
        var status = (r.status || '').toLowerCase();
        return '<tr>' +
          '<td><span class="status-pill">' + escapeHtml(r.source || '') + '</span></td>' +
          '<td>' + escapeHtml(r.name) + '</td>' +
          '<td>' + escapeHtml(r.email) + '</td>' +
          '<td>' + escapeHtml(r.event_title || r.entry_id || r.event_id) + '</td>' +
          '<td>' + escapeHtml(r.ticket_name || r.ticket_type) + '</td>' +
          '<td><span class="status-pill ' + escapeHtml(status) + '">' + escapeHtml(r.status) + '</span></td>' +
          '<td>' + escapeHtml(r.registered_at || r.created_at || '') + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';
  }

  function loadEventsFilter() {
    apiCall('list_events').then(function (res) {
      var sel = document.getElementById('events-filter-event');
      if (!sel || res.status !== 'ok') return;
      var current = sel.value;
      sel.innerHTML = '<option value="">All events (legacy)</option>' +
        (res.data.events || []).map(function (e) {
          return '<option value="' + escapeHtml(e.id) + '">' + escapeHtml(e.title) + '</option>';
        }).join('');
      if (current) sel.value = current;
    });
  }

  function loadRegistrations() {
    var eventId = document.getElementById('events-filter-event')?.value || '';
    apiCall('list_attendees', eventId ? { event_id: eventId } : null).then(function (res) {
      if (res.status === 'ok') {
        renderRegistrations(res.data.attendees || res.data.registrations || []);
      }
    });
  }

  function init() {
    document.querySelectorAll('.events-tab').forEach(function (btn) {
      btn.addEventListener('click', function () { switchTab(btn.getAttribute('data-tab')); });
    });
    document.getElementById('events-reindex')?.addEventListener('click', function () {
      apiCall('reindex', null, { method: 'POST' }).then(function (res) {
        if (res.status === 'ok') { loadOverview(); loadIndexedEvents(); loadCalendar(); }
      });
    });
    document.getElementById('events-export-ics')?.addEventListener('click', function () {
      window.location.href = API + '&action=export_ics';
    });
    document.getElementById('events-refresh-list')?.addEventListener('click', loadIndexedEvents);
    document.getElementById('events-refresh')?.addEventListener('click', loadRegistrations);
    document.getElementById('events-filter-event')?.addEventListener('change', loadRegistrations);
    document.getElementById('events-export-csv')?.addEventListener('click', function () {
      var eventId = document.getElementById('events-filter-event')?.value || '';
      var url = API + '&action=export_registrations';
      if (eventId) url += '&event_id=' + encodeURIComponent(eventId);
      window.location.href = url;
    });
    document.getElementById('events-cal-prev')?.addEventListener('click', function () {
      calMonth.setMonth(calMonth.getMonth() - 1); loadCalendar();
    });
    document.getElementById('events-cal-next')?.addEventListener('click', function () {
      calMonth.setMonth(calMonth.getMonth() + 1); loadCalendar();
    });
    document.getElementById('events-cal-today')?.addEventListener('click', function () {
      calMonth = new Date(); loadCalendar();
    });
    document.getElementById('events-msg-event')?.addEventListener('change', loadMessageAttendees);
    document.getElementById('events-msg-send')?.addEventListener('click', function () {
      var statusEl = document.getElementById('events-msg-status');
      var sel = document.getElementById('events-msg-event');
      var value = sel?.value || '';
      var body = {
        subject: document.getElementById('events-msg-subject')?.value || '',
        body: document.getElementById('events-msg-body')?.value || '',
        recipient_emails: getSelectedMessageEmails(),
      };
      if (value.indexOf('entry:') === 0) {
        body.entry_id = value.slice(6);
        var opt = sel.options[sel.selectedIndex];
        if (opt && opt.getAttribute('data-collection')) {
          body.collection = opt.getAttribute('data-collection');
        }
      } else if (value.indexOf('legacy:') === 0) {
        body.event_id = value.slice(7);
      }
      if (!value) {
        if (statusEl) {
          statusEl.hidden = false;
          statusEl.textContent = 'Select an event first.';
          statusEl.className = 'events-msg-status error';
        }
        return;
      }
      apiCall('message_attendees', null, { method: 'POST', body: body }).then(function (res) {
        if (!statusEl) return;
        statusEl.hidden = false;
        if (res.status === 'ok') {
          statusEl.textContent = 'Queued for ' + (res.data.recipient_count || 0) + ' recipient(s). Use Automation to deliver.';
          statusEl.className = 'events-msg-status ok';
        } else {
          statusEl.textContent = res.message || 'Failed';
          statusEl.className = 'events-msg-status error';
        }
      });
    });
    loadOverview();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
