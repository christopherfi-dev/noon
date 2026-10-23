// Browser tests (mock mode) for the round 6 conversation UI, at 390 and 1280
// wide: the conversation journey (step back, check card, pause, help, wrap-up,
// summary), the slip pause, UI-01 (mobile keyboard, screen-reader status and
// no lost answers at 360 wide), UI-02 (safe rendering), UI-03 (skill map),
// UI-06 (result and check-card contrast) and UI-07 (progress code, welcome
// back, take-over and the downloaded report).
// Usage: node app/tests/Browser/run_browser.mjs <out_dir>
import { createRequire } from 'module';
import { execFileSync, spawn } from 'child_process';
import fs from 'fs';
import path from 'path';
import os from 'os';
import net from 'net';

const require = createRequire(import.meta.url);
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const here = path.dirname(new URL(import.meta.url).pathname);
const appRoot = path.resolve(here, '../..');
const outDir = path.resolve(process.argv[2] || path.join(appRoot, '..', 'test-results', 'browser'));
fs.mkdirSync(outDir, { recursive: true });
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ae-browser-'));
const dbPath = path.join(tmp, 'b.sqlite');
const faultFile = path.join(tmp, 'faults.json');
fs.writeFileSync(faultFile, '[]');
fs.writeFileSync(path.join(tmp, 'config.php'), `<?php return ['db_path' => '${dbPath}', 'mock_fault_file' => '${faultFile}'];`);

const results = [];
const record = (name, pass, detail) => { results.push({ name, pass, detail }); console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}${detail ? '  ' + JSON.stringify(detail) : ''}`); };
const oracle = () => JSON.parse(execFileSync('php', [path.join(here, 'oracle.php'), dbPath]).toString());
const resetDb = () => execFileSync('php', ['-r', `$p=new PDO('sqlite:${dbPath}'); $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); foreach(['sessions','events','items','checker_results','operations','idempotency','reports','containers','counters','model_calls','progress'] as $t) $p->exec("DELETE FROM $t");`]);

async function freePort() {
  return new Promise((resolve) => { const s = net.createServer(); s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => resolve(p)); }); });
}
const port = await freePort();
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', path.join(appRoot, 'public')], { env: { ...process.env, ALGEBRA_CONFIG: path.join(tmp, 'config.php') }, stdio: 'ignore' });
await new Promise((r) => setTimeout(r, 700));
const base = `http://127.0.0.1:${port}/`;

const INTERNAL = /\b(expected values?|expected answers?|answer keys?|matched the key|guided steps?|hosts?|assisted|verdicts?|criterion|criteria)\b/gi;

async function idle(page) {
  await page.waitForFunction(() => document.getElementById('app')?.getAttribute('aria-busy') === 'false', null, { timeout: 15000 });
}
async function overflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
}
const correct = () => { const o = oracle().open; return o.type === 'classification' ? o.expected : String(o.expected).replace(/\*/g, ''); };
async function send(page, text) {
  await page.getByTestId('entry-input').fill(String(text));
  await page.getByTestId('send').click();
  await idle(page);
}
async function answer(page, value) {
  const o = oracle().open;
  if (o.type === 'classification') {
    for (const L of (Array.isArray(value) ? value : String(value).split(','))) await page.getByTestId('option-' + L).check();
    await page.getByTestId('send').click();
    await idle(page);
  } else {
    await send(page, value);
  }
}
const answerCorrectly = (page) => answer(page, correct());
/** The factor reaches only the x: Q07 (c - b) / a; E06 ax + b. */
const xOnly = () => {
  const o = oracle().open;
  const p = o.params;
  if (o.template !== 'GROUPED_LINEAR') return `${p.a}x + ${p.b}`;
  const gcd = (a, b) => (b ? gcd(b, a % b) : Math.abs(a));
  const g = gcd(p.c - p.b, p.a);
  return p.a / g === 1 ? String((p.c - p.b) / g) : `${(p.c - p.b) / g}/${p.a / g}`;
};
const lines = (page) => page.evaluate(() => [...document.querySelectorAll('[data-testid="line"]')].map((l) => ({ role: l.dataset.role, kind: l.dataset.kind, text: l.textContent })));
const dots = (page) => page.evaluate(() => { const d = document.querySelector('[data-testid="goal-dots"]'); return d ? { filled: d.querySelectorAll('.dot-filled').length, total: d.querySelectorAll('.dot').length, hidden: d.getAttribute('aria-hidden'), text: document.querySelector('[data-testid="goal"]').textContent } : null; });
const menuLabels = (page) => page.evaluate(() => { const m = document.querySelector('[data-testid="stuck-menu"]'); return m && !m.hidden ? [...m.querySelectorAll('button')].map((b) => b.textContent.trim()) : []; });

