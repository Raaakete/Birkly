(function () {
  'use strict';

  var API = (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function')
    ? Birkly.url('/api/index.php?route=shopping_cart')
    : '/api/index.php?route=shopping_cart';

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function apiCall(action, data, method) {
    method = method || 'GET';
    var url = API + '&action=' + encodeURIComponent(action);
    var opts = { method: method, credentials: 'same-origin', headers: {} };
    if (method === 'POST') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(Object.assign({ action: action, _csrf: csrfToken() }, data || {}));
    }
    return fetch(url, opts).then(function (r) { return r.json(); });
  }

  function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function formatMoney(cents, currency) {
    return ((cents || 0) / 100).toFixed(2) + ' ' + (currency || 'usd').toUpperCase();
  }

  function initTabs() {
    document.querySelectorAll('.shop-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var name = tab.getAttribute('data-tab');
        document.querySelectorAll('.shop-tab').forEach(function (t) {
          t.classList.toggle('active', t === tab);
        });
        document.querySelectorAll('.shop-tab-panel').forEach(function (panel) {
          var active = panel.id === 'shop-tab-' + name;
          panel.classList.toggle('active', active);
          panel.hidden = !active;
        });
      });
    });
  }

  function renderOrders(orders) {
    var el = document.getElementById('shop-orders-list');
    if (!el) return;
    if (!orders.length) {
      el.innerHTML = '<div class="shop-empty">No orders yet.</div>';
      return;
    }
    el.innerHTML =
      '<table class="shop-table"><thead><tr>' +
      '<th>Order</th><th>Status</th><th>Total</th><th>Payment</th><th>Email</th><th>Created</th><th></th>' +
      '</tr></thead><tbody>' +
      orders.map(function (o) {
        var status = (o.status || 'pending').toLowerCase();
        return '<tr>' +
          '<td><code>' + escapeHtml(o.id) + '</code></td>' +
          '<td><span class="status-pill ' + escapeHtml(status) + '">' + escapeHtml(o.status || '') + '</span></td>' +
          '<td>' + escapeHtml(formatMoney(o.total_cents, o.currency)) + '</td>' +
          '<td><code style="font-size:0.75rem">' + escapeHtml(o.payment_id || '—') + '</code></td>' +
          '<td>' + escapeHtml(o.customer_email || '—') + '</td>' +
          '<td>' + escapeHtml(o.created_at || '') + '</td>' +
          '<td>' + (status === 'pending' ? '<button class="btn btn-secondary btn-sm" data-cancel="' + escapeHtml(o.id) + '">Cancel</button>' : '') + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';

    el.querySelectorAll('[data-cancel]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('Cancel this order?')) return;
        apiCall('cancel_order', { id: btn.getAttribute('data-cancel') }, 'POST').then(loadOrders);
      });
    });
  }

  function loadOrders() {
    apiCall('list_orders').then(function (res) {
      if (res.status === 'ok') {
        renderOrders(res.data.orders || []);
      }
    });
  }

  function init() {
    initTabs();
    loadOrders();
    document.getElementById('shop-settings-form')?.addEventListener('submit', function (e) {
      e.preventDefault();
      alert('Settings saved locally — configure via plugin settings API in a future release.');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
