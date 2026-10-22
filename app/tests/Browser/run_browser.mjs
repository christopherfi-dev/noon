// Browser tests (mock mode): B1 end to end at 360x740 and 1280x800, UI-01
// (mobile keyboard and screen-reader status), UI-02 (safe rendering), UI-03
// (skill map) and the round UI checks UI-04 to UI-06 at 390 and 1280 wide.
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

async function freePort() {
  return new Promise((resolve) => { const s = net.createServer(); s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => resolve(p)); }); });
}
const port = await freePort();
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', path.join(appRoot, 'public')], { env: { ...process.env, ALGEBRA_CONFIG: path.join(tmp, 'config.php') }, stdio: 'ignore' });
await new Promise((r) => setTimeout(r, 700));
const base = `http://127.0.0.1:${port}/`;

async function idle(page) {
  await page.waitForFunction(() => document.getElementById('app')?.getAttribute('aria-busy') === 'false', null, { timeout: 15000 });
}
async function overflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
}
async function answer(page, value) {
  const o = oracle();
  if (o.active.type === 'classification') {
    for (const L of (Array.isArray(value) ? value : String(value).split(','))) await page.getByTestId('option-' + L).check();
  } else {
    await page.getByTestId('answer-input').fill(String(value));
  }
  await page.getByTestId('submit').click();
  await idle(page);
}
const correct = () => { const a = oracle().active; return Array.isArray(a.expected) ? a.expected : a.expected; };
const plainWrong = () => { const a = oracle().active; return a.type === 'classification' ? (a.expected.length === 1 && a.expected[0] === 'A' ? ['B'] : ['A']) : a.type === 'expression' ? 'x+997' : String(Number(a.expected) + 37); };

