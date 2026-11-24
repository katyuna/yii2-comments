/*
 * Admin comment list: shows comments posted by guests without a page reload.
 *
 * The page gives a Centrifugo JWT in data attributes of the comment table. The browser
 * connects to Centrifugo over WebSocket (proxied by nginx at /connection/websocket),
 * subscribes to the admin channel and adds a table row for every new comment.
 */
(function () {
  'use strict';

  const table = document.querySelector('[data-testid="admin-comment-list"]');
  const status = document.querySelector('[data-testid="realtime-status"]');

  if (!table || !table.dataset.wsToken || typeof Centrifuge === 'undefined') {
    return;
  }

  const config = table.dataset;
  const tbody = table.tBodies[0];

  const STATES = {
    connecting: ['Connecting…', 'text-bg-secondary'],
    live: ['Live', 'text-bg-success'],
    offline: ['Offline', 'text-bg-danger'],
  };

  function setStatus(state) {
    if (!status) {
      return;
    }
    const [label, color] = STATES[state];
    status.textContent = label;
    status.dataset.state = state;
    status.className = status.className.replace(/text-bg-\S+/, color);
  }

  // textContent never parses HTML, so comment text cannot inject markup or scripts
  function cell(text, className, testId) {
    const td = document.createElement('td');
    if (className) {
      td.className = className;
    }
    if (testId) {
      td.dataset.testid = testId;
    }
    td.textContent = text;
    return td;
  }

  // Same markup as the server-rendered row: Edit link + POST delete form with the CSRF token
  function actionsCell(id) {
    const td = document.createElement('td');
    td.className = 'text-end text-nowrap';

    const edit = document.createElement('a');
    edit.href = config.updateUrl.replace('__ID__', id);
    edit.className = 'btn btn-sm btn-outline-primary';
    edit.dataset.testid = 'admin-comment-edit';
    edit.textContent = 'Edit';

    const form = document.createElement('form');
    form.method = 'post';
    form.action = config.deleteUrl.replace('__ID__', id);
    form.className = 'd-inline';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = yii.getCsrfParam();
    csrf.value = yii.getCsrfToken();

    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'btn btn-sm btn-outline-danger';
    button.dataset.confirm = 'Delete this comment?';
    button.dataset.testid = 'admin-comment-delete';
    button.textContent = 'Delete';

    form.append(csrf, button);
    td.append(edit, ' ', form);
    return td;
  }

  function increaseCount() {
    const count = document.querySelector('[data-testid="admin-comment-count"]');
    if (count) {
      count.textContent = String(Number(count.textContent) + 1);
    }
  }

  function addComment(comment) {
    const id = String(comment.id);

    // After a reconnect Centrifugo may resend a comment that is already in the table
    if (tbody.querySelector('[data-comment-id="' + CSS.escape(id) + '"]')) {
      return;
    }

    increaseCount();

    if (config.liveInsert !== '1') {
      return;
    }

    const empty = tbody.querySelector('[data-testid="admin-comment-list-empty"]');
    if (empty) {
      empty.remove();
    }

    const row = document.createElement('tr');
    row.dataset.testid = 'admin-comment-row';
    row.dataset.commentId = id;
    row.className = 'table-success';
    row.append(
      cell(id, '', 'admin-comment-id'),
      cell(comment.author, '', 'admin-comment-author'),
      cell(comment.text, 'comment-text', 'admin-comment-text'),
      cell(comment.createdAt, 'text-nowrap', 'admin-comment-date'),
      cell(comment.ip, 'text-nowrap small text-body-secondary', ''),
      actionsCell(id)
    );
    tbody.prepend(row);
  }

  // Called by centrifuge-js when the token expires; X-Requested-With makes Yii answer 403
  // instead of a redirect to the login page when the admin session is over
  function refreshToken() {
    return fetch(config.tokenUrl, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (response) {
        if (response.status === 401 || response.status === 403) {
          throw new Centrifuge.UnauthorizedError('admin session is over');
        }
        if (!response.ok) {
          throw new Error('token request failed: ' + response.status);
        }
        return response.json();
      })
      .then(function (data) {
        return data.token;
      });
  }

  const wsUrl = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + '/connection/websocket';
  const centrifuge = new Centrifuge(wsUrl, {
    token: config.wsToken,
    getToken: refreshToken,
  });

  const subscription = centrifuge.newSubscription(config.wsChannel);
  subscription.on('subscribing', function () {
    setStatus('connecting');
  });
  subscription.on('subscribed', function () {
    setStatus('live');
  });
  subscription.on('unsubscribed', function () {
    setStatus('offline');
  });
  subscription.on('publication', function (ctx) {
    addComment(ctx.data);
  });

  centrifuge.on('connecting', function () {
    setStatus('connecting');
  });
  centrifuge.on('disconnected', function () {
    setStatus('offline');
  });

  subscription.subscribe();
  centrifuge.connect();
})();
