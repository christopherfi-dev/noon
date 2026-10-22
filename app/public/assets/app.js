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
    draft: { key: null, text: '', options: [] },
    lastItemKey: null,
    lastSubmit: null,       // the item and answer last sent (round UI, U1)
    stuckOpen: false,       // the "I'm stuck" choices (round UI, U5)
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

  // Routine confirmations are for screen readers only; anything else is also shown.
  const ROUTINE = ['Sending…', 'Saved.', 'Done.', 'Working…', 'Summary loaded.', 'Loading your summary…'];

  function announce(text) {
    if (!statusEl) return;
    statusEl.textContent = '';
    statusEl.classList.toggle('visually-hidden', ROUTINE.includes(text));
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
      state.draft = { key: state.lastItemKey, text: '', options: [] };
      state.stuckOpen = false;
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

  // ------------------------------------------------------------ math text

  // Round UI (U3): equations and expressions never break across lines. A run of
  // numbers, x or y, operators and parentheses (single spaces between) becomes a
  // nowrap span; the sentence around it may wrap. The same reading is used on the
  // server (ProblemText) to name the problem a piece of feedback is about.
  const MATH_TOKEN = '(?:\\d+(?:\\.\\d+)?|(?<![\\p{L}])[xy](?![\\p{L}]|-\\p{L})|[+\\-−×÷·=\\/^()²])';
  const MATH_RUN = new RegExp(MATH_TOKEN + '(?: ?' + MATH_TOKEN + ')*', 'gu');

  function trimRun(run) {
    let t = run.trim();
    t = t.replace(/^(?:[+=×÷·/^)]\s?)+/u, '').replace(/(?:\s?[+\-−=×÷·/^(])+$/u, '');
    const count = (str, c) => str.split(c).length - 1;
    while (t && count(t, '(') > count(t, ')') && t.startsWith('(')) t = t.slice(1).trimStart();
    while (t && count(t, ')') > count(t, '(') && t.endsWith(')')) t = t.slice(0, -1).trimEnd();
    return t.trim();
  }

  function isMath(run) {
    return /[\dxy]/u.test(run) && /[+\-−×÷·=/^()]/u.test(run);
  }

  /** @return {Array<{text: string, math: boolean}>} */
  function mathParts(text) {
    const parts = [];
    let at = 0;
    for (const m of text.matchAll(MATH_RUN)) {
      const run = trimRun(m[0]);
      if (!run || !isMath(run)) continue;
      const start = m.index + m[0].indexOf(run);
      if (start > at) parts.push({ text: text.slice(at, start), math: false });
      parts.push({ text: run, math: true });
      at = start + run.length;
    }
    if (at < text.length) parts.push({ text: text.slice(at), math: false });
    return parts;
  }

  /** The item's main equation or expression: the longest run with "=", else the longest run. */
  function mainExpression(text) {
    let best = '';
    for (const p of mathParts(text)) {
      if (!p.math) continue;
      const better = (p.text.includes('=') && !best.includes('=')) || (p.text.includes('=') === best.includes('=') && p.text.length > best.length);
      if (better) best = p.text;
    }
    return best;
  }

  /** Text nodes and nowrap math spans only; nothing is parsed as HTML. */
  function mathText(text, emphasise) {
    const frag = document.createDocumentFragment();
    let done = false;
    for (const p of mathParts(text)) {
      if (!p.math) { frag.append(document.createTextNode(p.text)); continue; }
      const main = !done && emphasise && p.text === emphasise;
      if (main) done = true;
      frag.append(el('span', { className: main ? 'math math-main' : 'math', text: p.text }));
    }
    return frag;
  }

  // ------------------------------------------------------------- the item

  /** Split an item prompt: an optional "Problem:" line (guided steps), the stem, and lettered options. */
  function parsePrompt(prompt) {
    const lines = prompt.split('\n');
    let problem = null;
    if (lines.length > 1 && /^Problem: /.test(lines[0])) problem = lines.shift().slice('Problem: '.length);
    const options = [];
    const stem = [];
    for (const line of lines) {
      const m = /^([A-D])\) (.*)$/.exec(line);
      if (m) options.push({ letter: m[1], text: m[2] });
      else if (!options.length) stem.push(line);
    }
    return { problem, stem: stem.join('\n').trim(), options };
  }

  function allowed(action) {
    return !!state.view && state.view.allowed_actions.includes(action);
  }

  function guided(v) {
    return v.mode === 'GUIDED_REPAIR';
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
    // U1: remember what was answered, so the feedback can name it once the item has left the screen.
    state.lastSubmit = { key: itemKey(v), prompt: v.active_item.prompt, type: v.active_item.response_type, text };
    sendEvent(allowed('CLARIFY_RESPONSE') ? 'CLARIFY_RESPONSE' : 'SUBMIT_RESPONSE', { text }).then((r) => {
      if (r && r.status === 200) focusAnswer();
    });
  }

  function stuckAction(action) {
    state.stuckOpen = false;
    sendEvent(action).then((r) => { if (r && r.status === 200) focusAnswer(); });
  }

  function focusAnswer() {
    window.setTimeout(() => {
      const input = root.querySelector('[data-testid="answer-input"], [data-testid^="option-"]');
      if (input && !input.disabled) input.focus();
    }, 0);
  }

  function renderOptions(it, options, disabled) {
    return el('fieldset', { className: 'options' },
      el('legend', { text: it.input_help || 'Choose every option that applies.' }),
      options.map((o) => {
        const id = 'opt-' + o.letter;
        return el('div', { className: 'option' },
          el('input', {
            type: 'checkbox', id, testid: 'option-' + o.letter, checked: state.draft.options.includes(o.letter), disabled,
            onchange: (e) => {
              const set = new Set(state.draft.options);
              if (e.target.checked) set.add(o.letter); else set.delete(o.letter);
              state.draft.options = Array.from(set);
            },
          }),
          el('label', { for: id, testid: 'option-label-' + o.letter },
            el('span', { className: 'option-letter', text: o.letter + ')' }), ' ', mathText(o.text)));
      }));
  }

  function renderAnswerForm(v, parsed) {
    const it = v.active_item;
    const disabled = state.busy || v.status === 'PENDING';
    const isGuided = guided(v);
    const fields = [];
    if (it.response_type === 'classification') {
      const options = parsed.options.length ? parsed.options : ['A', 'B', 'C', 'D'].map((L) => ({ letter: L, text: '' }));
      fields.push(renderOptions(it, options, disabled));
    } else {
      fields.push(
        el('label', { for: 'answer', className: 'field-label', text: it.input_help || 'Your answer' }),
        el('input', {
          id: 'answer', type: 'text', testid: 'answer-input', value: state.draft.text, maxlength: 2000, disabled,
          autocomplete: 'off', autocapitalize: 'off', spellcheck: 'false', inputmode: 'text', enterkeyhint: 'send',
          oninput: (e) => { state.draft.text = e.target.value; },
        }));
    }
    // U5: Submit, "I'm stuck" (which opens the ways to get help) and Skip where the engine allows it.
    const choices = isGuided
      ? [['REQUEST_HINT', 'Give me a hint', 'hint'], ['NOT_LEARNED', "I don't know", 'not-learned']]
      : [['REQUEST_HINT', 'Give me a hint', 'hint'], ['REQUEST_EXPLANATION', 'Explain the idea', 'explain'], ['NOT_LEARNED', "I haven't learned this yet", 'not-learned']];
    const available = choices.filter(([action]) => allowed(action));
    const canSubmit = allowed('SUBMIT_RESPONSE') || allowed('CLARIFY_RESPONSE');
    return el('form', { className: 'answer-form', onsubmit: submitAnswer, novalidate: true }, fields,
      el('div', { className: 'row controls' },
        canSubmit
          ? el('button', { type: 'submit', className: 'primary', testid: 'submit', disabled }, state.busy ? 'Checking…' : allowed('CLARIFY_RESPONSE') ? 'Submit again' : 'Submit')
          : null,
        available.length ? button("I'm stuck", 'stuck', () => { state.stuckOpen = !state.stuckOpen; render(); }, {
          disabled, className: 'stuck-toggle', 'aria-expanded': state.stuckOpen ? 'true' : 'false', 'aria-controls': 'stuck-menu',
        }) : null,
        allowed('SKIP_ITEM') && !isGuided ? button('Skip', 'skip', () => sendEvent('SKIP_ITEM'), { disabled, className: 'quiet' }) : null),
      available.length ? el('div', {
        id: 'stuck-menu', className: 'stuck-menu', testid: 'stuck-menu', hidden: !state.stuckOpen, role: 'group', 'aria-label': 'Ways to get help',
        onkeydown: (e) => {
          if (e.key === 'Escape') { state.stuckOpen = false; render(); const t = root.querySelector('[data-testid="stuck"]'); if (t) t.focus(); }
        },
      },
      available.map(([action, label, testid]) => button(label, testid, () => stuckAction(action), { disabled }))) : null);
  }

  // ---------------------------------------------------------- the feedback

  function feedbackKind(text) {
    if (text === 'Correct.') return 'correct';
    if (text === 'Not yet.') return 'notyet';
    return 'partial';
  }

  const FEEDBACK_ICON = { correct: '✓', notyet: '', partial: '' };

  /** U1: name the answered problem when the server's messages do not already do so. */
  function echoLine(v) {
    const last = state.lastSubmit;
    if (!last || last.key === itemKey(v) || !v.messages.some((m) => m.kind === 'FEEDBACK')) return null;
    if (v.messages.some((m) => /^(On .+ you (answered|chose) |You (answered|chose) )/.test(m.text))) return null;
    const problem = mainExpression(parsePrompt(last.prompt).stem);
    let answer = last.text.replace(/\s+/g, ' ').trim();
    let verb;
    if (last.type === 'classification') {
      const letters = answer.split(/[\s,;]+/).filter(Boolean);
      verb = 'you chose ' + (letters.length > 1 ? letters.slice(0, -1).join(', ') + ' and ' + letters[letters.length - 1] : letters[0] || answer);
    } else {
      if (answer.length > 40) answer = answer.slice(0, 39) + '…';
      verb = 'you answered ' + answer;
    }
    return problem ? 'On ' + problem + ' ' + verb + '.' : verb.charAt(0).toUpperCase() + verb.slice(1) + '.';
  }

  /**
   * U8: a turn that starts with a result is shown as one block: a green-tinted
   * block with a check for "Correct.", a neutral one for "Not yet." (and for an
   * answer that is equal but not in the requested form). Icon and words always
   * carry the meaning; colour is never the only signal.
   */
  function renderMessages(v) {
    const blocks = [];
    let block = null;
    const echo = echoLine(v);
    for (const m of v.messages) {
      if (m.kind === 'FEEDBACK') {
        const kind = feedbackKind(m.text);
        block = el('li', { className: 'msg feedback feedback-' + kind, testid: 'feedback', 'data-result': kind },
          el('p', { className: 'feedback-head' },
            el('span', { className: 'feedback-icon', 'aria-hidden': 'true', text: FEEDBACK_ICON[kind] }),
            el('span', { className: 'feedback-word', testid: 'feedback-word' }, mathText(m.text))));
        if (echo) block.append(el('p', { className: 'feedback-context', testid: 'feedback-echo' }, mathText(echo)));
        blocks.push(block);
      } else if (m.kind === 'INFORMATION' && block) {
        block.append(el('p', { className: /^On .+ you (answered|chose) /.test(m.text) ? 'feedback-context' : null }, mathText(m.text)));
      } else {
        block = null;
        blocks.push(el('li', { className: 'msg msg-' + m.kind.toLowerCase() }, el('p', null, mathText(m.text))));
      }
    }
    return el('ul', { className: 'messages', testid: 'messages', role: 'log', 'aria-live': 'polite', 'aria-label': 'Tutor messages' }, blocks);
  }

  // -------------------------------------------------------- goal progress

  // U7: the goal counts answers on the items of these modes; a probe and a guided step do not count.
  const COUNTED_MODES = ['TARGET_ASSESSMENT', 'PREREQUISITE_CHECK', 'INDEPENDENT_CHECK', 'RETURN_TO_TARGET'];

  function renderProgress(v, reportReady) {
    const lines = (v.progress.label || '').split('\n');
    const context = lines.shift();
    const parts = [];
    if (context) parts.push(el('p', { className: 'context', testid: 'progress-context', text: context }));
    if (!reportReady && v.active_item && COUNTED_MODES.includes(v.mode) && !guided(v)) {
      const need = v.progress.criterion_required;
      const have = Math.min(v.progress.independent_successes, need);
      const dots = el('span', { className: 'dots', 'aria-hidden': 'true', testid: 'goal-dots', 'data-filled': String(have) },
        Array.from({ length: need }, (_, i) => el('span', { className: i < have ? 'dot dot-filled' : 'dot' })));
      parts.push(el('div', { className: 'goal', testid: 'goal' }, dots,
        el('div', { className: 'goal-text' },
          lines.map((line, i) => el('p', { className: i === 0 ? 'goal-count' : 'goal-note', testid: i === 0 ? 'goal-count' : null, text: line })))));
    } else if (lines.length) {
      parts.push(el('div', { className: 'goal goal-none', testid: 'goal-note' }, lines.map((line) => el('p', { className: 'goal-note', text: line }))));
    }
    return el('div', { className: 'progress', testid: 'progress' }, parts);
  }

  function renderWorkspace() {
    const v = state.view;
    const reportReady = v.mode === 'SESSION_REPORT' || v.status === 'CLOSED';
    const pending = v.status === 'PENDING';
    const heading = MODE_HEADINGS[v.mode] || 'Practice';
    const it = v.active_item;
    const parsed = it ? parsePrompt(it.prompt) : null;
    const main = parsed ? mainExpression(parsed.stem) : '';
    return el('section', { className: 'screen workspace', testid: 'workspace', 'aria-labelledby': 'ws-heading' },
      el('p', { className: 'topic', testid: 'target-title', text: v.target ? 'Topic: ' + v.target.title : 'No topic chosen yet' }),
      el('h1', { id: 'ws-heading', text: heading }),
      renderProgress(v, reportReady),
      errorBanner(),
      renderMessages(v),
      pending ? el('p', { className: 'pending', role: 'status', text: 'Working…' }) : null,
      it && !reportReady ? el('div', { className: 'question card' + (guided(v) ? ' question-guided' : '') },
        el('h2', { className: 'visually-hidden', text: 'Question' }),
        // U6: the only notice that guided work does not count.
        guided(v) ? el('p', { className: 'assisted', testid: 'assisted-tag', text: 'Practice with help: not counted toward the goal' }) : null,
        parsed.problem ? el('p', { className: 'problem', testid: 'guided-problem' }, el('span', { className: 'problem-label', text: 'Problem ' }), mathText(parsed.problem, parsed.problem)) : null,
        el('p', { className: 'prompt', testid: 'prompt' }, mathText(parsed.stem, main)),
        renderAnswerForm(v, parsed)) : null,
      v.menu && v.menu.options && v.menu.options.length && !reportReady ? el('div', { className: 'menu card' },
        el('h2', { text: v.menu.menu_id === 'continuation' ? 'Keep going?' : 'Choose an option' }),
        el('div', { className: 'menu-options' }, v.menu.options.map((o) => button(o.label, 'menu-option-' + o.option_id, () => {
          if (o.action === 'SELECT_TARGET') sendEvent('SELECT_TARGET', { target_skill_id: o.option_id });
          else if (o.action === 'CONTINUE_SESSION') sendEvent('CONTINUE_SESSION', { continue_minutes: 10 });
          else sendEvent(o.action, {});
        }, { disabled: state.busy || pending })))) : null,
      allowed('RESUME_SESSION') ? el('div', { className: 'row' }, button('Try again', 'retry', () => sendEvent('RESUME_SESSION'), { className: 'primary' })) : null,
      reportReady ? el('div', { className: 'card done-card' },
        el('p', { text: v.status === 'CLOSED' ? 'This session has ended.' : 'Your work on this topic is complete for this session.' }),
        el('div', { className: 'row' },
          button('View your session summary', 'view-report', loadReport, { className: 'primary' }),
          v.status !== 'CLOSED' && allowed('END_SESSION') ? button('Close session', 'end-session', () => sendEvent('END_SESSION')) : null,
          button('Start a new session', 'new-session', () => { state.screen = 'welcome'; render(); }))) : null,
      !reportReady ? el('div', { className: 'row session-controls' },
        allowed('CHANGE_TARGET') ? button(state.picker ? 'Hide topics' : 'Change topic', 'change-topic', () => { state.picker = !state.picker; render(); }, { 'aria-expanded': state.picker ? 'true' : 'false', className: 'quiet' }) : null,
        allowed('END_SESSION') ? button('End session', 'end-session', () => {
          if (window.confirm('End this session and see your summary?')) sendEvent('END_SESSION');
        }, { className: 'quiet' }) : null) : null,
      state.picker && allowed('CHANGE_TARGET') && !reportReady ? el('div', { className: 'card picker' },
        el('h2', { text: 'Switch to another skill' }),
        el('p', { text: 'Your current work is kept in the record as interrupted; the new topic starts fresh.' }),
        skillButtonList((id) => { state.picker = false; sendEvent('CHANGE_TARGET', { target_skill_id: id }); }, 'change-to-', null)) : null,
    );
  }

  // --------------------------------------------------------------- summary

  function list(items, empty, testid) {
    if (!items || !items.length) return el('p', { className: 'muted', testid, text: empty });
    return el('ul', { testid }, items.map((t) => el('li', null, mathText(t))));
  }

  /** U2: the learner level on top; the full record behind "Details for teachers". */
  function renderSummary() {
    const data = state.report;
    const learner = data.summary.learner;
    const teacher = data.summary.teacher;
    const cols = [['Skill', null], ['Questions answered', 'items_attempted'], ['Without help', 'independent'], ['With help', 'assisted'], ['Goal', 'goal'], ['What the beta checks', 'checked']];
    const table = el('table', { className: 'skills-table', testid: 'skills-table' },
      el('caption', { text: 'Per skill, this session' }),
      el('thead', null, el('tr', null, cols.map(([h]) => el('th', { scope: 'col', text: h })))),
      el('tbody', null, teacher.skills.map((s) => el('tr', null,
        el('th', { scope: 'row', 'data-label': 'Skill', text: s.name }),
        cols.slice(1).map(([h, key]) => el('td', { 'data-label': h, text: String(s[key]) }))))));
    const details = teacher.skills.map((s) => el('section', { className: 'skill-detail' },
      el('h4', { text: s.name }),
      el('h5', { text: 'What we saw (observations, not confirmed causes)' }),
      list(s.observed, 'Nothing to note.'),
      s.route.length ? el('div', null, el('h5', { text: 'Route' }), list(s.route, '')) : null,
      s.notes.length ? el('div', null, el('h5', { text: 'Not in this beta' }), list(s.notes, '')) : null));
    return el('section', { className: 'screen summary', testid: 'report', 'aria-labelledby': 'summary-heading' },
      el('h1', { id: 'summary-heading', tabindex: '-1', text: 'Session summary' }),
      el('p', { className: 'provider-label', testid: 'report-provider', text: data.provider_label }),
      el('div', { className: 'learner-summary', testid: 'learner-summary' },
        el('section', { className: 'summary-part' }, el('h2', { text: 'What you worked on' }), list(learner.worked_on, 'No skill was started.')),
        el('section', { className: 'summary-part' }, el('h2', { text: 'Goals met' }), list(learner.goals_met, 'No goal was met in this session.')),
        el('section', { className: 'summary-part' }, el('h2', { text: 'What was hard' }), list(learner.hard, 'You did not need help in this session.')),
        el('section', { className: 'summary-part' }, el('h2', { text: 'What to practise next' }), list(learner.next, 'Nothing listed.')),
        el('p', { className: 'muted summary-note', text: learner.note })),
      el('details', { className: 'teacher-details', testid: 'teacher-details' },
        el('summary', { testid: 'teacher-toggle' }, el('span', { text: 'Details for teachers' })),
        el('div', { className: 'teacher-body' },
          el('p', null, 'Status: ' + teacher.status + '. Topic' + (teacher.topics.length === 1 ? '' : 's') + ': ' + (teacher.topics.join(', ') || 'none') + '.'),
          el('div', { className: 'table-wrap' }, table),
          details,
          teacher.sections.map((sec) => el('section', { className: 'teacher-section' },
            el('h3', { text: sec.heading }),
            sec.lines.length === 1 && sec.heading === 'About these results' ? el('p', null, sec.lines[0]) : list(sec.lines, sec.empty))))),
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
    // A printed summary includes the teacher details.
    window.addEventListener('beforeprint', () => { document.querySelectorAll('details').forEach((d) => { d.open = true; }); });
    boot().catch(() => {
      root.replaceChildren(el('p', { className: 'error-banner', role: 'alert', text: 'The tutor could not load. Please reload the page.' }));
    });
  });
})();
