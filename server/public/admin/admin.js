/* SisterRide admin panel: plain JavaScript, no build step, no CDN.
   Served from /admin and talks to the Laravel API at /api. */
(function () {
  'use strict';

  var API = '/api';
  var store = {
    get token() { return localStorage.getItem('sr_admin_token'); },
    set token(v) { v ? localStorage.setItem('sr_admin_token', v) : localStorage.removeItem('sr_admin_token'); },
    get user() { try { return JSON.parse(localStorage.getItem('sr_admin_user')); } catch (e) { return null; } },
    set user(v) { v ? localStorage.setItem('sr_admin_user', JSON.stringify(v)) : localStorage.removeItem('sr_admin_user'); }
  };
  var badges = { verifications: 0, sos: 0 };

  /* ---------- helpers ---------- */
  // h('div', {class:'x', onclick: fn}, child, 'text', ...) -- text is always inserted safely (no innerHTML).
  function h(tag, attrs) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) return;
      if (k.slice(0, 2) === 'on') node.addEventListener(k.slice(2), v);
      else if (k === 'class') node.className = v;
      else if (k === 'value') node.value = v;
      else if (k === 'checked' || k === 'disabled') node[k] = !!v;
      else node.setAttribute(k, v === true ? '' : v);
    });
    for (var i = 2; i < arguments.length; i++) append(node, arguments[i]);
    return node;
  }
  function append(node, child) {
    if (child === null || child === undefined || child === false) return;
    if (Array.isArray(child)) child.forEach(function (c) { append(node, c); });
    else node.appendChild(child.nodeType ? child : document.createTextNode(String(child)));
  }

  function request(path, opts) {
    opts = opts || {};
    var headers = { Accept: 'application/json' };
    if (store.token) headers.Authorization = 'Bearer ' + store.token;
    var body;
    if (opts.body !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(opts.body); }
    return fetch(API + path, { method: opts.method || 'GET', headers: headers, body: body }).then(function (res) {
      if (res.status === 401) { signOut(); throw new Error('Session expired. Please sign in again.'); }
      if (opts.blob) {
        if (!res.ok) throw new Error('File not available');
        return res.blob();
      }
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) {
          var msg = data.message || 'Request failed (' + res.status + ')';
          if (data.errors) msg = Object.keys(data.errors).map(function (k) { return data.errors[k][0]; }).join(' ');
          throw new Error(msg);
        }
        return data;
      });
    });
  }

  function signOut() {
    store.token = null; store.user = null;
    route();
  }

  function pill(text) { return h('span', { class: 'pill ' + text }, text); }
  function fmtDate(s) { return s ? new Date(s).toLocaleString() : ''; }
  function showError(box, err) { box.textContent = err.message || String(err); }

  /* ---------- layout ---------- */
  var PAGES = [
    { id: 'dashboard', label: 'Dashboard' },
    { id: 'verifications', label: 'Verifications', badge: 'verifications' },
    { id: 'users', label: 'Users' },
    { id: 'rides', label: 'Rides' },
    { id: 'sos', label: 'SOS alerts', badge: 'sos' },
    { id: 'rates', label: 'Rate cards' }
  ];

  function shell(active, content) {
    var nav = h('nav', { class: 'nav' }, PAGES.map(function (p) {
      var count = p.badge ? badges[p.badge] : 0;
      return h('a', { href: '#/' + p.id, class: p.id === active ? 'active' : '' },
        p.label, count > 0 ? h('span', { class: 'badge' }, count) : null);
    }));
    return h('div', { class: 'layout' },
      h('aside', { class: 'sidebar' },
        h('div', { class: 'brand' }, 'SisterRide'), nav, h('div', { class: 'spacer' }),
        h('div', { class: 'muted', style: 'padding:0 10px' }, store.user ? store.user.name : ''),
        h('button', { class: 'ghost', onclick: function () {
          request('/logout', { method: 'POST' }).catch(function () {}).then(signOut);
        } }, 'Sign out')),
      h('main', { class: 'main' }, content));
  }

  function refreshBadges() {
    return request('/admin/stats').then(function (s) {
      badges.verifications = s.pending_verifications; badges.sos = s.open_sos; return s;
    });
  }

  /* ---------- login ---------- */
  function loginPage() {
    var err = h('div', { class: 'error' });
    var login = h('input', { placeholder: 'Phone or email', required: true, autocomplete: 'username' });
    var pass = h('input', { type: 'password', placeholder: 'Password', required: true, autocomplete: 'current-password' });
    var btn = h('button', { type: 'submit' }, 'Sign in');
    var form = h('form', { onsubmit: function (e) {
      e.preventDefault(); err.textContent = ''; btn.disabled = true;
      request('/login', { method: 'POST', body: { login: login.value.trim(), password: pass.value } })
        .then(function (data) {
          if (!data.user || data.user.role !== 'admin') throw new Error('This account is not an admin.');
          store.token = data.token; store.user = data.user;
          location.hash = '#/dashboard'; route();
        })
        .catch(function (e2) { showError(err, e2); })
        .then(function () { btn.disabled = false; });
    } }, login, pass, err, btn);
    return h('div', { class: 'login card' }, h('h1', null, 'SisterRide Admin'), form);
  }

  /* ---------- dashboard ---------- */
  function dashboardPage(main) {
    main.appendChild(h('h1', null, 'Dashboard'));
    var grid = h('div', { class: 'cards' }, h('div', { class: 'muted' }, 'Loading...'));
    main.appendChild(grid);
    refreshBadges().then(function (s) {
      var items = [
        ['Passengers', s.passengers], ['Providers', s.providers],
        ['Pending verifications', s.pending_verifications, s.pending_verifications > 0],
        ['Open SOS alerts', s.open_sos, s.open_sos > 0], ['Match groups', s.groups_total],
        ['Trips completed', s.trips_completed], ['Suspended users', s.suspended_users]
      ];
      grid.textContent = '';
      items.forEach(function (it) {
        grid.appendChild(h('div', { class: 'card stat' + (it[2] ? ' alert' : '') },
          h('div', { class: 'num' }, it[1]), h('div', { class: 'label' }, it[0])));
      });
    }).catch(function (e) { grid.textContent = e.message; });
  }

  /* ---------- verifications ---------- */
  function openLightbox(src) {
    var box = h('div', { class: 'lightbox', onclick: function () { box.remove(); } }, h('img', { src: src, alt: '' }));
    document.body.appendChild(box);
  }

  function docFigure(verification, type, label) {
    var img = h('img', { alt: label });
    var fig = h('figure', null, img, h('figcaption', null, label));
    request('/admin/verifications/' + verification.id + '/document/' + type, { blob: true })
      .then(function (blob) {
        var url = URL.createObjectURL(blob);
        img.src = url; img.addEventListener('click', function () { openLightbox(url); });
      })
      .catch(function () { fig.style.display = 'none'; });
    return fig;
  }

  function reviewCard(v, reload) {
    var u = v.user || {};
    var err = h('div', { class: 'error' });
    var reason = h('textarea', { placeholder: 'Reason for rejection (shown to the user)', style: 'display:none' });
    var approve = h('button', { class: 'good', onclick: function () { act('approve'); } }, 'Approve');
    var reject = h('button', { class: 'bad', onclick: function () {
      if (reason.style.display === 'none') { reason.style.display = 'block'; reason.focus(); return; }
      act('reject', { reason: reason.value.trim() });
    } }, 'Reject');

    function act(kind, body) {
      err.textContent = ''; approve.disabled = reject.disabled = true;
      request('/admin/verifications/' + v.id + '/' + kind, { method: 'POST', body: body || {} })
        .then(reload)
        .catch(function (e) { showError(err, e); approve.disabled = reject.disabled = false; });
    }

    return h('div', { class: 'card review' },
      h('div', { class: 'head' },
        h('div', null, h('strong', null, u.name || 'Unknown'),
          h('div', { class: 'muted' }, [u.phone, u.role + (u.provider_type ? ' / ' + u.provider_type : ''), 'CNIC ' + v.cnic_number].join(' | ')),
          h('div', { class: 'muted' }, 'Submitted ' + fmtDate(v.created_at))),
        pill(v.status)),
      h('div', { class: 'docs' }, docFigure(v, 'cnic', 'CNIC'), docFigure(v, 'selfie', 'Selfie'), docFigure(v, 'student', 'Student ID')),
      v.rejection_reason ? h('div', { class: 'muted' }, 'Reason: ' + v.rejection_reason) : null,
      v.status === 'pending' ? h('div', null, reason, h('div', { class: 'actions' }, approve, reject, err)) : null);
  }

  function verificationsPage(main) {
    var status = 'pending';
    var list = h('div');
    var tabs = h('div', { class: 'toolbar tabs' });
    main.appendChild(h('h1', null, 'Identity verifications'));
    main.appendChild(tabs); main.appendChild(list);

    function drawTabs() {
      tabs.textContent = '';
      ['pending', 'approved', 'rejected'].forEach(function (s) {
        tabs.appendChild(h('button', { class: s === status ? 'on' : '', onclick: function () { status = s; drawTabs(); load(); } }, s));
      });
    }
    function load() {
      list.textContent = 'Loading...';
      Promise.all([request('/admin/verifications?status=' + status), refreshBadges()]).then(function (r) {
        var rows = r[0].data || [];
        list.textContent = '';
        if (!rows.length) list.appendChild(h('div', { class: 'empty' }, 'No ' + status + ' verifications.'));
        rows.forEach(function (v) { list.appendChild(reviewCard(v, load)); });
        if (r[0].last_page > 1) list.appendChild(h('div', { class: 'muted' }, 'Showing first page of ' + r[0].total + '.'));
      }).catch(function (e) { list.textContent = e.message; });
    }
    drawTabs(); load();
  }

  /* ---------- users ---------- */
  function usersPage(main) {
    var q = h('input', { placeholder: 'Search name or phone' });
    var role = h('select', null,
      h('option', { value: '' }, 'All roles'), h('option', { value: 'passenger' }, 'Passengers'),
      h('option', { value: 'provider' }, 'Providers'), h('option', { value: 'admin' }, 'Admins'));
    var tbody = h('tbody'); var err = h('div', { class: 'error' });
    main.appendChild(h('h1', null, 'Users'));
    main.appendChild(h('div', { class: 'toolbar' }, q, role, h('button', { onclick: load }, 'Search')));
    main.appendChild(err);
    main.appendChild(h('table', null, h('thead', null, h('tr', null,
      ['Name', 'Phone', 'Role', 'Verification', 'Trust', 'Status', ''].map(function (c) { return h('th', null, c); }))), tbody));
    q.addEventListener('keydown', function (e) { if (e.key === 'Enter') load(); });

    function load() {
      err.textContent = '';
      var qs = '?role=' + encodeURIComponent(role.value) + '&q=' + encodeURIComponent(q.value.trim());
      request('/admin/users' + qs).then(function (res) {
        tbody.textContent = '';
        if (!res.data.length) tbody.appendChild(h('tr', null, h('td', { colspan: 7, class: 'muted' }, 'No users found.')));
        res.data.forEach(function (u) {
          var btn = u.role === 'admin' ? null : h('button', { class: u.is_suspended ? 'good' : 'bad', onclick: function () {
            btn.disabled = true;
            request('/admin/users/' + u.id + '/suspend', { method: 'POST', body: { suspended: !u.is_suspended } })
              .then(load).catch(function (e) { showError(err, e); btn.disabled = false; });
          } }, u.is_suspended ? 'Reinstate' : 'Suspend');
          tbody.appendChild(h('tr', null,
            h('td', null, u.name), h('td', null, u.phone || ''),
            h('td', null, u.role + (u.provider_type ? ' / ' + u.provider_type : '')),
            h('td', null, pill(u.verification_status)), h('td', null, u.trust_score),
            h('td', null, u.is_suspended ? pill('suspended') : 'active'), h('td', null, btn)));
        });
      }).catch(function (e) { showError(err, e); });
    }
    load();
  }

  /* ---------- rides / matching ---------- */
  function ridesPage(main) {
    var filter = h('select', { onchange: load },
      h('option', { value: '' }, 'All groups'), h('option', { value: 'matched' }, 'Waiting for driver'),
      h('option', { value: 'driver_accepted' }, 'Driver accepted'), h('option', { value: 'in_progress' }, 'In progress'),
      h('option', { value: 'completed' }, 'Completed'), h('option', { value: 'cancelled' }, 'Cancelled'));
    var summary = h('div', { class: 'muted' });
    var result = h('span', { class: 'muted' });
    var tbody = h('tbody');
    main.appendChild(h('h1', null, 'Rides and matching'));
    main.appendChild(h('div', { class: 'toolbar' }, filter,
      h('button', { class: 'ghost', onclick: load }, 'Refresh'),
      h('button', { onclick: function () {
        result.textContent = 'Matching...';
        request('/admin/matching/run', { method: 'POST', body: {} }).then(function (s) {
          result.textContent = s.groups_created + ' group(s) created, ' + s.requests_matched + ' request(s) matched, ' + s.expired + ' expired.';
          load();
        }).catch(function (e) { result.textContent = e.message; });
      } }, 'Run matching now'), result));
    main.appendChild(summary);
    main.appendChild(h('table', null, h('thead', null, h('tr', null,
      ['#', 'Mode', 'Departs', 'Women', 'Route', 'Fare', 'Driver', 'Status'].map(function (c) { return h('th', null, c); }))), tbody));

    function load() {
      request('/admin/groups?status=' + encodeURIComponent(filter.value)).then(function (res) {
        summary.textContent = res.waiting_requests + ' request(s) are waiting for a match.';
        var rows = res.groups.data || [];
        tbody.textContent = '';
        if (!rows.length) tbody.appendChild(h('tr', null, h('td', { colspan: 8, class: 'muted' }, 'No groups yet.')));
        rows.forEach(function (g) {
          var names = (g.members || []).filter(function (m) { return m.status !== 'cancelled'; })
            .map(function (m) { return m.passenger ? m.passenger.name.split(' ')[0] : '?'; }).join(', ');
          tbody.appendChild(h('tr', null,
            h('td', null, g.id), h('td', null, g.mode === 'bike' ? 'Bike' : 'Car / Rickshaw'),
            h('td', null, fmtDate(g.depart_at)), h('td', null, names),
            h('td', null, g.total_distance_km + ' km'), h('td', null, 'Rs ' + g.total_fare),
            h('td', null, g.driver ? g.driver.name : '-'), h('td', null, pill(g.status))));
        });
      }).catch(function (e) { summary.textContent = e.message; });
    }
    load();
  }

  /* ---------- SOS ---------- */
  function sosPage(main) {
    var filter = h('select', { onchange: load },
      h('option', { value: '' }, 'All'), h('option', { value: 'new' }, 'New'),
      h('option', { value: 'acknowledged' }, 'Acknowledged'), h('option', { value: 'resolved' }, 'Resolved'));
    var list = h('div');
    main.appendChild(h('h1', null, 'SOS alerts'));
    main.appendChild(h('div', { class: 'toolbar' }, filter, h('button', { class: 'ghost', onclick: load }, 'Refresh')));
    main.appendChild(list);

    function card(s) {
      var u = s.user || {};
      var note = h('textarea', { placeholder: 'Note (optional)' }, '');
      note.value = s.admin_note || '';
      var err = h('div', { class: 'error' });
      function update(status) {
        request('/admin/sos/' + s.id, { method: 'PATCH', body: { status: status, admin_note: note.value.trim() || null } })
          .then(load).catch(function (e) { showError(err, e); });
      }
      var map = 'https://www.google.com/maps?q=' + encodeURIComponent(s.lat + ',' + s.lng);
      return h('div', { class: 'card review' },
        h('div', { class: 'head' },
          h('div', null, h('strong', null, u.name || 'Unknown'), h('div', { class: 'muted' }, (u.phone || '') + ' | ' + fmtDate(s.created_at)),
            h('div', null, h('a', { href: map, target: '_blank', rel: 'noopener noreferrer' }, 'Open location in Maps (' + s.lat + ', ' + s.lng + ')'))),
          pill(s.status)),
        s.status !== 'resolved' ? h('div', null, note, h('div', { class: 'actions' },
          s.status === 'new' ? h('button', { onclick: function () { update('acknowledged'); } }, 'Acknowledge') : null,
          h('button', { class: 'good', onclick: function () { update('resolved'); } }, 'Mark resolved'), err)) :
          (s.admin_note ? h('div', { class: 'muted' }, 'Note: ' + s.admin_note) : null));
    }
    function load() {
      request('/admin/sos?status=' + encodeURIComponent(filter.value)).then(function (res) {
        list.textContent = '';
        if (!res.data.length) list.appendChild(h('div', { class: 'empty' }, 'No SOS alerts.'));
        res.data.forEach(function (s) { list.appendChild(card(s)); });
        return refreshBadges();
      }).catch(function (e) { list.textContent = e.message; });
    }
    load();
  }

  /* ---------- rate cards ---------- */
  function ratesPage(main) {
    var wrap = h('div', { class: 'rate-grid' });
    main.appendChild(h('h1', null, 'Rate cards'));
    main.appendChild(h('p', { class: 'muted' }, 'Fares are in rupees. Commission is taken from the total fare, never added per passenger.'));
    main.appendChild(wrap);

    function field(label, value, step) {
      var input = h('input', { type: 'number', step: step || '0.01', min: '0', value: value });
      return { input: input, node: h('label', null, label, input) };
    }
    request('/admin/rate-cards').then(function (cards) {
      cards.forEach(function (c) {
        var base = field('Base fare (Rs)', c.base_fare), km = field('Per km (Rs)', c.per_km), com = field('Commission %', c.commission_pct);
        var active = h('input', { type: 'checkbox', checked: c.active });
        var msg = h('div', { class: 'muted' });
        wrap.appendChild(h('div', { class: 'card' },
          h('h2', null, c.mode === 'bike' ? 'Bike / Scooty' : 'Car / Rickshaw'),
          base.node, km.node, com.node,
          h('label', { style: 'display:flex;gap:8px;align-items:center' }, active, 'Active'),
          h('button', { onclick: function () {
            msg.textContent = 'Saving...';
            request('/admin/rate-cards/' + c.id, { method: 'PUT', body: {
              base_fare: Number(base.input.value), per_km: Number(km.input.value),
              commission_pct: Number(com.input.value), active: active.checked } })
              .then(function () { msg.textContent = 'Saved.'; })
              .catch(function (e) { msg.textContent = e.message; });
          } }, 'Save'), msg));
      });
    }).catch(function (e) { wrap.textContent = e.message; });
  }

  /* ---------- router ---------- */
  var VIEWS = { dashboard: dashboardPage, verifications: verificationsPage, users: usersPage, rides: ridesPage, sos: sosPage, rates: ratesPage };

  function route() {
    var app = document.getElementById('app');
    app.textContent = '';
    if (!store.token) { app.appendChild(loginPage()); return; }
    var id = (location.hash.replace(/^#\//, '') || 'dashboard');
    if (!VIEWS[id]) id = 'dashboard';
    var main = h('div');
    app.appendChild(shell(id, main));
    VIEWS[id](main);
    if (id !== 'dashboard') {
      refreshBadges().then(function () {
        // update sidebar badges without redrawing the page
        document.querySelectorAll('.nav a').forEach(function (a, i) {
          var p = PAGES[i]; var old = a.querySelector('.badge'); if (old) old.remove();
          if (p.badge && badges[p.badge] > 0) a.appendChild(h('span', { class: 'badge' }, badges[p.badge]));
        });
      }).catch(function () {});
    }
  }

  window.addEventListener('hashchange', route);
  route();
})();