async function newPage(browser, w, h) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h } });
  const page = await ctx.newPage();
  const errors = [];
  const dialogs = [];
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('dialog', async (d) => { dialogs.push(d.message()); await d.accept(); });
  return { ctx, page, errors, dialogs };
}

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
try {
  // ------------------------------------------------ the conversation journey
  for (const [w, h, label] of [[390, 844, 'mobile'], [1280, 800, 'desktop']]) {
    resetDb();
    const { ctx, page, errors } = await newPage(browser, w, h);
    const shot = (name) => page.screenshot({ path: path.join(outDir, `${name}-${w}.png`), fullPage: true });
    const checks = {};
    let maxOverflow = 0;
    const measure = async () => { maxOverflow = Math.max(maxOverflow, await overflow(page)); };
    await page.goto(base);
    await page.getByTestId('start-default').click();
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    await shot('01-start');
    const first = await lines(page);
    checks.opens = first.length >= 2 && first[0].role === 'tutor' && first[0].kind === 'message' && first.some((l) => l.kind === 'problem');
    checks.pinnedPractice = (await page.getByTestId('open-problem').getAttribute('data-purpose')) === 'practice' && (await page.getByTestId('open-problem-label').textContent()) === 'Practice';
    checks.pinnedAboveInput = await page.evaluate(() => {
      const p = document.querySelector('[data-testid="open-problem"]').getBoundingClientRect();
      const i = document.querySelector('[data-testid="entry-input"]').getBoundingClientRect();
      const c = document.querySelector('[data-testid="conversation"]').getBoundingClientRect();
      return p.bottom <= i.top + 1 && (window.innerWidth < 640 || c.bottom <= p.top + 1);
    });
    const d0 = await dots(page);
    checks.dots0 = d0 && d0.filled === 0 && d0.total === 3 && d0.hidden === 'true' && d0.text.includes('0 of 3 check questions correct without help (one slip may pause the count)')
      && d0.text.includes('Still needed: a solve, a step choice and a new kind of equation.');
    checks.oneInput = (await page.locator('[data-testid="composer"] input[type="text"]').count()) === 1
      && JSON.stringify(await page.evaluate(() => [...document.querySelectorAll('[data-testid="composer"] .controls button')].map((b) => b.textContent.trim()))) === JSON.stringify(["I'm stuck", 'Skip this question']);
    await measure();

    // A wrong answer: the factor reached only the x. Code's result names the problem; the tutor steps back.
    const topicTitle = await page.getByTestId('topic-title').textContent();
    const wrong = xOnly();
    await send(page, wrong);
    const result = page.locator('[data-kind="result"]').last();
    checks.notYet = (await result.getAttribute('data-result')) === 'notyet' && (await result.textContent()).includes(`you answered ${wrong}.`)
      && (await page.getByTestId('result-word').last().textContent()) === 'Not yet.';
    checks.steppedBack = (await page.getByTestId('topic-title').textContent()) === topicTitle && (await page.getByTestId('focus-line').textContent()) === 'Working on: Apply the distributive property with positive factors';
    checks.inputCleared = (await page.getByTestId('entry-input').inputValue()) === '';
    await shot('02-step-back');
    await measure();

    // Correct practice, then a check question in its own card, in the conversation and pinned.
    await answerCorrectly(page);
    checks.resultCorrect = (await page.locator('[data-kind="result"]').last().getAttribute('data-result')) === 'correct'
      && (await page.locator('[data-kind="result"] .result-icon').last().textContent()) === '✓';
    checks.checkCard = (await page.locator('[data-testid="conversation"] [data-testid="check-card"]').count()) >= 1
      && (await page.locator('[data-testid="conversation"] [data-testid="check-card"] .card-label').last().textContent()) === 'Check question: on your own'
      && (await page.getByTestId('open-problem').getAttribute('data-purpose')) === 'check'
      && (await page.getByTestId('open-problem-label').textContent()) === 'Check question: on your own';
    await shot('03-check-card');

    // While a check question is open, a message is not sent to the tutor.
    const callsBefore = oracle().calls;
    await send(page, 'can you explain this to me first?');
    const afterPause = await lines(page);
    checks.pause = afterPause[afterPause.length - 1].kind === 'notice' && afterPause[afterPause.length - 1].text === "Let's finish this check question first. Answer it, or tap I'm stuck."
      && oracle().calls === callsBefore && (await page.getByTestId('open-problem').getAttribute('data-purpose')) === 'check';
    await shot('04-conversation-paused');

    // "I'm stuck": three choices; a hint makes the answer one with help.
    await page.getByTestId('stuck').click();
    checks.stuckMenu = JSON.stringify(await menuLabels(page)) === JSON.stringify(['Give me a hint', 'Explain the idea', "I haven't learned this yet"]);
    await page.getByTestId('hint').click();
    await idle(page);
    checks.withHelp = (await page.getByTestId('with-help').count()) === 1 && (await page.getByTestId('goal').textContent()).includes('You had help on this one');
    await answerCorrectly(page);
    checks.helpResets = (await dots(page)).filled === 0;
    await measure();

    // Keep answering until the tutor wraps up; the dots fill from code's count.
    let filledSeen = 0;
    for (let i = 0; i < 24 && !(await page.getByTestId('wrap-up').count()); i++) {
      await answerCorrectly(page);
      const d = await dots(page);
      if (d) filledSeen = Math.max(filledSeen, d.filled);
      await measure();
    }
    checks.wrapUp = (await page.getByTestId('wrap-up').count()) === 1 && filledSeen === 3;
    // Wide: the conversation scrolls in its own box, at its newest line. Narrow: the page scrolls and the newest line sits above the composer.
    checks.newestAtBottom = await page.evaluate((wide) => {
      const c = document.querySelector('[data-testid="conversation"]');
      if (wide) return c.scrollHeight > c.clientHeight && Math.abs(c.scrollTop + c.clientHeight - c.scrollHeight) <= 2;
      const last = c.lastElementChild.getBoundingClientRect();
      const composer = document.querySelector('[data-testid="composer"]');
      const limit = composer ? composer.getBoundingClientRect().top : window.innerHeight;
      return getComputedStyle(composer).position === 'sticky' && last.bottom <= limit + 2 && last.bottom > 0;
    }, w >= 640);
    await shot('05-wrap-up');
    const conversationText = await page.getByTestId('conversation').textContent();
    checks.noInternalInConversation = (conversationText.match(INTERNAL) || []).length === 0 && !/master/i.test(conversationText);

    await page.getByTestId('finish').click();
    await page.getByTestId('report').waitFor();
    await idle(page);
    const learnerText = await page.getByTestId('learner-summary').textContent();
    checks.summary = JSON.stringify(await page.locator('[data-testid="learner-summary"] h2').allTextContents()) === JSON.stringify(['What you did well', 'Where you had trouble', 'What we worked on', 'What to work on next time'])
      && learnerText.includes('goal met: 3 correct without help (one slip may pause the count)') && !/\b[A-Z]\d{2}\b/.test(learnerText)
      && (await page.getByTestId('report-provider').textContent()) === 'MOCK (simulated tutor; no live provider was used)';
    await page.getByTestId('teacher-toggle').click();
    const reportText = await page.getByTestId('report').textContent();
    checks.teacher = reportText.includes('Before check questions (audit)') && (reportText.match(INTERNAL) || []).length === 0 && !/master/i.test(reportText);
    await measure();
    await shot('06-summary');
    const pass = Object.values(checks).every(Boolean) && maxOverflow <= 0 && errors.length === 0;
    record(`Conversation journey: step back, check card, pause, help, wrap-up, summary (${label} ${w}x${h})`, pass, { checks, maxOverflow, consoleErrors: errors });
    await ctx.close();
  }

  // ------------------------------------------------ the slip pause (§5.5)
  for (const [w, h, label] of [[390, 844, 'mobile'], [1280, 800, 'desktop']]) {
    resetDb();
    const { ctx, page, errors } = await newPage(browser, w, h);
    const checks = {};
    await page.goto(base);
    await page.getByTestId('choose-skill').click();
    await page.getByTestId('start-skill-E06').click();
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    await answerCorrectly(page); // practice; the tutor follows with a check question
    // The owner's case: after one check answered correctly, the tutor poses 7(x + 5); the learner answers 7x + 25.
    fs.writeFileSync(faultFile, JSON.stringify([{ type: 'ENVELOPE', set: { message_type: 'QUESTION', student_message: 'Good.', item: {
      purpose: 'check', skill_id: 'E06', source: 'spec', recipe_ref: null, spec: { kind: 'expand', expression: '7(x + 5)' }, prompt: '', stated_answer: '7x + 35' } } }]));
    await answerCorrectly(page); // the first check question; the tutor follows with 7(x + 5)
    checks.posed = oracle().open.purpose === 'CHECK' && oracle().open.prompt.includes('7(x + 5)');
    const slip = '7x + 25';
    await send(page, slip);
    const after = await lines(page);
    checks.notYet = after.some((l) => l.kind === 'result' && l.text.includes('Not yet.') && l.text.includes(slip));
    checks.micro = oracle().open.purpose === 'PRACTICE' && oracle().open.prompt === 'Wait, what is 7 × 5?';
    const d = await dots(page);
    checks.paused = d.text.includes('Paused after a small slip: the count was kept.') && d.filled === 1;
    await page.screenshot({ path: path.join(outDir, `07-slip-pause-${w}.png`), fullPage: true });
    await answerCorrectly(page); // the micro-question
    checks.retry = (await page.getByTestId('open-problem').getAttribute('data-purpose')) === 'retry'
      && (await page.getByTestId('open-problem-label').textContent()) === 'Try it again (this one does not count toward the goal)';
    await answerCorrectly(page); // the retry
    checks.countKept = (await dots(page)).filled === 1 && oracle().open && oracle().open.purpose === 'CHECK';
    const pass = Object.values(checks).every(Boolean) && (await overflow(page)) <= 0 && errors.length === 0;
    record(`Slip pause: micro-question, retry, count paused (${label} ${w}x${h})`, pass, { checks, consoleErrors: errors });
    await ctx.close();
  }

  // ------------------------------------------------- UI-01 mobile keyboard
  {
    resetDb();
    const { ctx, page, errors } = await newPage(browser, 360, 740);
    await page.goto(base);
    await page.getByTestId('start-default').waitFor();
    let reached = false;
    for (let i = 0; i < 12 && !reached; i++) {
      await page.keyboard.press('Tab');
      reached = await page.evaluate(() => document.activeElement?.getAttribute('data-testid') === 'start-default');
    }
    await page.keyboard.press('Enter');
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    await page.waitForFunction(() => document.activeElement?.getAttribute('data-testid') === 'entry-input', null, { timeout: 2000 }).catch(() => {});
    const focusOnInput = await page.evaluate(() => document.activeElement?.getAttribute('data-testid'));
    // An ambiguous entry is asked about again, never marked wrong.
    await page.keyboard.type('1 1/2');
    await page.keyboard.press('Enter');
    await idle(page);
    const ambiguous = (await lines(page)).pop();
    const notMarked = !(await lines(page)).some((l) => l.kind === 'result');
    // No lost answers: a failed connection keeps the typed text.
    let aborts = 0;
    await page.route('**/api.php?action=event', (route) => { if (aborts < 2) { aborts++; return route.abort(); } return route.continue(); });
    const typed = correct();
    await page.getByTestId('entry-input').fill(typed);
    await page.getByTestId('send').click();
    await idle(page);
    const keptAfterFailure = await page.getByTestId('entry-input').inputValue();
    const banner = await page.getByTestId('error-banner').textContent().catch(() => '');
    await page.unroute('**/api.php?action=event');
    await page.getByTestId('entry-input').focus();
    await page.keyboard.press('Enter');
    await idle(page);
    const resultShown = (await page.locator('[data-kind="result"][data-result="correct"]').count()) === 1;
    const statusRegion = await page.evaluate(() => { const s = document.querySelector('[role="status"][aria-live]'); return s ? s.getAttribute('aria-live') : null; });
    const logRegion = await page.evaluate(() => { const l = document.querySelector('[data-testid="conversation"]'); return l ? [l.getAttribute('role'), l.getAttribute('aria-live'), l.getAttribute('tabindex')].join() : null; });
    const labelled = await page.evaluate(() => [...document.querySelectorAll('input, textarea')].every((el) => (el.labels && el.labels.length > 0) || el.getAttribute('aria-label')));
    const focusVisible = await page.evaluate(() => { const b = document.querySelector('[data-testid="stuck"]'); b.focus(); const s = getComputedStyle(b); return s.outlineStyle !== 'none' || s.boxShadow !== 'none'; });
    const ov = await overflow(page);
    await page.screenshot({ path: path.join(outDir, 'ui01-mobile-keyboard-360.png'), fullPage: true });
    const failedLoads = errors.filter((e) => !e.includes('net::ERR_FAILED') && !e.includes('Failed to fetch'));
    const pass = reached && focusOnInput === 'entry-input' && ambiguous.kind === 'notice' && ambiguous.text.includes('can be read more than one way') && notMarked
      && keptAfterFailure === typed && banner.includes('Your typed text is kept') && resultShown && statusRegion === 'polite' && logRegion === 'log,polite,0'
      && labelled && focusVisible && ov <= 0 && failedLoads.length === 0;
    record('UI-01 mobile keyboard, status and no lost answers (360x740)', pass, { reached, focusOnInput, ambiguous, notMarked, keptAfterFailure, banner, resultShown, statusRegion, logRegion, labelled, focusVisible, overflow: ov, consoleErrors: errors });
    await ctx.close();
  }

  // ------------------------------------------------- UI-02 safe rendering
  {
    resetDb();
    const { ctx, page, errors, dialogs } = await newPage(browser, 1280, 800);
    await page.goto(base);
    await page.getByTestId('start-default').click();
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    // Markers carry no digits, so the answer guards cannot reject the hostile text by chance.
    const hostile = '<img src=x onerror="window.__xss=\'img\'"><script>window.__xss=\'script\'</script><a href="javascript:window.__xss=\'link\'">link</a> \\href{javascript:alert(\'a\')}{click} $\\unicode{x41}$';
    await send(page, '<b onmouseover="window.__xss=4">7</b>');
    fs.writeFileSync(faultFile, JSON.stringify([{ type: 'TEXT', text: hostile }]));
    await send(page, 'why does this work?');
    const injected = await page.evaluate(() => ({
      xss: window.__xss ?? null,
      imgs: document.querySelectorAll('#app img').length,
      scripts: document.querySelectorAll('#app script').length,
      anchorsJs: [...document.querySelectorAll('a')].filter((a) => a.href.startsWith('javascript:')).length,
      bold: document.querySelectorAll('#app b').length,
    }));
    const text = await page.getByTestId('conversation').textContent();
    const shownAsText = text.includes('<script>') && text.includes('<b onmouseover');
    const pass = injected.xss === null && injected.imgs === 0 && injected.scripts === 0 && injected.anchorsJs === 0 && injected.bold === 0 && shownAsText && dialogs.length === 0 && errors.length === 0;
    record('UI-02 safe rendering of tutor and learner text', pass, { injected, shownAsText, dialogs, consoleErrors: errors });
    await ctx.close();
  }

  // ------------------------------------------------- UI-03 skill map
  {
    const { ctx, page, errors } = await newPage(browser, 390, 844);
    await page.goto(base);
    await page.getByTestId('browse-skills').click();
    const count = async () => Number((await page.locator('.count').textContent()).split(' ')[0]);
    const ids = async () => page.locator('.catalog-row .skill-id').allTextContents();
    const labels = async () => page.locator('.catalog-row .tag[class*="avail-"]').allTextContents();
    const toggle = page.getByTestId('toggle-full-map');
    const defaultView = { count: await count(), ids: (await ids()).sort(), pressed: await toggle.getAttribute('aria-pressed'), text: (await toggle.textContent()).trim() };
    await toggle.click();
    const fullView = { count: await count(), pressed: await page.getByTestId('toggle-full-map').getAttribute('aria-pressed'), labels: [...new Set(await labels())].sort() };
    const ov = await overflow(page);
    await page.screenshot({ path: path.join(outDir, 'ui03-full-map-390.png') });
    await page.getByTestId('toggle-full-map').click();
    const back = await count();
    const beta = ['E06', 'N03', 'N04', 'N05', 'N06', 'N18', 'Q04', 'Q05', 'Q06', 'Q07'];
    const pass = defaultView.count === 10 && JSON.stringify(defaultView.ids) === JSON.stringify(beta) && defaultView.pressed === 'false' && defaultView.text === 'Show full map'
      && fullView.count === 205 && fullView.pressed === 'true' && fullView.labels.length === 3 && back === 10 && ov <= 0 && errors.length === 0;
    record('UI-03 skill map shows the 10 tutor skills by default; the full 205-skill map on request', pass, { defaultView, fullView, back, overflow: ov, consoleErrors: errors });
    await ctx.close();
  }

  // ------------------------------------------------- UI-06 contrast
  {
    const { ctx, page, errors } = await newPage(browser, 390, 844);
    await page.goto(base);
    await page.getByTestId('start-default').waitFor();
    const out = {};
    for (const scheme of ['light', 'dark']) {
      await page.emulateMedia({ colorScheme: scheme });
      out[scheme] = await page.evaluate(() => {
        const rgb = (c) => c.match(/[\d.]+/g).slice(0, 3).map(Number);
        const lum = (c) => { const [r, g, b] = rgb(c).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
        const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
        const res = {};
        const ol = document.createElement('ol');
        ol.className = 'conversation';
        document.getElementById('app').append(ol);
        for (const kind of ['correct', 'notyet']) {
          const li = document.createElement('li');
          li.className = 'line line-result result-' + kind;
          li.innerHTML = '<p class="result-head"><span class="result-icon">✓</span><span class="result-word">x</span></p><p class="result-note">body</p>';
          ol.append(li);
          const bg = getComputedStyle(li).backgroundColor;
          const icon = li.querySelector('.result-icon');
          res[kind] = {
            word: ratio(getComputedStyle(li.querySelector('.result-word')).color, bg),
            body: ratio(getComputedStyle(li.querySelector('.result-note')).color, bg),
            icon: kind === 'correct' ? ratio(getComputedStyle(icon).color, getComputedStyle(icon).backgroundColor) : ratio(getComputedStyle(icon).borderTopColor, bg),
            bg,
          };
        }
        const card = document.createElement('li');
        card.className = 'line line-check';
        card.innerHTML = '<div class="check-card"><p class="card-label">Check question: on your own</p><p class="problem-text">Solve</p></div>';
        ol.append(card);
        const c = card.querySelector('.check-card');
        res.check = { label: ratio(getComputedStyle(c.querySelector('.card-label')).color, getComputedStyle(c).backgroundColor), text: ratio(getComputedStyle(c.querySelector('.problem-text')).color, getComputedStyle(c).backgroundColor), border: ratio(getComputedStyle(c).borderTopColor, getComputedStyle(document.body).backgroundColor) };
        ol.remove();
        return res;
      });
    }
    const ok = (r) => r.correct.word >= 4.5 && r.correct.body >= 4.5 && r.notyet.word >= 4.5 && r.notyet.body >= 4.5 && r.correct.icon >= 3 && r.notyet.icon >= 3 && r.correct.bg !== r.notyet.bg
      && r.check.label >= 4.5 && r.check.text >= 4.5 && r.check.border >= 3;
    const pass = ok(out.light) && ok(out.dark) && errors.length === 0;
    record('UI-06 result blocks and the check card meet WCAG AA contrast in light and dark mode', pass, { out, consoleErrors: errors });
    await ctx.close();
  }

  // UI-07: the code during a session, "Welcome back" on another device, the take-over
  // message on the first device, and the downloaded report opened in a browser.
  for (const [w, h, label] of [[390, 844, 'mobile'], [1280, 800, 'desktop']]) {
    resetDb();
    const A = await newPage(browser, w, h);
    const B = await newPage(browser, w, h);
    const shotOf = (page, name) => page.screenshot({ path: path.join(outDir, `${name}-${w}.png`), fullPage: true });
    const checks = {};
    const page = A.page;
    await page.goto(base);
    await page.getByTestId('start-default').click();
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    checks.noCodeBeforeAnswer = (await page.getByTestId('your-code').count()) === 0;
    await send(page, xOnly());
    await page.getByTestId('your-code').waitFor();
    const code = (await page.getByTestId('code-value').textContent()).trim();
    checks.codeShownAfterFirstAnswer = /^[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/.test(code)
      && (await page.getByTestId('your-code').textContent()).includes('Write this down or download your report to continue next time.');
    await answerCorrectly(page);
    checks.stillSameCode = (await page.getByTestId('code-value').textContent()).trim() === code;
    await shotOf(page, '08-code-during-session');
    const promptOnA = await page.getByTestId('prompt').textContent();

    // Another device: the code typed in lower case without dashes.
    const pb = B.page;
    await pb.goto(base);
    await pb.getByTestId('code-input').fill(code.replace(/-/g, '').toLowerCase());
    await pb.getByTestId('code-continue').click();
    await pb.getByTestId('welcome-back').waitFor();
    const wbHeadings = await pb.locator('[data-testid="welcome-back"] h3').allTextContents();
    checks.welcomeBack = JSON.stringify(wbHeadings) === JSON.stringify(['What you did well', 'Where you had trouble', 'What we worked on', 'What to work on next time'])
      && (await pb.getByTestId('code-start').textContent()).includes('continue where you stopped');
    await shotOf(pb, '09-welcome-back');
    await pb.getByTestId('code-start').click();
    await pb.getByTestId('workspace').waitFor();
    await idle(pb);
    checks.continuedExactly = (await pb.getByTestId('prompt').textContent()) === promptOnA && (await pb.getByTestId('code-value').textContent()).trim() === code
      && (await pb.locator('[data-testid="line"]').count()) > 2;

    // The first device: its next entry is refused, and it says why.
    await page.getByTestId('entry-input').fill('1');
    await page.getByTestId('send').click();
    await page.getByTestId('moved').waitFor();
    checks.movedMessage = (await page.getByTestId('moved').textContent()).includes('This progress continued on another device.') && (await page.getByTestId('entry-input').count()) === 0;
    await page.reload();
    await page.getByTestId('moved').waitFor();
    checks.movedAfterReload = (await page.getByTestId('moved').textContent()).includes('This progress continued on another device.');
    await shotOf(page, '10-continued-on-another-device');

    // The downloaded report, opened in a browser.
    await pb.getByTestId('end-session').click(); // the confirmation is accepted
    await pb.getByTestId('report').waitFor();
    await idle(pb);
    const [download] = await Promise.all([pb.waitForEvent('download'), pb.getByTestId('download-report').click()]);
    const file = path.join(tmp, `report-${w}.html`);
    await download.saveAs(file);
    const html = fs.readFileSync(file, 'utf8');
    checks.downloadName = /^algebra-report-\d{4}-\d{2}-\d{2}\.html$/.test(download.suggestedFilename());
    checks.downloadSelfContained = html.includes('<style>') && !/<script|https?:\/\/|<link|<img|url\(/i.test(html) && html.includes(code) && !html.includes('<details');
    const viewer = await B.ctx.newPage();
    const requests = [];
    viewer.on('request', (r) => { if (!r.url().startsWith('file:')) requests.push(r.url()); });
    await viewer.goto('file://' + file);
    const viewerText = await viewer.locator('body').textContent();
    checks.downloadOpens = ['What you did well', 'Where you had trouble', 'What we worked on', 'What to work on next time', 'Details for teachers', 'Your code'].every((t) => viewerText.includes(t)) && requests.length === 0;
    checks.downloadNoOverflow = await viewer.evaluate(() => document.documentElement.scrollWidth - window.innerWidth) <= 0;
    await viewer.screenshot({ path: path.join(outDir, `11-downloaded-report-${w}.png`), fullPage: true });
    // The refused entry on the first device is an expected 409, which the browser logs as a failed load.
    const unexpected = [...A.errors.filter((e) => !e.includes('status of 409')), ...B.errors];
    const pass = Object.values(checks).every(Boolean) && unexpected.length === 0 && A.errors.length <= 1;
    record(`UI-07 progress code, welcome back, take-over and downloaded report (${label} ${w}x${h})`, pass, { checks, consoleErrors: [...A.errors, ...B.errors] });
    await A.ctx.close();
    await B.ctx.close();
  }
} finally {
  await browser.close();
  server.kill();
}
fs.writeFileSync(path.join(outDir, 'browser-results.json'), JSON.stringify({ ran_at: new Date().toISOString(), results }, null, 2));
const failed = results.filter((r) => !r.pass).length;
console.log(`\n${results.length - failed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
