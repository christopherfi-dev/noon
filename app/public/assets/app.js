/*
 * Algebra Engine AI tutor UI (round 6). Vanilla JS; all text from the server,
 * the tutor or the learner is inserted with textContent only, never parsed as
 * HTML. The session is a conversation: tutor and student messages scroll with
 * the newest at the bottom, the open problem is pinned above one input for an
 * answer or a message, and code's results, check questions and goal dots come
 * from the server view.
 */
'use strict';

(() => {
  const PROBLEM_ACTIONS = ['SEND', 'STUCK', 'SKIP'];
  const STUCK_CHOICES = [['HINT', 'Give me a hint', 'hint'], ['NOT_LEARNED', "I haven't learned this yet", 'not-learned']];
  const CHECK_LABEL = 'Check question: on your own';
  const AVAILABILITY = {
    AVAILABLE_SCORED: 'Available in this tutor',
    PARTIAL_COMPONENTS: 'Partial: numeric part only',
    UNAVAILABLE: 'Not available in this tutor',
  };

  const state = {
    csrf: null,
    boot: null,
    view: null,
    screen: 'welcome',      // welcome | pick | browse | workspace | summary | moved | forgotten
    report: null,
    search: '',
    fullMap: false,         // skill map: the tutor's skills by default; the full 205-skill map on request
    picker: false,
    busy: false,
    error: null,
    draft: { key: null, text: '', options: [] },
    code: null,             // this session's progress code, once known
    codeInput: '',
    codeError: null,
    welcomeBack: null,      // what a typed code's record says ("Welcome back")
    moved: false,           // this session continued on another device
    forgotten: null,        // the message after "Forget this session"
    stuckOpen: false,       // the "I'm stuck" choices
    lastLineId: null,       // the newest conversation line already shown (scroll to it when a newer one arrives)
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
  const ROUTINE = ['Sending…', 'Sent.', 'Done.', 'The tutor is writing…', 'Summary loaded.', 'Loading your summary…', 'Welcome back.'];

  function announce(text) {
    if (!statusEl) return;
    statusEl.textContent = '';
    statusEl.classList.toggle('visually-hidden', ROUTINE.includes(text));
    window.setTimeout(() => { statusEl.textContent = text; }, 30);
  }

  function problemKey(view) {
    const p = view && view.open_problem;
    return p ? p.problem_id + '#' + p.problem_version : null;
  }

  function liveSession() {
    const v = state.view;
    return v && v.status !== 'CLOSED' ? v : null;
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
    state.view = view;
    const key = problemKey(view);
    if (key !== state.draft.key) {
      // A new open problem: its choices start empty; a typed message is kept.
      state.draft = { key, text: state.draft.text, options: [] };
      state.stuckOpen = false;
    }
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

  /**
   * Run a mutation. The learner's typed text is kept on any recoverable
   * error; onOk runs before the page is drawn again.
   */
  async function mutate(action, body, onOk) {
    if (state.busy) return null;
    state.busy = true;
    state.error = null;
    announce(action === 'event' && body.action === 'SEND' ? 'The tutor is writing…' : 'Sending…');
    render();
    let result = null;
    try {
      const r = await request('POST', action, body);
      result = r;
      if (r.status === 200 && r.data && typeof r.data.revision === 'number') {
        adoptView(r.data);
        if (onOk) onOk(r);
        announce(r.data.status === 'HOLD' ? 'Your work is saved, but the tutor\'s reply is not ready yet.' : 'Sent.');
        if (!state.code) await fetchCode();
      } else if (r.status === 202) {
        announce('The tutor is writing…');
        const settled = await pollUntilSettled();
        announce(settled ? 'Sent.' : 'Still working. Please wait a moment and reload.');
      } else if (r.status === 200) {
        if (onOk) onOk(r);
        announce('Done.');
      } else if (r.status === 409 && r.data && r.data.error_code === 'CONTINUED_ELSEWHERE') {
        showMoved();
      } else {
        const message = (r.data && r.data.message) || 'Something went wrong. Your typed text is kept.';
        state.error = message;
        if (r.status === 409 && r.data && r.data.reload) {
          const keep = state.draft;
          await refreshState();
          state.draft = Object.assign({}, state.draft, { text: keep.text });
        }
        announce(message);
      }
    } catch (e) {
      state.error = 'The connection failed. Your typed text is kept; please try again.';
      announce(state.error);
    } finally {
      state.busy = false;
      render();
    }
    return result;
  }

  function sendEvent(action, payload, onOk) {
    const v = state.view;
    if (!v) return Promise.resolve(null);
    const withProblem = PROBLEM_ACTIONS.includes(action) && v.open_problem;
    return mutate('event', {
      idempotency_key: newKey(),
      expected_revision: v.revision,
      action,
      problem_id: withProblem ? v.open_problem.problem_id : null,
      problem_version: withProblem ? v.open_problem.problem_version : null,
      payload: payload || {},
    }, onOk);
  }

  // ------------------------------------------------------- progress codes

  /** This session's code, for "Your code". The server reads it from this device's cookie. */
  async function fetchCode() {
    try {
      const r = await request('GET', 'progress');
      if (r.status === 200 && r.data) state.code = r.data.code || null;
      else if (r.status === 409 && r.data && r.data.error_code === 'CONTINUED_ELSEWHERE') showMoved();
    } catch (e) { /* the code is shown after the next answer */ }
  }

  function showMoved() {
    state.moved = true;
    state.screen = 'moved';
    state.view = null;
    state.report = null;
    announce('This progress continued on another device.');
  }

  async function lookupCode(e) {
    if (e) e.preventDefault();
    if (state.busy) return;
    const code = state.codeInput.trim();
    if (!code) { state.codeError = 'Enter your code first.'; render(); return; }
    state.busy = true;
    state.codeError = null;
    render();
    try {
      const r = await request('POST', 'progress_lookup', { code });
      if (r.status === 200 && r.data) {
        state.welcomeBack = r.data;
        announce('Welcome back.');
      } else {
        state.welcomeBack = null;
        state.codeError = (r.data && r.data.message) || "That code wasn't found. Check it, or start fresh.";
        announce(state.codeError);
      }
    } catch (err) {
      state.codeError = 'The connection failed. Please try again.';
    } finally {
      state.busy = false;
      render();
    }
    const card = document.getElementById('welcome-back-heading') || document.getElementById('code');
    if (card) card.focus();
  }

  function enterWorkspace() {
    state.screen = 'workspace';
    state.report = null;
    state.picker = false;
    state.moved = false;
    state.lastLineId = null;
  }

  async function startWithCode(targetSkillId) {
    state.code = null; // the code shown from now on is this one (read back after the start)
    const r = await mutate('progress_start', { code: state.codeInput.trim(), idempotency_key: newKey(), target_skill_id: targetSkillId || null });
    if (r && r.status === 200) {
      state.welcomeBack = null;
      state.codeInput = '';
      enterWorkspace();
      render();
      focusEntry();
    } else if (r && r.data && r.data.error_code === 'CODE_NOT_FOUND') {
      state.error = null;
      state.welcomeBack = null;
      state.codeError = r.data.message;
      render();
    }
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
      state.code = null; // a fresh session gets its own code at its first answer; any earlier record is untouched
      state.draft = { key: problemKey(state.view), text: '', options: [] };
      enterWorkspace();
      render();
      focusEntry();
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
        if (r.data.progress_code) state.code = r.data.progress_code;
        state.screen = 'summary';
        announce('Summary loaded.');
      } else if (r.status === 409 && r.data && r.data.error_code === 'CONTINUED_ELSEWHERE') {
        showMoved();
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

  /** End the session (after a confirmation when asked for one), then show the summary code wrote. */
  async function finish(confirmFirst) {
    if (confirmFirst && !window.confirm('End this session and see your summary?')) return;
    const r = await sendEvent('END_SESSION');
    if (r && r.status === 200) await loadReport();
  }

  async function forget() {
    if (!window.confirm('Delete this session, your answers, your summary and its progress code from this server?')) return;
    const r = await request('POST', 'forget', { idempotency_key: newKey() });
    if (r.status === 200) {
      state.forgotten = (r.data && r.data.message) || 'This session was deleted.';
      state.screen = 'forgotten';
      state.view = null;
      state.report = null;
      state.code = null;
      announce(state.forgotten);
      render();
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
    const privacy = document.querySelector('[data-testid="privacy-progress"]');
    if (privacy && r.data.notices && r.data.notices.progress) privacy.textContent = r.data.notices.progress;
    state.screen = r.data.continued_elsewhere ? 'moved' : 'welcome';
    state.moved = !!r.data.continued_elsewhere;
    if (r.data.session && r.data.session.status !== 'CLOSED') await fetchCode();
    render();
  }

  // ---------------------------------------------------------------- render

  function render() {
    if (!root) return;
    root.setAttribute('aria-busy', state.busy ? 'true' : 'false');
    const oldLog = root.querySelector('[data-testid="conversation"]');
    const oldScroll = oldLog ? oldLog.scrollTop : null;
    let screen;
    switch (state.screen) {
      case 'pick': screen = renderPick(); break;
      case 'browse': screen = renderBrowse(); break;
      case 'workspace': screen = state.view ? renderWorkspace() : renderWelcome(); break;
      case 'summary': screen = state.report ? renderSummary() : renderWelcome(); break;
      case 'moved': screen = renderMessage('moved', 'Continued on another device', 'This progress continued on another device. This page can no longer send answers.'); break;
      case 'forgotten': screen = renderMessage('forgotten', 'Deleted', state.forgotten || 'This session was deleted.'); break;
      default: screen = renderWelcome();
    }
    const active = document.activeElement;
    const activeId = active && active.getAttribute ? active.getAttribute('data-testid') : null;
    root.replaceChildren(screen);
    // The conversation keeps its place, and moves to the newest line when one arrives.
    const log = root.querySelector('[data-testid="conversation"]');
    if (log) {
      const lines = state.view ? state.view.conversation : [];
      const newest = lines.length ? lines[lines.length - 1].id : null;
      const arrived = newest !== state.lastLineId || oldScroll === null;
      if (log.scrollHeight > log.clientHeight + 1) {
        log.scrollTop = arrived ? log.scrollHeight : oldScroll; // wide screens: the conversation's own box
      } else if (arrived && log.lastElementChild) {
        // Narrow screens: the page scrolls; keep the newest line above the composer stuck to the bottom.
        const composer = root.querySelector('[data-testid="composer"]');
        log.lastElementChild.style.scrollMarginBottom = (composer ? composer.offsetHeight + 8 : 8) + 'px';
        log.lastElementChild.scrollIntoView({ block: 'end' });
      }
      state.lastLineId = newest;
    }
    if (activeId) {
      const again = root.querySelector('[data-testid="' + activeId + '"]');
      if (again && again !== document.activeElement && !again.disabled) again.focus();
    }
  }

  function renderMessage(testid, heading, text) {
    return el('section', { className: 'screen', testid, 'aria-labelledby': 'message-heading' },
      el('h1', { id: 'message-heading', tabindex: '-1', text: heading }),
      el('div', { className: 'card notice-card' },
        el('p', { text }),
        el('div', { className: 'row' },
          button('Start a new session', 'message-new-session', () => { window.location.reload(); }, { className: 'primary' }))));
  }

  /** "Your code" in the header area of a session and of its summary. */
  function codeBar() {
    if (!state.code) return null;
    return el('div', { className: 'code-bar', testid: 'your-code', role: 'note', 'aria-label': 'Your progress code' },
      el('span', { className: 'code-label', text: 'Your code' }),
      el('span', { className: 'code-value', testid: 'code-value', text: state.code }),
      el('span', { className: 'code-note', text: 'Write this down or download your report to continue next time.' }));
  }

  function headingList(lines, empty, testid) {
    if (!lines || !lines.length) return el('p', { className: 'muted empty-line', testid, text: empty });
    return el('ul', { testid }, lines.map((t) => el('li', null, mathText(t))));
  }

  /** "Continue with a progress code" and, for a valid code, "Welcome back" (written by code from the record). */
  function renderCodeCard() {
    const wb = state.welcomeBack;
    const form = el('form', { className: 'code-form', onsubmit: lookupCode, novalidate: true },
      el('label', { for: 'code', className: 'field-label', text: 'Continue with a progress code' }),
      el('div', { className: 'code-row' },
        el('input', {
          id: 'code', type: 'text', testid: 'code-input', value: state.codeInput, maxlength: 40, autocomplete: 'off', autocapitalize: 'characters', spellcheck: 'false',
          placeholder: 'K7QM-4XRT-9WPC', 'aria-describedby': state.codeError ? 'code-error' : null,
          oninput: (e) => { state.codeInput = e.target.value; },
        }),
        el('button', { type: 'submit', testid: 'code-continue', disabled: state.busy }, 'Continue')),
      state.codeError ? el('p', { id: 'code-error', className: 'error-banner', role: 'alert', testid: 'code-error', text: state.codeError }) : null);
    if (!wb) return el('div', { className: 'card code-card' }, form);
    const sum = wb.summary;
    return el('div', { className: 'card code-card' }, form,
      el('section', { className: 'welcome-back', testid: 'welcome-back', 'aria-labelledby': 'welcome-back-heading' },
        el('h2', { id: 'welcome-back-heading', tabindex: '-1', text: 'Welcome back' }),
        el('p', { className: 'muted', text: 'From last time, with code ' + wb.code + ':' }),
        el('div', { className: 'learner-summary compact' },
          Object.keys(sum.headings).map((k) => el('section', { className: 'summary-part' },
            el('h3', { text: sum.headings[k] }), headingList(sum[k], sum.empty[k])))),
        wb.all_met
          ? el('div', null,
            el('p', { testid: 'all-met', text: 'You met every goal on this route. Choose a skill to keep practising with this code.' }),
            skillButtonList((id) => startWithCode(id), 'code-skill-', null))
          : el('div', { className: 'row' },
            button(wb.live ? 'Start: continue where you stopped' : 'Start: ' + (wb.next_skill ? wb.next_skill.title : 'continue'), 'code-start', () => startWithCode(null), { className: 'primary' }))));
  }

  function errorBanner() {
    return state.error ? el('p', { className: 'error-banner', role: 'alert', testid: 'error-banner', text: state.error }) : null;
  }

  function renderWelcome() {
    const boot = state.boot;
    const beta = boot.beta;
    const def = beta.skills.find((s) => s.skill_id === beta.default_target) || beta.skills[0];
    const live = liveSession();
    return el('section', { className: 'screen welcome', 'aria-labelledby': 'welcome-heading' },
      el('h1', { id: 'welcome-heading', text: 'Talk algebra through with a tutor' }),
      el('p', { className: 'lead', text: 'Pick one algebra skill. Answer in your own words, ask questions, and say when you are stuck. The tutor decides what to try next; the app checks every answer itself and keeps track of your goals.' }),
      errorBanner(),
      live ? el('div', { className: 'card resume' },
        el('h2', { text: 'You have a session in progress' }),
        el('p', { text: live.topic ? 'Topic: ' + live.topic.title : 'No topic chosen yet.' }),
        el('div', { className: 'row' },
          button('Continue your session', 'continue-session', () => { enterWorkspace(); render(); focusEntry(); }, { className: 'primary' }),
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
      renderCodeCard(),
      el('div', { className: 'notices' },
        el('h2', { text: 'Before you start' }),
        el('p', { text: boot.notices.data }),
        boot.notices.progress ? el('p', { text: boot.notices.progress }) : null,
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
      el('p', { text: 'These ' + state.boot.beta.skills.length + ' skills are available in this tutor.' }),
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
      el('h1', { id: 'browse-heading', text: state.fullMap ? 'All ' + state.boot.catalog.length + ' curriculum skills' : 'The ' + betaIds.size + ' skills in this tutor' }),
      el('p', { text: state.fullMap
        ? 'The full map is shown for reference. Only skills marked as available can be practised here; the rest are not yet supported and are never treated as gaps in your knowledge.'
        : 'These are the skills you can practise with this tutor. The full curriculum map has ' + state.boot.catalog.length + ' skills.' }),
      errorBanner(),
      button(state.fullMap ? 'Show tutor skills only' : 'Show full map', 'toggle-full-map', () => { state.fullMap = !state.fullMap; render(); }, { 'aria-pressed': state.fullMap ? 'true' : 'false' }),
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

  // Equations and expressions never break across lines. A run of numbers, x or
  // y, operators and parentheses (single spaces between) becomes a nowrap span;
  // the sentence around it may wrap.
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

  /** The problem's main equation or expression: the longest run with "=", else the longest run. */
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

  /** A problem's wording: the stem, then lettered options as a list. */
  function problemBody(text) {
    const lines = text.split('\n');
    const stem = lines.filter((l) => !/^[A-D]\) /.test(l)).join('\n').trim();
    const options = lines.filter((l) => /^[A-D]\) /.test(l));
    const main = mainExpression(stem);
    return [
      el('p', { className: 'problem-text', testid: 'problem-text' }, mathText(stem, main)),
      options.length ? el('ul', { className: 'option-lines' }, options.map((o) => el('li', null, mathText(o)))) : null,
    ];
  }

  // -------------------------------------------------------- conversation

  const RESULT_ICON = { correct: '✓', notyet: '', partial: '' };
  const SPEAKER = { tutor: 'Tutor', student: 'You', app: 'App' };

  function lineNode(line) {
    const base = { testid: 'line', 'data-role': line.role, 'data-kind': line.kind };
    if (line.kind === 'result') {
      const [word, ...rest] = line.text.split('\n');
      const kind = line.result || 'partial';
      return el('li', Object.assign({ className: 'line line-result result-' + kind, 'data-result': kind }, base),
        el('p', { className: 'result-head' },
          el('span', { className: 'result-icon', 'aria-hidden': 'true', text: RESULT_ICON[kind] }),
          el('span', { className: 'result-word', testid: 'result-word' }, mathText(word))),
        rest.map((t) => el('p', { className: /^On .+ you (answered|chose) /.test(t) ? 'result-context' : 'result-note' }, mathText(t))));
    }
    if (line.kind === 'check') {
      return el('li', Object.assign({ className: 'line line-check' }, base),
        el('div', { className: 'check-card', testid: 'check-card' },
          el('p', { className: 'card-label', text: CHECK_LABEL }),
          problemBody(line.text)));
    }
    if (line.kind === 'problem') {
      return el('li', Object.assign({ className: 'line line-problem line-tutor' }, base),
        el('p', { className: 'speaker', text: 'Tutor: a question to work on' }),
        problemBody(line.text));
    }
    if (line.kind === 'notice' || line.kind === 'error') {
      return el('li', Object.assign({ className: 'line line-' + line.kind }, base),
        line.text.split('\n').map((t) => el('p', null, mathText(t))));
    }
    // Messages and clarifying questions, from the tutor or the student.
    return el('li', Object.assign({ className: 'line line-' + line.role + (line.kind === 'clarify' ? ' line-clarify' : '') }, base),
      el('p', { className: 'speaker', text: SPEAKER[line.role] || '' }),
      line.text.split('\n').map((t) => el('p', { className: 'line-text' }, mathText(t))));
  }

  function renderConversation(v, writing) {
    return el('ol', {
      className: 'conversation', testid: 'conversation', role: 'log', 'aria-live': 'polite', 'aria-label': 'Conversation with the tutor', tabindex: '0',
    }, v.conversation.map(lineNode),
    writing ? el('li', { className: 'line line-writing', testid: 'writing' }, el('p', { text: 'The tutor is writing…' })) : null);
  }

  // -------------------------------------------------------- goal progress

  /** Code's goal status for the skill being checked: dots and its wording. */
  function renderGoal(v) {
    const g = v.goal;
    if (!g) return null;
    const have = g.met ? g.required : Math.min(g.count, g.required);
    const lines = g.label.split('\n');
    const dots = el('span', { className: 'dots', 'aria-hidden': 'true', testid: 'goal-dots', 'data-filled': String(have) },
      Array.from({ length: g.required }, (_, i) => el('span', { className: i < have ? 'dot dot-filled' : 'dot' })));
    return el('div', { className: 'goal' + (g.paused ? ' goal-paused' : ''), testid: 'goal' }, dots,
      el('div', { className: 'goal-text' },
        el('p', { className: 'goal-skill', text: g.title }),
        lines.map((line, i) => el('p', { className: i === 0 ? 'goal-count' : 'goal-note', testid: i === 0 ? 'goal-count' : null, text: line }))));
  }

  // ------------------------------------------------------------ composer

  function allowed(action) {
    return !!state.view && state.view.allowed_actions.includes(action);
  }

  function submitEntry(e) {
    if (e) e.preventDefault();
    const v = state.view;
    if (!v || state.busy || !allowed('SEND')) return;
    const p = v.open_problem;
    let text;
    if (p && p.response_type === 'classification' && state.draft.options.length) {
      text = state.draft.options.slice().sort().join(',');
    } else {
      text = state.draft.text.trim();
    }
    if (!text) { announce(p ? 'Type an answer or a message first.' : 'Type a message first.'); return; }
    sendEvent('SEND', { text }, () => { state.draft.text = ''; state.draft.options = []; }).then((r) => {
      if (r && r.status === 200) focusEntry();
    });
  }

  /** Open or close the help choices; closing returns focus to the input. */
  function toggleStuck(byKeyboard) {
    state.stuckOpen = !state.stuckOpen;
    render();
    if (!state.stuckOpen) focusEntry();
    else if (byKeyboard) { const first = root.querySelector('#stuck-menu button'); if (first) first.focus(); }
  }

  /** Arrow keys move between the choices; Escape closes the menu. */
  function stuckKeys(e) {
    const items = Array.from(root.querySelectorAll('#stuck-menu button'));
    const at = items.indexOf(document.activeElement);
    if (e.key === 'Escape') { e.preventDefault(); state.stuckOpen = false; render(); focusEntry(); return; }
    let to = null;
    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') to = (at + 1) % items.length;
    else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') to = (at - 1 + items.length) % items.length;
    else if (e.key === 'Home') to = 0;
    else if (e.key === 'End') to = items.length - 1;
    if (to !== null && items[to]) { e.preventDefault(); items[to].focus(); }
  }

  function stuckChoice(choice) {
    state.stuckOpen = false;
    sendEvent('STUCK', { choice }).then((r) => { if (r && r.status === 200) focusEntry(); });
  }

  function focusEntry() {
    window.setTimeout(() => {
      const input = root.querySelector('[data-testid="entry-input"]');
      if (input && !input.disabled) input.focus();
    }, 0);
  }

  function renderOptions(p, disabled) {
    return el('fieldset', { className: 'options' },
      el('legend', { text: p.input_help || 'Choose every option that applies.' }),
      (p.options || []).map((o) => {
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

  /** The open problem, pinned above the input; a check question is a distinct card. */
  function renderPinned(p) {
    const check = p.purpose === 'check';
    return el('div', { className: 'pinned' + (check ? ' pinned-check check-card' : ''), testid: 'open-problem', 'data-purpose': p.purpose },
      el('p', { className: 'card-label', testid: 'open-problem-label', text: p.label }),
      el('p', { className: 'prompt', testid: 'prompt' }, mathText(p.prompt, mainExpression(p.prompt))),
      p.with_help ? el('p', { className: 'with-help', testid: 'with-help', text: 'You had help on this one.' }) : null);
  }

  function renderComposer(v) {
    const p = v.open_problem;
    const disabled = state.busy || v.status === 'PENDING';
    const choice = p && p.response_type === 'classification';
    const label = !p ? 'Message the tutor' : choice ? 'Or type a message to the tutor' : p.input_help;
    const canStuck = allowed('STUCK');
    return el('form', { className: 'composer', testid: 'composer', onsubmit: submitEntry, novalidate: true },
      p ? renderPinned(p) : null,
      choice ? renderOptions(p, disabled) : null,
      el('label', { for: 'entry', className: 'field-label', text: label }),
      el('div', { className: 'entry-row' },
        el('input', {
          id: 'entry', type: 'text', testid: 'entry-input', value: state.draft.text, maxlength: 2000, disabled,
          autocomplete: 'off', autocapitalize: 'off', spellcheck: 'false', inputmode: 'text', enterkeyhint: 'send',
          oninput: (e) => { state.draft.text = e.target.value; },
        }),
        el('button', { type: 'submit', className: 'primary', testid: 'send', disabled }, 'Send')),
      canStuck || allowed('SKIP') ? el('div', { className: 'row controls' },
        canStuck ? button("I'm stuck", 'stuck', (e) => toggleStuck(e.detail === 0), {
          disabled, className: 'stuck-toggle', 'aria-expanded': state.stuckOpen ? 'true' : 'false', 'aria-controls': 'stuck-menu',
        }) : null,
        allowed('SKIP') ? button('Skip this question', 'skip', () => sendEvent('SKIP', {}, null).then((r) => { if (r && r.status === 200) focusEntry(); }), { disabled, className: 'quiet' }) : null) : null,
      canStuck ? el('div', {
        id: 'stuck-menu', className: 'stuck-menu', testid: 'stuck-menu', hidden: !state.stuckOpen, role: 'group', 'aria-label': 'Ways to get help',
        onkeydown: stuckKeys,
      }, STUCK_CHOICES.map(([c, text, testid]) => button(text, testid, () => stuckChoice(c), { disabled }))) : null);
  }

  // ------------------------------------------------------------ workspace

  function renderWorkspace() {
    const v = state.view;
    const closed = v.status === 'CLOSED';
    const pending = v.status === 'PENDING';
    const writing = !closed && (pending || (state.busy && state.screen === 'workspace'));
    const focusDiffers = v.focus && v.topic && v.focus.skill_id !== v.topic.skill_id;
    return el('section', { className: 'screen workspace', testid: 'workspace', 'aria-labelledby': 'ws-heading' },
      codeBar(),
      el('h1', { id: 'ws-heading', className: 'ws-heading', testid: 'topic-title', text: v.topic ? v.topic.title : 'No topic chosen yet' }),
      focusDiffers ? el('p', { className: 'focus-line', testid: 'focus-line', text: 'Working on: ' + v.focus.title }) : null,
      closed ? null : renderGoal(v),
      errorBanner(),
      renderConversation(v, writing),
      v.status === 'HOLD' && allowed('RESUME') ? el('div', { className: 'row' }, button('Try again', 'retry', () => sendEvent('RESUME'), { className: 'primary' })) : null,
      v.wrap_up && !closed ? el('div', { className: 'card wrap-up', testid: 'wrap-up' },
        el('p', { text: 'The tutor has finished the work planned for this session. Your summary is written by the app from your answers.' }),
        el('div', { className: 'row' },
          button('Finish and see my summary', 'finish', () => finish(false), { className: 'primary' }),
          button('Keep practising', 'keep-going', focusEntry))) : null,
      closed ? el('div', { className: 'card done-card' },
        el('p', { text: 'This session has ended.' }),
        el('div', { className: 'row' },
          button('View your session summary', 'view-report', loadReport, { className: 'primary' }),
          button('Start a new session', 'new-session', () => { state.screen = 'welcome'; render(); }))) : null,
      !closed && allowed('SEND') ? renderComposer(v) : null,
      !closed ? el('div', { className: 'row session-controls' },
        allowed('CHANGE_TOPIC') ? button(state.picker ? 'Hide topics' : 'Change topic', 'change-topic', () => { state.picker = !state.picker; render(); }, { 'aria-expanded': state.picker ? 'true' : 'false', className: 'quiet' }) : null,
        allowed('END_SESSION') ? button('End session', 'end-session', () => finish(true), { className: 'quiet' }) : null) : null,
      state.picker && allowed('CHANGE_TOPIC') && !closed ? el('div', { className: 'card picker' },
        el('h2', { text: 'Switch to another skill' }),
        el('p', { text: 'The open question is set aside, not marked wrong. Each skill keeps its own goal.' }),
        skillButtonList((id) => { state.picker = false; sendEvent('CHANGE_TOPIC', { topic_skill_id: id }).then((r) => { if (r && r.status === 200) focusEntry(); }); }, 'change-to-', v.topic ? v.topic.skill_id : null)) : null,
    );
  }

  // --------------------------------------------------------------- summary

  function list(items, empty, testid) {
    if (!items || !items.length) return el('p', { className: 'muted', testid, text: empty });
    return el('ul', { testid }, items.map((t) => el('li', null, mathText(t))));
  }

  /** The learner level on top; the full record behind "Details for teachers". Every line is written by code. */
  function renderSummary() {
    const data = state.report;
    const learner = data.summary.learner;
    const teacher = data.summary.teacher;
    const cols = [['Skill', null], ['Questions answered', 'items_attempted'], ['Without help', 'independent'], ['With help or practice', 'assisted'], ['Goal', 'goal'], ['What the goal checks', 'checked']];
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
      s.notes.length ? el('div', null, el('h5', { text: 'Notes' }), list(s.notes, '')) : null));
    return el('section', { className: 'screen summary', testid: 'report', 'aria-labelledby': 'summary-heading' },
      el('h1', { id: 'summary-heading', tabindex: '-1', text: 'Session summary' }),
      el('p', { className: 'provider-label', testid: 'report-provider', text: data.provider_label }),
      codeBar(),
      el('div', { className: 'learner-summary', testid: 'learner-summary' },
        Object.keys(learner.headings).map((k) => el('section', { className: 'summary-part', testid: 'part-' + k },
          el('h2', { text: learner.headings[k] }), headingList(learner[k], learner.empty[k]))),
        learner.earlier && learner.earlier.length ? el('section', { className: 'summary-part earlier', testid: 'part-earlier' },
          el('h2', { text: 'From earlier sessions' }), list(learner.earlier, '')) : null,
        el('p', { className: 'muted summary-note', text: learner.note })),
      el('details', { className: 'teacher-details', testid: 'teacher-details' },
        el('summary', { testid: 'teacher-toggle' }, el('span', { text: 'Details for teachers' })),
        el('div', { className: 'teacher-body' },
          el('p', null, 'Status: ' + teacher.status + '. Topic' + (teacher.topics.length === 1 ? '' : 's') + ': ' + (teacher.topics.join(', ') || 'none') + '.'),
          el('div', { className: 'table-wrap' }, table),
          details,
          teacher.sections.map((sec) => el('section', { className: 'teacher-section', testid: 'teacher-section' },
            el('h3', { text: sec.heading }),
            sec.lines.length === 1 && sec.heading === 'About these results' ? el('p', null, sec.lines[0]) : list(sec.lines, sec.empty))),
          el('p', null, el('a', { className: 'button-link', href: 'api.php?action=report&format=download', download: 'algebra-session-report.json', testid: 'download-json' }, 'Download the record (JSON)')))),
      el('div', { className: 'row controls' },
        el('a', { className: 'button-link primary-link', href: 'api.php?action=report&format=html', download: true, testid: 'download-report' }, 'Download report'),
        button('Print or save as PDF', 'print-report', () => window.print()),
        button('Start a new session', 'new-session', () => { state.report = null; state.screen = 'welcome'; render(); }),
        state.view && state.view.status !== 'CLOSED' ? button('Back', 'back', () => { enterWorkspace(); render(); }) : null),
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
