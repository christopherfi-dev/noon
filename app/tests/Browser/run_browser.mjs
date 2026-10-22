// Browser tests (mock mode): B1 end to end at 360x740 and 1280x800, UI-01
// (mobile keyboard and screen-reader status) and UI-02 (safe rendering).
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
    const dontKnow = await page.getByTestId('not-learned').textContent();
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
    maxOverflow = Math.max(maxOverflow, await overflow(page));
    await page.screenshot({ path: path.join(outDir, `b1-${label}-report.png`), fullPage: true });
    const expected = ['TARGET_ASSESSMENT', 'DIAGNOSTIC_PROBE', 'PREREQUISITE_CHECK', 'GUIDED_REPAIR', 'GUIDED_REPAIR', 'INDEPENDENT_CHECK', 'RETURN_TO_TARGET', 'SESSION_REPORT'];
    const modes = steps.map((s) => s.mode);
    const pass = JSON.stringify(modes) === JSON.stringify(expected) && finalMode === 'SESSION_REPORT' && guidedHeading && dontKnow.includes("I don't know")
      && reportText.includes('MOCK') && reportText.includes('IMMEDIATE_CRITERION_MET') && !/master/i.test(reportText) && maxOverflow <= 0 && errors.length === 0;
    record(`B1 browser journey (${label} ${w}x${h})`, pass, { modes, maxOverflow, consoleErrors: errors, smallerPrompt: smallerPrompt.slice(0, 120) });
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
    const focusVisible = await page.evaluate(() => { const b = document.querySelector('[data-testid="hint"]'); b.focus(); const s = getComputedStyle(b); return s.outlineStyle !== 'none' || s.boxShadow !== 'none'; });
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
} finally {
  await browser.close();
  server.kill();
}
fs.writeFileSync(path.join(outDir, 'browser-results.json'), JSON.stringify({ ran_at: new Date().toISOString(), results }, null, 2));
const failed = results.filter((r) => !r.pass).length;
console.log(`\n${results.length - failed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
