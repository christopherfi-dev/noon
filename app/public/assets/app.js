/*
 * Algebra Engine beta UI. Vanilla JS; all text from the server, the model or
 * the learner is inserted with textContent only, never parsed as HTML.
 */
'use strict';

(() => {
  const ITEM_ACTIONS = ['SUBMIT_RESPONSE', 'REQUEST_HINT', 'REQUEST_EXPLANATION', 'CLARIFY_RESPONSE', 'SKIP_ITEM', 'NOT_LEARNED'];
  const MODE_HEADINGS = {
    AWAITING_SESSION_CONTEXT: 'Getting ready',
    TARGET_SELECTION: 'Choose a skill',
    TARGET_ASSESSMENT: 'Check your skill',
    DIAGNOSTIC_PROBE: 'Another question',
    PREREQUISITE_CHECK: 'A building-block question',
    GUIDED_REPAIR: 'Step by step',
    INDEPENDENT_CHECK: 'On your own',
    RETURN_TO_TARGET: 'Back to your topic',
    SESSION_REPORT: 'Summary ready',
    SESSION_CLOSED: 'Session ended',
  };
  const AVAILABILITY = {
    AVAILABLE_SCORED: 'Available in this beta',
    PARTIAL_COMPONENTS: 'Partial: numeric part only',
    UNAVAILABLE: 'Not available in this beta',
  };

  const state = {
    csrf: null,
    boot: null,
    view: null,
    screen: 'welcome',      // welcome | pick | browse | workspace | summary
    report: null,
    search: '',
    fullMap: false,         // skill map: the beta skills by default; the full 205-skill map on request
    picker: false,
    busy: false,
    error: null,
    draft: { key: null, text: '', working: '', options: [] },
    lastItemKey: null,
  };

  let root;
  let statusEl;

  // ------------------------------------------------------------- utilities

  function el(tag, props, ...children) {
    const node = document.createElement(tag);
    if (props) {
      for (const [k, v] of Object.entries(props)) {
        if (v === null || v === undefined || v === false) continue;
        if (k === 'text') node.textContent = String(v);
        else if (k === 'testid') node.setAttribute('data-testid', v);
        else if (k === 'className') node.className = v;
        else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
        else if (k === 'value') node.value = v;
        else if (k === 'checked') node.checked = !!v;
        else if (k === 'disabled') node.disabled = !!v;
        else if (k === 'hidden') node.hidden = !!v;
        else node.setAttribute(k, v === true ? '' : String(v));
      }
    }
    for (const c of children.flat()) {
      if (c === null || c === undefined || c === false) continue;
      node.append(c instanceof Node ? c : document.createTextNode(String(c)));
    }
    return node;
  }

  function button(label, testid, onclick, extra) {
    return el('button', Object.assign({ type: 'button', testid, onclick, disabled: state.busy }, extra || {}), label);
  }

  function newKey() {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  }

  function announce(text) {
    if (!statusEl) return;
    statusEl.textContent = '';
    window.setTimeout(() => { statusEl.textContent = text; }, 30);
  }

  function itemKey(view) {
    const it = view && view.active_item;
    return it ? it.item_id + '#' + it.item_version : null;
  }

  function liveSession() {
    const v = state.view;
    return v && v.mode !== 'SESSION_CLOSED' && v.status !== 'CLOSED' ? v : null;
  }

  function sleep(ms) { return new Promise((r) => window.setTimeout(r, ms)); }

  // ------------------------------------------------------------------- API

  async function request(method, action, body, extraQuery) {
    const headers = { Accept: 'application/json' };
    const init = { method, credentials: 'same-origin', headers, cache: 'no-store' };
    if (method === 'POST') {
      headers['Content-Type'] = 'application/json';
      if (state.csrf) headers['X-CSRF-Token'] = state.csrf;
      init.body = JSON.stringify(body);
    }
    const url = 'api.php?action=' + encodeURIComponent(action) + (extraQuery || '');
    let response;
    try {
      response = await fetch(url, init);
    } catch (e) {
      // One retry with the same body, so the same idempotency key is reused.
      await sleep(800);
      response = await fetch(url, init);
    }
    let data = null;
    try { data = await response.json(); } catch (e) { data = null; }
    return { status: response.status, data };
  }

  function adoptView(view) {
    if (!view) return;
    if (view.csrf_token) state.csrf = view.csrf_token;
    const before = state.lastItemKey;
    state.view = view;
    state.lastItemKey = itemKey(view);
    if (state.lastItemKey !== state.draft.key) {
      state.draft = { key: state.lastItemKey, text: '', working: '', options: [] };
    }
    return before !== state.lastItemKey;
  }

  async function refreshState() {
    const r = await request('GET', 'state');
    if (r.status === 200 && r.data) {
      if (r.data.session) adoptView(r.data.session);
      else state.view = null;
    }
    return r;
  }

  async function pollUntilSettled() {
    const until = Date.now() + 30000;
    while (Date.now() < until) {
      await sleep(1500);
      const r = await refreshState();
      if (r.status === 200 && (!state.view || state.view.status !== 'PENDING')) return true;
    }
    return false;
  }

  /** Run a mutation; keeps the learner's draft on any recoverable error. */
  async function mutate(action, body) {
    if (state.busy) return null;
    state.busy = true;
    state.error = null;
    announce('Sending…');
    render();
    let result = null;
    try {
      const r = await request('POST', action, body);
      result = r;
      if (r.status === 200 && r.data && typeof r.data.revision === 'number') {
        adoptView(r.data);
        announce(r.data.status === 'HOLD' ? 'Saved. The next step is not ready yet.' : 'Saved.');
      } else if (r.status === 202) {
        announce('Working…');
        const settled = await pollUntilSettled();
        announce(settled ? 'Saved.' : 'Still working. Please wait a moment and reload.');
      } else if (r.status === 200) {
        announce('Done.');
      } else {
        const message = (r.data && r.data.message) || 'Something went wrong. Your typed answer is kept.';
        state.error = message;
        if (r.status === 409 && r.data && r.data.reload) {
          const keep = state.draft;
          await refreshState();
          if (keep.key === state.lastItemKey) state.draft = keep;
        }
        announce(message);
      }
    } catch (e) {
      state.error = 'The connection failed. Your typed answer is kept; please try again.';
      announce(state.error);
    } finally {
      state.busy = false;
      render();
    }
    return result;
  }

  function sendEvent(action, payload) {
    const v = state.view;
    if (!v) return null;
    const withItem = ITEM_ACTIONS.includes(action) && v.active_item;
    return mutate('event', {
      idempotency_key: newKey(),
      expected_revision: v.revision,
      action,
      item_id: withItem ? v.active_item.item_id : null,
      item_version: withItem ? v.active_item.item_version : null,
      payload: payload || {},
    });
  }

  async function startSession(skillId) {
    const live = liveSession();
    const r = await mutate('start', {
      idempotency_key: newKey(),
      expected_revision: live ? live.revision : 0,
      entry_route: 'TOPIC',
      target_skill_id: skillId,
      task_token: null,
      replace_current: !!live,
    });
    if (r && r.status === 200) {
      state.screen = 'workspace';
      state.report = null;
      state.picker = false;
      render();
      focusAnswer();
    }
  }

  async function loadReport() {
    state.busy = true;
    announce('Loading your summary…');
    render();
    try {
      const r = await request('GET', 'report');
      if (r.status === 200 && r.data && r.data.report) {
        state.report = r.data;
        state.screen = 'summary';
        announce('Summary loaded.');
      } else {
        state.error = (r.data && r.data.message) || 'The summary is not available.';
        announce(state.error);
      }
    } finally {
      state.busy = false;
      render();
    }
    const h = document.getElementById('summary-heading');
    if (h) h.focus();
  }

  async function forget() {
    if (!window.confirm('Delete this session, your answers and your summary from this server?')) return;
    const r = await request('POST', 'forget', { idempotency_key: newKey() });
    if (r.status === 200) {
      window.location.reload();
    } else {
      announce((r.data && r.data.message) || 'Could not delete the session.');
    }
  }

  async function boot() {
    const r = await request('GET', 'bootstrap');
    if (r.status !== 200 || !r.data) {
      root.replaceChildren(el('p', { className: 'error-banner', role: 'alert', text: (r.data && r.data.message) || 'The tutor is not available right now.' }));
      root.setAttribute('aria-busy', 'false');
      return;
    }
    state.boot = r.data;
    state.csrf = r.data.csrf_token;
    if (r.data.session) adoptView(r.data.session);
    const badge = document.querySelector('[data-testid="mock-badge"]');
    if (badge) badge.hidden = r.data.provider_mode !== 'mock';
    state.screen = 'welcome';
    render();
  }

  // ---------------------------------------------------------------- render

  function render() {
    if (!root) return;
    root.setAttribute('aria-busy', state.busy ? 'true' : 'false');
    let screen;
    switch (state.screen) {
      case 'pick': screen = renderPick(); break;
      case 'browse': screen = renderBrowse(); break;
      case 'workspace': screen = state.view ? renderWorkspace() : renderWelcome(); break;
      case 'summary': screen = state.report ? renderSummary() : renderWorkspace(); break;
      default: screen = renderWelcome();
    }
    const active = document.activeElement;
    const activeId = active && active.getAttribute ? active.getAttribute('data-testid') : null;
    root.replaceChildren(screen);
    if (activeId) {
      const again = root.querySelector('[data-testid="' + activeId + '"]');
      if (again && again !== document.activeElement && !again.disabled) again.focus();
    }
  }

  function errorBanner() {
    return state.error ? el('p', { className: 'error-banner', role: 'alert', text: state.error }) : null;
  }

  function renderWelcome() {
    const boot = state.boot;
    const beta = boot.beta;
    const def = beta.skills.find((s) => s.skill_id === beta.default_target) || beta.skills[0];
    const live = liveSession();
    return el('section', { className: 'screen welcome', 'aria-labelledby': 'welcome-heading' },
      el('h1', { id: 'welcome-heading', text: 'Algebra practice that finds where things break' }),
      el('p', { className: 'lead', text: 'Work on one algebra skill. If something goes wrong, the tutor checks the skills it builds on, works through them with you one question at a time, and then brings you back.' }),
      errorBanner(),
      live ? el('div', { className: 'card resume' },
        el('h2', { text: 'You have a session in progress' }),
        el('p', { text: live.target ? 'Topic: ' + live.target.title : 'No topic chosen yet.' }),
        el('div', { className: 'row' },
          button('Continue your session', 'continue-session', () => { state.screen = 'workspace'; render(); focusAnswer(); }, { className: 'primary' }),
          button('Start over', 'start-over', () => {
            if (window.confirm('Start over? Your current session will be replaced.')) startSession(def.skill_id);
          })),
      ) : null,
      el('div', { className: 'card' },
        el('h2', { text: 'Start' }),
        button('Start: ' + def.title, 'start-default', () => startSession(def.skill_id), { className: 'primary wide' }),
        el('div', { className: 'row' },
          button('Choose another skill', 'choose-skill', () => { state.screen = 'pick'; render(); }),
          button('Browse skills', 'browse-skills', () => { state.screen = 'browse'; render(); })),
      ),
      el('div', { className: 'notices' },
        el('h2', { text: 'Before you start' }),
        el('p', { text: boot.notices.data }),
        el('p', { text: boot.notices.provider }),
        el('p', { text: 'Results describe what you showed in this session only. They are not a grade and not a long-term judgement.' })),
    );
  }

  function skillButtonList(onPick, testPrefix, exclude) {
    return el('ul', { className: 'skill-list' },
      state.boot.beta.skills.filter((s) => s.skill_id !== exclude).map((s) => el('li', null,
        el('button', { type: 'button', className: 'skill-button', testid: testPrefix + s.skill_id, disabled: state.busy, onclick: () => onPick(s.skill_id) },
          el('span', { className: 'skill-id', text: s.skill_id }), ' ', el('span', { text: s.title })))));
  }

  function renderPick() {
    return el('section', { className: 'screen', 'aria-labelledby': 'pick-heading' },
      el('h1', { id: 'pick-heading', text: 'Choose a skill' }),
      el('p', { text: 'These ' + state.boot.beta.skills.length + ' skills are available in this beta.' }),
      errorBanner(),
      skillButtonList((id) => startSession(id), 'start-skill-', null),
      button('Back', 'back', () => { state.screen = 'welcome'; render(); }));
  }

  function renderBrowse() {
    const q = state.search.trim().toLowerCase();
    const groups = new Map();
    const betaIds = new Set(state.boot.beta.skills.map((s) => s.skill_id));
    const shown = state.fullMap ? state.boot.catalog : state.boot.catalog.filter((s) => betaIds.has(s.skill_id));
    for (const s of shown) {
      if (q && !(s.skill_id.toLowerCase().includes(q) || s.title.toLowerCase().includes(q))) continue;
      if (!groups.has(s.domain)) groups.set(s.domain, []);
      groups.get(s.domain).push(s);
    }
    const searchId = 'skill-search';
    const list = [];
    for (const [domain, skills] of groups) {
      list.push(el('section', { className: 'domain' },
        el('h2', { text: domain + ' (' + skills.length + ')' }),
        el('ul', { className: 'catalog' }, skills.map((s) => el('li', { className: 'catalog-row' },
          el('div', { className: 'catalog-main' },
            el('span', { className: 'skill-id', text: s.skill_id }), ' ',
            el('span', { className: 'catalog-title', text: s.title })),
          el('div', { className: 'catalog-meta' },
            el('span', { className: 'tag', text: s.curricular_role.toLowerCase() }), ' ',
            el('span', { className: 'tag avail-' + s.runtime_status.toLowerCase(), text: AVAILABILITY[s.runtime_status] || s.runtime_status }),
            s.runtime_status !== 'UNAVAILABLE'
              ? el('button', { type: 'button', className: 'small', testid: 'start-skill-' + s.skill_id, disabled: state.busy, onclick: () => startSession(s.skill_id) }, 'Start')
              : null)))),
      ));
    }
    return el('section', { className: 'screen', 'aria-labelledby': 'browse-heading' },
      el('h1', { id: 'browse-heading', text: state.fullMap ? 'All ' + state.boot.catalog.length + ' curriculum skills' : 'The ' + betaIds.size + ' beta skills' }),
      el('p', { text: state.fullMap
        ? 'The full map is shown for reference. Only skills marked as available can be practised in this beta; the rest are not yet supported and are never treated as gaps in your knowledge.'
        : 'These are the skills you can practise in this beta. The full curriculum map has ' + state.boot.catalog.length + ' skills.' }),
      errorBanner(),
      button(state.fullMap ? 'Show beta skills only' : 'Show full map', 'toggle-full-map', () => { state.fullMap = !state.fullMap; render(); }, { 'aria-pressed': state.fullMap ? 'true' : 'false' }),
      el('label', { for: searchId, text: 'Search by title or ID' }),
      el('input', {
        id: searchId, type: 'search', testid: 'skill-search', value: state.search, autocomplete: 'off',
        oninput: (e) => {
          state.search = e.target.value;
          const pos = e.target.selectionStart;
          render();
          const box = document.getElementById(searchId);
          if (box) { box.focus(); try { box.setSelectionRange(pos, pos); } catch (err) { /* ignore */ } }
        },
      }),
      el('p', { className: 'count', 'aria-live': 'polite', text: Array.from(groups.values()).reduce((n, g) => n + g.length, 0) + ' skills shown' }),
      list,
      button('Back', 'back', () => { state.screen = 'welcome'; render(); }));
  }

  function optionLetters(prompt) {
    const letters = [];
    const re = /^([A-D])\) /gm;
    let m;
    while ((m = re.exec(prompt)) !== null) {
      if (!letters.includes(m[1])) letters.push(m[1]);
    }
    return letters.length ? letters : ['A', 'B', 'C', 'D'];
  }

  function allowed(action) {
    return !!state.view && state.view.allowed_actions.includes(action);
  }

  function submitAnswer(e) {
    if (e) e.preventDefault();
    const v = state.view;
    if (!v || !v.active_item || state.busy) return;
    let text;
    if (v.active_item.response_type === 'classification') {
      if (!state.draft.options.length) { announce('Choose at least one option.'); return; }
      text = state.draft.options.slice().sort().join(',');
    } else {
      text = state.draft.text.trim();
      if (!text) { announce('Enter an answer first.'); return; }
    }
    const payload = { text };
    if (state.draft.working.trim()) payload.working = state.draft.working;
    sendEvent(allowed('CLARIFY_RESPONSE') ? 'CLARIFY_RESPONSE' : 'SUBMIT_RESPONSE', payload).then((r) => {
      if (r && r.status === 200) focusAnswer();
    });
  }

  function focusAnswer() {
    window.setTimeout(() => {
      const input = root.querySelector('[data-testid="answer-input"], [data-testid^="option-"]');
      if (input && !input.disabled) input.focus();
    }, 0);
  }

  function renderAnswerForm(v) {
    const it = v.active_item;
    const disabled = state.busy || v.status === 'PENDING';
    const fields = [];
    if (it.response_type === 'classification') {
      const letters = optionLetters(it.prompt);
      fields.push(el('fieldset', { className: 'options' },
        el('legend', { text: it.input_help || 'Choose every option that applies.' }),
        letters.map((L) => {
          const id = 'opt-' + L;
          return el('div', { className: 'option' },
            el('input', {
              type: 'checkbox', id, testid: 'option-' + L, checked: state.draft.options.includes(L), disabled,
              onchange: (e) => {
                const set = new Set(state.draft.options);
                if (e.target.checked) set.add(L); else set.delete(L);
                state.draft.options = Array.from(set);
              },
            }),
            el('label', { for: id, text: 'Option ' + L }));
        })));
    } else {
      fields.push(
        el('label', { for: 'answer', className: 'field-label', text: it.input_help || 'Your answer' }),
        el('input', {
          id: 'answer', type: 'text', testid: 'answer-input', value: state.draft.text, maxlength: 2000, disabled,
          autocomplete: 'off', autocapitalize: 'off', spellcheck: 'false', inputmode: 'text',
          oninput: (e) => { state.draft.text = e.target.value; },
        }));
    }
    fields.push(
      el('label', { for: 'working', className: 'field-label', text: 'Working (optional)' }),
      el('textarea', {
        id: 'working', rows: 3, testid: 'working-input', maxlength: 4000, disabled,
        oninput: (e) => { state.draft.working = e.target.value; },
      }));
    const form = el('form', { className: 'answer-form', onsubmit: submitAnswer, novalidate: true }, fields,
      el('div', { className: 'row controls' },
        (allowed('SUBMIT_RESPONSE') || allowed('CLARIFY_RESPONSE'))
          ? el('button', { type: 'submit', className: 'primary', testid: 'submit', disabled }, allowed('CLARIFY_RESPONSE') ? 'Submit again' : 'Submit')
          : null,
        allowed('REQUEST_HINT') ? button('Hint', 'hint', () => sendEvent('REQUEST_HINT'), { disabled }) : null,
        allowed('REQUEST_EXPLANATION') ? button('Explain this', 'explain', () => sendEvent('REQUEST_EXPLANATION'), { disabled }) : null,
        allowed('NOT_LEARNED') ? button(v.mode === 'GUIDED_REPAIR' ? "I don't know" : "I haven't learned this", 'not-learned', () => sendEvent('NOT_LEARNED'), { disabled }) : null,
        allowed('SKIP_ITEM') ? button('Skip', 'skip', () => sendEvent('SKIP_ITEM'), { disabled }) : null));
    // The textarea value is set after creation so long text is preserved exactly.
    const ta = form.querySelector('textarea');
    if (ta) ta.value = state.draft.working;
    return form;
  }

  function renderWorkspace() {
    const v = state.view;
    const reportReady = v.mode === 'SESSION_REPORT' || v.status === 'CLOSED';
    const pending = v.status === 'PENDING';
    const heading = MODE_HEADINGS[v.mode] || 'Practice';
    const it = v.active_item;
    return el('section', { className: 'screen workspace', testid: 'workspace', 'aria-labelledby': 'ws-heading' },
      el('p', { className: 'topic', testid: 'target-title', text: v.target ? 'Topic: ' + v.target.title : 'No topic chosen yet' }),
      el('h1', { id: 'ws-heading', text: heading }),
      el('p', { className: 'progress', testid: 'progress', 'aria-live': 'polite', text: v.progress.label }),
      errorBanner(),
      el('ul', { className: 'messages', testid: 'messages', role: 'log', 'aria-live': 'polite', 'aria-label': 'Tutor messages' },
        v.messages.map((m) => el('li', { className: 'msg msg-' + m.kind.toLowerCase(), text: m.text }))),
      pending ? el('p', { className: 'pending', role: 'status', text: 'Working…' }) : null,
      it && !reportReady ? el('div', { className: 'question card' },
        el('h2', { className: 'visually-hidden', text: 'Question' }),
        it.support_status === 'ASSISTED' ? el('p', { className: 'assisted', text: 'Practice with help (not counted as independent)' }) : null,
        el('p', { className: 'prompt', testid: 'prompt', text: it.prompt }),
        renderAnswerForm(v)) : null,
      v.menu && v.menu.options && v.menu.options.length && !reportReady ? el('div', { className: 'menu card' },
        el('h2', { text: v.menu.menu_id === 'continuation' ? 'Keep going?' : 'Choose an option' }),
        el('div', { className: 'menu-options' }, v.menu.options.map((o) => button(o.label, 'menu-option-' + o.option_id, () => {
          if (o.action === 'SELECT_TARGET') sendEvent('SELECT_TARGET', { target_skill_id: o.option_id });
          else if (o.action === 'CONTINUE_SESSION') sendEvent('CONTINUE_SESSION', { continue_minutes: 10 });
          else sendEvent(o.action, {});
        }, { disabled: state.busy || pending })))) : null,
      allowed('RESUME_SESSION') ? el('div', { className: 'row' }, button('Try again', 'retry', () => sendEvent('RESUME_SESSION'), { className: 'primary' })) : null,
      reportReady ? el('div', { className: 'card' },
        el('p', { text: v.status === 'CLOSED' ? 'This session has ended.' : 'Your work on this topic is complete for this session.' }),
        el('div', { className: 'row' },
          button('View your session summary', 'view-report', loadReport, { className: 'primary' }),
          v.status !== 'CLOSED' && allowed('END_SESSION') ? button('Close session', 'end-session', () => sendEvent('END_SESSION')) : null,
          button('Start a new session', 'new-session', () => { state.screen = 'welcome'; render(); }))) : null,
      !reportReady ? el('div', { className: 'row session-controls' },
        allowed('CHANGE_TARGET') ? button(state.picker ? 'Hide topics' : 'Change topic', 'change-topic', () => { state.picker = !state.picker; render(); }, { 'aria-expanded': state.picker ? 'true' : 'false' }) : null,
        allowed('END_SESSION') ? button('End session', 'end-session', () => {
          if (window.confirm('End this session and see your summary?')) sendEvent('END_SESSION');
        }) : null) : null,
      state.picker && allowed('CHANGE_TARGET') && !reportReady ? el('div', { className: 'card picker' },
        el('h2', { text: 'Switch to another skill' }),
        el('p', { text: 'Your current work is kept in the record as interrupted; the new topic starts fresh.' }),
        skillButtonList((id) => { state.picker = false; sendEvent('CHANGE_TARGET', { target_skill_id: id }); }, 'change-to-', null)) : null,
    );
  }

  function list(items, empty) {
    if (!items || !items.length) return el('p', { className: 'muted', text: empty });
    return el('ul', null, items.map((t) => el('li', { text: t })));
  }

  function statements(items, empty) {
    return list((items || []).map((x) => x.statement), empty);
  }

  function renderSummary() {
    const data = state.report;
    const r = data.report;
    const table = el('table', { className: 'skills-table' },
      el('caption', { text: 'Per skill, this session' }),
      el('thead', null, el('tr', null, ['Skill', 'Items attempted', 'Independent', 'With help', 'Criterion status', 'Coverage'].map((h) => el('th', { scope: 'col', text: h })))),
      el('tbody', null, data.per_skill.map((s) => el('tr', null,
        el('th', { scope: 'row', text: s.skill_id + ' ' + s.title }),
        el('td', { text: String(s.items_attempted) }),
        el('td', { text: String(s.independent) }),
        el('td', { text: String(s.assisted) }),
        el('td', { text: s.criterion_status.replace(/_/g, ' ').toLowerCase() }),
        el('td', { text: s.runtime_coverage }),
      ))));
    const details = data.per_skill.map((s) => el('section', { className: 'skill-detail' },
      el('h3', { text: s.skill_id + ': ' + s.title }),
      el('h4', { text: 'Error patterns observed (observations, not confirmed causes)' }),
      list(s.error_signatures_observed, 'None observed.'),
      s.descent_path.length ? el('div', null, el('h4', { text: 'Descent and return' }), list(s.descent_path, '')) : null,
      s.boundary_notes.length ? el('div', null, el('h4', { text: 'Beta boundary' }), list(s.boundary_notes, '')) : null));
    return el('section', { className: 'screen summary', testid: 'report', 'aria-labelledby': 'summary-heading' },
      el('h1', { id: 'summary-heading', tabindex: '-1', text: 'Session summary' }),
      el('p', { className: 'provider-label', testid: 'report-provider', text: data.provider_label }),
      el('p', { text: 'Status: ' + r.status.replace(/_/g, ' ').toLowerCase() + '. Topics: ' + (r.targets_attempted.join(', ') || 'none') + '.' }),
      el('div', { className: 'table-wrap' }, table),
      details,
      el('h2', { text: 'Criteria met in this session' }),
      statements(r.immediate_criteria, 'No criterion was met in this session.'),
      el('h2', { text: 'Transfer actually tested' }),
      statements(r.transfer_evidence, 'No transfer item was answered correctly without help.'),
      el('h2', { text: 'Guided work' }),
      statements(r.guided_work, 'No guided work in this session.'),
      el('h2', { text: 'Repairs and returns' }),
      statements(r.repairs_and_returns, 'No repair was needed.'),
      el('h2', { text: 'Route taken' }),
      list(data.descent_path, 'No descent to other skills.'),
      el('h2', { text: 'Unresolved or untested' }),
      list(r.unresolved_or_untested, 'Nothing listed.'),
      el('h2', { text: 'Next evidence needed' }),
      list(r.next_evidence_needed, 'Nothing listed.'),
      el('h2', { text: 'About these results' }),
      el('p', { text: r.policy_note }),
      el('div', { className: 'row controls' },
        el('a', { className: 'button-link', href: 'api.php?action=report&format=download', download: 'algebra-session-report.json', testid: 'download-report' }, 'Download JSON'),
        button('Print', 'print-report', () => window.print()),
        button('Start a new session', 'new-session', () => { state.report = null; state.screen = 'welcome'; render(); }),
        state.view && state.view.status !== 'CLOSED' ? button('Back', 'back', () => { state.screen = 'workspace'; render(); }) : null),
    );
  }

  document.addEventListener('DOMContentLoaded', () => {
    root = document.getElementById('app');
    statusEl = document.getElementById('status');
    const forgetButton = document.getElementById('forget');
    if (forgetButton) forgetButton.addEventListener('click', forget);
    boot().catch(() => {
      root.replaceChildren(el('p', { className: 'error-banner', role: 'alert', text: 'The tutor could not load. Please reload the page.' }));
    });
  });
})();