async function newPage(browser, w, h) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h } });
  const page = await ctx.newPage();
  const errors = [];
  const dialogs = [];
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('dialog', async (d) => { dialogs.push(d.message()); await d.dismiss(); });
  return { ctx, page, errors, dialogs };
}

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
try {
  // ------------------------------------------------------------ B1 in a browser
  for (const [w, h, label] of [[360, 740, 'mobile'], [1280, 800, 'desktop']]) {
    const { ctx, page, errors } = await newPage(browser, w, h);
    const steps = [];
    let maxOverflow = 0;
    const note = async (what) => { const o = oracle(); steps.push({ what, mode: o.mode, skill: o.skill }); maxOverflow = Math.max(maxOverflow, await overflow(page)); };
    await page.goto(base);
    await page.getByTestId('start-default').click();
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    await note('start');
    await answer(page, oracle().active.signatures.DIST_X_ONLY); await note('Q07 wrong (distribution signature)');
    await answer(page, oracle().active.signatures.DIST_X_ONLY); await note('probe wrong (same signature)');
    const p = oracle().active.params;
    await answer(page, `${p.a}x+${p.b}`); await note('E06 check wrong (factor on first term only)');
    await page.screenshot({ path: path.join(outDir, `b1-${label}-guided-repair.png`), fullPage: true });
    const guidedHeading = (await page.getByTestId('workspace').textContent()).includes('Step by step');
    // Round UI (U1): the feedback before the first guided problem names the missed problem and the answer.
    const namesMissed = (await page.getByTestId('feedback').textContent()).includes(`On ${p.a}(x + ${p.b}) you answered ${p.a}x+${p.b}.`);
    await page.getByTestId('stuck').click();
    const dontKnow = await page.getByTestId('not-learned').textContent();
    await page.getByTestId('stuck').click();
    await answer(page, plainWrong()); await note('guided step wrong → smaller question');
    const smallerPrompt = await page.getByTestId('prompt').textContent();
    let guard = 0;
    while (oracle().mode === 'GUIDED_REPAIR' && guard++ < 40) { await answer(page, correct()); }
    await note('guided repair complete');
    for (let i = 0; i < 3; i++) { await answer(page, correct()); }
    await note('E06 three variants');
    for (let i = 0; i < 3; i++) { await answer(page, correct()); }
    await note('Q07 three variants');
    const finalMode = oracle().mode;
    await page.getByTestId('view-report').click();
    await page.getByTestId('report').waitFor();
    const reportText = await page.getByTestId('report').textContent();
    // Round 5.1 ruling 4: the summary page uses plain words (R5-B list, plurals included).
    const internalInSummary = reportText.match(/\b(expected values?|expected answers?|answer keys?|matched the key|guided steps?|hosts?|assisted|verdicts?|criterion|criteria)\b/gi) || [];
    maxOverflow = Math.max(maxOverflow, await overflow(page));
    await page.screenshot({ path: path.join(outDir, `b1-${label}-report.png`), fullPage: true });
    const expected = ['TARGET_ASSESSMENT', 'DIAGNOSTIC_PROBE', 'PREREQUISITE_CHECK', 'GUIDED_REPAIR', 'GUIDED_REPAIR', 'INDEPENDENT_CHECK', 'RETURN_TO_TARGET', 'SESSION_REPORT'];
    const modes = steps.map((s) => s.mode);
    const pass = JSON.stringify(modes) === JSON.stringify(expected) && finalMode === 'SESSION_REPORT' && guidedHeading && namesMissed && dontKnow.includes("I don't know")
      && reportText.includes('MOCK') && reportText.includes('goal met: 3 correct in a row without help') && !/master/i.test(reportText) && internalInSummary.length === 0 && maxOverflow <= 0 && errors.length === 0;
    record(`B1 browser journey (${label} ${w}x${h})`, pass, { modes, namesMissed, maxOverflow, internalInSummary, consoleErrors: errors, smallerPrompt: smallerPrompt.slice(0, 120) });
    await ctx.close();
    execFileSync('php', ['-r', `$p=new PDO('sqlite:${dbPath}'); foreach(['sessions','events','items','checker_results','operations','idempotency','reports','containers','counters','model_calls'] as $t) $p->exec("DELETE FROM $t");`]);
  }

  // ------------------------------------------------- UI-01 mobile keyboard
  {
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
    const focusOnInput = await page.evaluate(() => document.activeElement?.getAttribute('data-testid'));
    await page.keyboard.type('1 1/2');
    await page.keyboard.press('Enter');
    await idle(page);
    const keptAfterError = await page.getByTestId('answer-input').inputValue();
    const statusRegion = await page.evaluate(() => { const s = document.querySelector('[role="status"][aria-live]'); return s ? s.getAttribute('aria-live') : null; });
    const logRegion = await page.evaluate(() => { const l = document.querySelector('[data-testid="messages"]'); return l ? (l.getAttribute('aria-live') || l.getAttribute('role')) : null; });
    const labelled = await page.evaluate(() => [...document.querySelectorAll('input, textarea')].every((el) => el.labels && el.labels.length > 0 || el.getAttribute('aria-label')));
    await page.getByTestId('answer-input').fill('');
    await page.getByTestId('answer-input').focus();
    await page.keyboard.type(String(correct()));
    await page.keyboard.press('Enter');
    await idle(page);
    const msgs = await page.getByTestId('messages').textContent();
    const focusVisible = await page.evaluate(() => { const b = document.querySelector('[data-testid="stuck"]'); b.focus(); const s = getComputedStyle(b); return s.outlineStyle !== 'none' || s.boxShadow !== 'none'; });
    const ov = await overflow(page);
    await page.screenshot({ path: path.join(outDir, 'ui01-mobile-keyboard.png'), fullPage: true });
    const pass = reached && focusOnInput === 'answer-input' && keptAfterError === '1 1/2' && statusRegion === 'polite' && !!logRegion && labelled && msgs.includes('Correct.') && focusVisible && ov <= 0 && errors.length === 0;
    record('UI-01 mobile keyboard and status (360x740)', pass, { reached, focusOnInput, keptAfterError, statusRegion, logRegion, labelled, focusVisible, overflow: ov, consoleErrors: errors });
    await ctx.close();
  }

  // ------------------------------------------------- UI-03 skill map default (Round 2 §3)
  {
    const { ctx, page, errors } = await newPage(browser, 360, 740);
    await page.goto(base);
    await page.getByTestId('browse-skills').click();
    const count = async () => Number((await page.locator('.count').textContent()).split(' ')[0]);
    const ids = async () => page.locator('.catalog-row .skill-id').allTextContents();
    const labels = async () => page.locator('.catalog-row .tag[class*="avail-"]').allTextContents();
    const toggle = page.getByTestId('toggle-full-map');
    const defaultView = { count: await count(), ids: (await ids()).sort(), pressed: await toggle.getAttribute('aria-pressed'), text: (await toggle.textContent()).trim(), labels: [...new Set(await labels())] };
    await toggle.click();
    const fullView = { count: await count(), pressed: await page.getByTestId('toggle-full-map').getAttribute('aria-pressed'), labels: [...new Set(await labels())].sort() };
    const ov = await overflow(page);
    await page.screenshot({ path: path.join(outDir, 'ui03-full-map-mobile.png') });
    await page.getByTestId('toggle-full-map').click();
    const back = await count();
    const beta = ['E06', 'N03', 'N04', 'N05', 'N06', 'N18', 'Q04', 'Q05', 'Q06', 'Q07'];
    const pass = defaultView.count === 10 && JSON.stringify(defaultView.ids) === JSON.stringify(beta) && defaultView.pressed === 'false' && defaultView.text === 'Show full map'
      && fullView.count === 205 && fullView.pressed === 'true' && fullView.labels.length === 3 && back === 10 && ov <= 0 && errors.length === 0;
    record('UI-03 skill map shows the 10 beta skills by default; full map on request', pass, { defaultView, fullView, back, overflow: ov, consoleErrors: errors });
    await ctx.close();
  }

  // ------------------------------------------------- UI-02 safe rendering
  {
    const { ctx, page, errors, dialogs } = await newPage(browser, 1280, 800);
    await page.goto(base);
    await page.getByTestId('start-default').click();
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    // Markers carry no digits, so the answer-disclosure guard cannot reject the hostile text by chance.
    const hostile = '<img src=x onerror="window.__xss=\'img\'"><script>window.__xss=\'script\'</script><a href="javascript:window.__xss=\'link\'">link</a> \\href{javascript:alert(\'a\')}{click} $\\unicode{x41}$';
    await answer(page, '<b onmouseover="window.__xss=4">7</b>');
    // Model text reaches the learner in a hint (an independent item's framing is host-only since round 5).
    fs.writeFileSync(faultFile, JSON.stringify([{ type: 'STUDENT_MESSAGE', text: hostile }]));
    await page.getByTestId('stuck').click();
    await page.getByTestId('hint').click();
    await idle(page);
    const injected = await page.evaluate(() => ({
      xss: window.__xss ?? null,
      imgs: document.querySelectorAll('#app img').length,
      scripts: document.querySelectorAll('#app script').length,
      anchorsJs: [...document.querySelectorAll('a')].filter((a) => a.href.startsWith('javascript:')).length,
      bold: document.querySelectorAll('#app b').length,
    }));
    const shownAsText = (await page.getByTestId('messages').textContent()).includes('<script>');
    const pass = injected.xss === null && injected.imgs === 0 && injected.scripts === 0 && injected.anchorsJs === 0 && injected.bold === 0 && shownAsText && dialogs.length === 0;
    record('UI-02 safe rendering of model and learner text', pass, { injected, shownAsText, dialogs, consoleErrors: errors });
    await ctx.close();
  }

  // ------------------------------------------------- Round UI (CONDUCTOR_ROUND_UI.md)
  const uiDir = path.join(outDir, 'ui-round');
  fs.mkdirSync(uiDir, { recursive: true });
  const resetDb = () => execFileSync('php', ['-r', `$p=new PDO('sqlite:${dbPath}'); foreach(['sessions','events','items','checker_results','operations','idempotency','reports','containers','counters','model_calls'] as $t) $p->exec("DELETE FROM $t");`]);
  const actionsOf = (page) => { const sent = []; page.on('request', (r) => { if (r.method() === 'POST' && r.url().includes('action=event')) { try { sent.push(JSON.parse(r.postData()).action); } catch (e) { /* ignore */ } } }); return sent; };
  const controlLabels = (page) => page.evaluate(() => [...document.querySelectorAll('.answer-form .controls button')].filter((b) => b.offsetParent !== null).map((b) => b.textContent.trim()));
  const menuLabels = (page) => page.evaluate(() => { const m = document.querySelector('[data-testid="stuck-menu"]'); return m && !m.hidden ? [...m.querySelectorAll('button')].map((b) => b.textContent.trim()) : []; });
  const dots = (page) => page.evaluate(() => { const d = document.querySelector('[data-testid="goal-dots"]'); return d ? { filled: d.querySelectorAll('.dot-filled').length, total: d.querySelectorAll('.dot').length, hidden: d.getAttribute('aria-hidden'), text: document.querySelector('[data-testid="goal"]').textContent } : null; });
  // U2: a word broken across lines inside a table cell has more than one line box.
  const brokenWords = (page) => page.evaluate(() => {
    const out = [];
    for (const cell of document.querySelectorAll('.skills-table th, .skills-table td, .skills-table caption')) {
      const walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT);
      for (let n = walker.nextNode(); n; n = walker.nextNode()) {
        for (const m of n.textContent.matchAll(/\S+/g)) {
          const r = document.createRange(); r.setStart(n, m.index); r.setEnd(n, m.index + m[0].length);
          const lines = new Set([...r.getClientRects()].map((x) => Math.round(x.top)));
          if (lines.size > 1) out.push(m[0]);
        }
      }
    }
    return out;
  });

  // UI-04: the round UI journey with its screenshots (U1, U2, U3, U4, U5, U6, U7, U8).
  for (const [w, h, label] of [[390, 844, 'mobile'], [1280, 800, 'desktop']]) {
    resetDb();
    const { ctx, page, errors } = await newPage(browser, w, h);
    const sent = actionsOf(page);
    const shot = (name) => page.screenshot({ path: path.join(uiDir, `${name}-${w}.png`), fullPage: true });
    const checks = {};
    await page.goto(base);
    await page.getByTestId('start-default').waitFor();
    await shot('01-welcome');
    checks.u4Welcome = !/working/i.test(await page.locator('body').textContent()) && (await page.locator('textarea').count()) === 0;

    await page.getByTestId('start-default').click();
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    await shot('02-independent-scalar-item');
    await shot('06-progress-dots-0');
    const d0 = await dots(page);
    checks.u7Dots0 = d0 && d0.filled === 0 && d0.total === 3 && d0.hidden === 'true' && d0.text.includes('0 of 3 in a row without help') && d0.text.includes('Still needed: a solve, a step choice and a new kind of equation.');
    checks.u5Independent = JSON.stringify(await controlLabels(page)) === JSON.stringify(['Submit', "I'm stuck", 'Skip']) && (await menuLabels(page)).length === 0;
    await page.getByTestId('stuck').click();
    checks.u5IndependentMenu = JSON.stringify(await menuLabels(page)) === JSON.stringify(['Give me a hint', 'Explain the idea', "I haven't learned this yet"])
      && (await page.getByTestId('stuck').getAttribute('aria-expanded')) === 'true';
    await page.getByTestId('hint').focus();
    await page.keyboard.press('Escape');
    checks.u5EscapeCloses = (await menuLabels(page)).length === 0 && await page.evaluate(() => document.activeElement?.getAttribute('data-testid') === 'stuck');
    checks.u4NoWorkingBox = (await page.locator('textarea').count()) === 0;

    await answer(page, correct());
    await shot('07-progress-dots-1');
    checks.u7Dots1 = (await dots(page)).filled === 1;
    const okBlock = page.getByTestId('feedback');
    checks.u8Correct = (await okBlock.getAttribute('data-result')) === 'correct' && (await okBlock.locator('.feedback-icon').textContent()) === '✓'
      && (await page.getByTestId('feedback-word').textContent()) === 'Correct.';
    const okBg = await okBlock.evaluate((e) => getComputedStyle(e).backgroundColor);

    // U3: the multiple-choice item.
    checks.u3IsChoice = oracle().active.type === 'classification';
    await shot('03-multiple-choice-item');
    const u3 = await page.evaluate(() => {
      const prompt = document.querySelector('[data-testid="prompt"]');
      const labels = [...document.querySelectorAll('[data-testid^="option-label-"]')].map((l) => l.textContent);
      const spans = [...document.querySelectorAll('.question .math')];
      return {
        stemHasOptions: /(^|\n)[A-D]\) /.test(prompt.textContent),
        labels,
        nowrap: spans.length > 0 && spans.every((s) => getComputedStyle(s).whiteSpace === 'nowrap'),
        oneLine: spans.every((s) => new Set([...s.getClientRects()].map((r) => Math.round(r.top))).size === 1),
        stemWeight: getComputedStyle(prompt).fontWeight,
        emphasised: [...prompt.querySelectorAll('.math-main')].map((s) => s.textContent),
      };
    });
    const promptText = execFileSync('php', ['-r', `$p=new PDO('sqlite:${dbPath}'); $r=$p->query('SELECT engine_json FROM sessions ORDER BY created_at DESC, rowid DESC LIMIT 1')->fetchColumn(); echo json_decode($r,true)['active']['prompt'];`]).toString();
    const optionLines = promptText.split('\n').filter((l) => /^[A-D]\) /.test(l));
    checks.u3Labels = optionLines.length === 4 && JSON.stringify(u3.labels) === JSON.stringify(optionLines);
    checks.u3Stem = !u3.stemHasOptions && u3.stemWeight === '400' && u3.emphasised.length === 1 && u3.nowrap && u3.oneLine;

    await answer(page, correct());
    await shot('08-progress-dots-2');
    checks.u7Dots2 = (await dots(page)).filled === 2 && (await dots(page)).text.includes('Still needed: a new kind of equation.');

    // A wrong answer with the distribution signature: a neutral "Not yet." block and a probe that does not count.
    const sig = oracle().active.signatures.DIST_X_ONLY;
    await answer(page, sig);
    const noBlock = page.getByTestId('feedback');
    checks.u8NotYet = (await noBlock.getAttribute('data-result')) === 'notyet' && (await page.getByTestId('feedback-word').textContent()) === 'Not yet.'
      && (await noBlock.evaluate((e) => getComputedStyle(e).backgroundColor)) !== okBg && (await noBlock.textContent()).includes(`you answered ${sig}.`);
    checks.u7ProbeNoDots = (await dots(page)) === null && (await page.getByTestId('goal-note').textContent()).includes('does not count toward the goal');
    await answer(page, oracle().active.signatures.DIST_X_ONLY);
    const p = oracle().active.params;
    checks.u7BuildingBlockDots = (await dots(page)).filled === 0 && (await page.getByTestId('progress-context').textContent()).startsWith('Building block: ');
    await answer(page, `${p.a}x+${p.b}`);

    // U1, U5, U6 in guided repair.
    checks.u1 = (await page.getByTestId('feedback').textContent()).includes(`On ${p.a}(x + ${p.b}) you answered ${p.a}x+${p.b}.`);
    await shot('04-feedback-before-first-guided-problem');
    checks.u5Guided = JSON.stringify(await controlLabels(page)) === JSON.stringify(['Submit', "I'm stuck"]);
    checks.u6 = (await page.locator('[data-testid="assisted-tag"]').count()) === 1 && !/with help|not counted/i.test(await page.getByTestId('progress').textContent());
    await page.getByTestId('stuck').click();
    checks.u5GuidedMenu = JSON.stringify(await menuLabels(page)) === JSON.stringify(['Give me a hint', "I don't know"]);
    await shot('05-guided-step-im-stuck-open');
    const before = sent.length;
    await page.getByTestId('hint').click();
    await idle(page);
    checks.u5HintAction = sent.slice(before).join() === 'REQUEST_HINT';
    let guard = 0;
    while (oracle().mode === 'GUIDED_REPAIR' && guard++ < 40) await answer(page, correct());
    for (let i = 0; i < 6 && oracle().mode !== 'SESSION_REPORT'; i++) await answer(page, correct());
    checks.reachedReport = oracle().mode === 'SESSION_REPORT';

    // U2: the two-level summary.
    await page.getByTestId('view-report').click();
    await page.getByTestId('report').waitFor();
    const details = page.getByTestId('teacher-details');
    checks.u2Collapsed = (await details.evaluate((d) => d.open)) === false;
    const learnerText = await page.getByTestId('learner-summary').textContent();
    const learnerHeadings = await page.locator('[data-testid="learner-summary"] h2').allTextContents();
    checks.u2Learner = JSON.stringify(learnerHeadings) === JSON.stringify(['What you worked on', 'Goals met', 'What was hard', 'What to practise next'])
      && !/\b[A-Z]\d{2}\b/.test(learnerText) && learnerText.includes('goal met: 3 correct in a row without help');
    await shot('09-learner-summary');
    await page.getByTestId('teacher-toggle').click();
    checks.u2Opened = await details.evaluate((d) => d.open);
    await shot('10-teacher-details-opened');
    const reportText = await page.getByTestId('report').textContent();
    const terms = reportText.match(/\b(declared prerequisites?|descents?|descended|implicated|branch(?:es)?|transfer variants?|retention claims?|faded support|coverage|expected values?|expected answers?|answer keys?|matched the key|guided steps?|hosts?|assisted|verdicts?|criterion|criteria)\b/gi) || [];
    const codes = reportText.match(/\b[A-Z]{2,}(?:_[A-Z0-9]+)+\b|\b(?:all|any)_of\b|(?<!\()\b[A-Z]\d{2}\b(?!\))/g) || [];
    checks.u2Words = terms.length === 0 && codes.length === 0 && !/master/i.test(reportText);
    const stacked = await page.evaluate(() => getComputedStyle(document.querySelector('.skills-table tbody tr')).display);
    checks.u2Table = (w < 600 ? stacked === 'block' : stacked === 'table-row') && (await brokenWords(page)).length === 0;
    const ov = await overflow(page);
    const pass = Object.values(checks).every(Boolean) && ov <= 0 && errors.length === 0;
    record(`UI-04 round UI journey (${label} ${w}x${h})`, pass, { checks, overflow: ov, brokenWords: await brokenWords(page), terms, codes, consoleErrors: errors });
    await ctx.close();
  }

  // UI-05: each help choice sends today's action; a typed stuck entry still works; the dots empty on a reset.
  for (const [w, h, label] of [[390, 844, 'mobile'], [1280, 800, 'desktop']]) {
    resetDb();
    const { ctx, page, errors } = await newPage(browser, w, h);
    const sent = actionsOf(page);
    const checks = {};
    await page.goto(base);
    await page.getByTestId('start-default').click();
    await page.getByTestId('workspace').waitFor();
    await idle(page);
    await answer(page, correct());
    checks.oneDot = (await dots(page)).filled === 1;
    await answer(page, plainWrong());
    checks.probe = oracle().mode === 'DIAGNOSTIC_PROBE';
    await answer(page, correct());
    const d = await dots(page);
    checks.reset = oracle().mode === 'TARGET_ASSESSMENT' && d.filled === 0 && d.text.includes('0 of 3 in a row without help');
    const pick = async (testid) => { const n = sent.length; await page.getByTestId('stuck').click(); await page.getByTestId(testid).click(); await idle(page); return sent.slice(n).join(); };
    checks.hint = (await pick('hint')) === 'REQUEST_HINT' && (await page.getByTestId('goal').textContent()).includes('You had help on this one');
    checks.explain = (await pick('explain')) === 'REQUEST_EXPLANATION';
    const n = sent.length;
    await page.getByTestId('answer-input').fill("I'm stuck");
    await page.getByTestId('submit').click();
    await idle(page);
    const msgs = await page.getByTestId('messages').textContent();
    checks.typedStuck = sent.slice(n).join() === 'SUBMIT_RESPONSE' && !msgs.includes('Not yet.') && msgs.length > 0;
    if (await page.getByTestId('stuck').count()) {
      checks.notLearned = (await pick('not-learned')) === 'NOT_LEARNED';
    } else {
      checks.notLearned = false;
    }
    const pass = Object.values(checks).every(Boolean) && errors.length === 0;
    record(`UI-05 help choices, typed stuck entry and streak reset (${label} ${w}x${h})`, pass, { checks, sent, consoleErrors: errors });
    await ctx.close();
  }

  // UI-06: result blocks meet WCAG AA in light and dark mode; icon and words always accompany the colour.
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
        for (const kind of ['correct', 'notyet']) {
          const li = document.createElement('li');
          li.className = 'msg feedback feedback-' + kind;
          li.innerHTML = '<p class="feedback-head"><span class="feedback-icon">✓</span><span class="feedback-word">x</span></p><p>body</p>';
          document.getElementById('app').append(li);
          const bg = getComputedStyle(li).backgroundColor;
          const icon = li.querySelector('.feedback-icon');
          res[kind] = {
            word: ratio(getComputedStyle(li.querySelector('.feedback-word')).color, bg),
            body: ratio(getComputedStyle(li.querySelector('p + p')).color, bg),
            icon: kind === 'correct' ? ratio(getComputedStyle(icon).color, getComputedStyle(icon).backgroundColor) : ratio(getComputedStyle(icon).borderTopColor, bg),
            bg,
          };
          li.remove();
        }
        return res;
      });
    }
    const ok = (r) => r.correct.word >= 4.5 && r.correct.body >= 4.5 && r.notyet.word >= 4.5 && r.notyet.body >= 4.5 && r.correct.icon >= 3 && r.notyet.icon >= 3 && r.correct.bg !== r.notyet.bg;
    const pass = ok(out.light) && ok(out.dark) && errors.length === 0;
    record('UI-06 result blocks meet WCAG AA contrast in light and dark mode', pass, { out, consoleErrors: errors });
    await ctx.close();
  }
} finally {
  await browser.close();
  server.kill();
}
fs.writeFileSync(path.join(outDir, 'browser-results.json'), JSON.stringify({ ran_at: new Date().toISOString(), results }, null, 2));
const failed = results.filter((r) => !r.pass).length;
console.log(`\n${results.length - failed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
