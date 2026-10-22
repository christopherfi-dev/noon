<?php
declare(strict_types=1);

namespace AlgebraEngine\Report;

/**
 * Round UI v3 (U9): the downloadable report. One self-contained HTML file:
 * inline CSS, no scripts, no external requests, every text escaped. It holds
 * the four learner headings, the teacher details expanded, the date and the
 * progress code. Every sentence comes from the report payload, which is built
 * from recorded evidence; no model-written text appears in it.
 */
final class ReportHtml
{
    private const CSS = <<<'CSS'
:root { --bg: #ffffff; --fg: #1b1f24; --muted: #545d68; --line: #c9d1d9; --card: #f6f8fa; --accent: #0b5cad; --mark: #fff4d6; }
@media (prefers-color-scheme: dark) { :root { --bg: #0f1419; --fg: #e6edf3; --muted: #a8b3bf; --line: #3b4552; --card: #161c23; --accent: #5aa7f0; --mark: #3a2f12; } }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--fg); font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 1.0625rem; line-height: 1.5; overflow-wrap: break-word; }
main { max-width: 760px; margin: 0 auto; padding: 16px 16px 32px; }
h1 { font-size: 1.6rem; line-height: 1.25; margin: 0.2em 0 0.3em; }
h2 { font-size: 1.15rem; margin: 1.1em 0 0.4em; }
h3 { font-size: 1.02rem; margin: 1em 0 0.3em; }
h4 { font-size: 0.95rem; margin: 0.8em 0 0.2em; color: var(--muted); }
p { margin: 0.4em 0; }
ul { margin: 0.3em 0; padding-left: 1.2em; }
li + li { margin-top: 4px; }
.meta { color: var(--muted); }
.code { display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight: 700; letter-spacing: 0.06em; padding: 2px 8px; border: 1px solid var(--line); border-radius: 6px; background: var(--card); }
.provider { font-weight: 700; padding: 8px 12px; border: 2px solid #b35c00; border-radius: 8px; background: var(--mark); }
.part { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 2px 16px 8px; margin: 12px 0; }
.empty { color: var(--muted); }
.teacher { border-top: 2px solid var(--line); margin-top: 24px; }
table { border-collapse: collapse; width: 100%; font-size: 0.92rem; }
caption { text-align: left; font-weight: 600; padding: 4px 0; }
th, td { border: 1px solid var(--line); padding: 6px 8px; text-align: left; vertical-align: top; }
@media (max-width: 599px) {
  table, thead, tbody, tr, th, td, caption { display: block; width: auto; }
  thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
  tr { border: 1px solid var(--line); border-radius: 8px; margin: 8px 0; padding: 4px 0; background: var(--card); }
  th, td { border: 0; padding: 4px 12px; }
  td::before { content: attr(data-label) ": "; color: var(--muted); }
}
@media print { body { background: #fff; color: #000; font-size: 11pt; } .part, .provider, .code { background: none; } }
CSS;

    /** @param array $report the report payload (with 'summary' and 'progress_code') */
    public static function render(array $report, string $isoDate): string
    {
        $e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $list = function (array $lines, string $empty) use ($e): string {
            if ($lines === []) {
                return $empty === '' ? '' : '<p class="empty">' . $e($empty) . '</p>';
            }
            return '<ul>' . implode('', array_map(fn ($l) => '<li>' . $e((string) $l) . '</li>', $lines)) . '</ul>';
        };
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate, new \DateTimeZone('UTC'));
        $date = $d === false ? $isoDate : $d->format('j F Y');
        $learner = $report['summary']['learner'];
        $teacher = $report['summary']['teacher'];
        $code = $report['progress_code'] ?? null;

        $h = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'">'
            . '<title>' . $e('Algebra practice report, ' . $date) . '</title><style>' . self::CSS . '</style></head><body><main>';
        $h .= '<h1>Algebra practice report</h1>';
        $h .= '<p class="meta">Date: ' . $e($date) . '</p>';
        if ($code !== null) {
            $h .= '<p>Your code: <span class="code">' . $e($code) . '</span></p>'
                . '<p class="meta">Enter this code on the tutor\'s welcome screen to continue next time. Anyone with this code or this report can continue the progress.</p>';
        }
        $h .= '<p class="provider">' . $e((string) $report['provider_label']) . '</p>';

        $h .= '<section aria-label="Summary">';
        foreach (SummaryView::HEADINGS as $key => $heading) {
            $h .= '<section class="part"><h2>' . $e($heading) . '</h2>' . $list($learner[$key] ?? [], SummaryView::EMPTY[$key]) . '</section>';
        }
        if (($learner['earlier'] ?? []) !== []) {
            $h .= '<section class="part"><h2>' . $e(SummaryView::EARLIER) . '</h2>' . $list($learner['earlier'], '') . '</section>';
        }
        $h .= '<p class="meta">' . $e((string) $learner['note']) . '</p></section>';

        $h .= '<section class="teacher"><h2>Details for teachers</h2>';
        $h .= '<p>Status: ' . $e($teacher['status']) . '. Topic' . (count($teacher['topics']) === 1 ? '' : 's') . ': ' . $e(implode(', ', $teacher['topics']) ?: 'none') . '.</p>';
        $cols = ['Questions answered' => 'items_attempted', 'Without help' => 'independent', 'With help' => 'assisted', 'Goal' => 'goal', 'What the beta checks' => 'checked'];
        $h .= '<table><caption>Per skill, this session</caption><thead><tr><th scope="col">Skill</th>';
        foreach (array_keys($cols) as $c) {
            $h .= '<th scope="col">' . $e($c) . '</th>';
        }
        $h .= '</tr></thead><tbody>';
        foreach ($teacher['skills'] as $s) {
            $h .= '<tr><th scope="row">' . $e($s['name']) . '</th>';
            foreach ($cols as $label => $key) {
                $h .= '<td data-label="' . $e($label) . '">' . $e((string) $s[$key]) . '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table>';
        foreach ($teacher['skills'] as $s) {
            $h .= '<h3>' . $e($s['name']) . '</h3><h4>What we saw (observations, not confirmed causes)</h4>' . $list($s['observed'], 'Nothing to note.');
            if ($s['route'] !== []) {
                $h .= '<h4>Route</h4>' . $list($s['route'], '');
            }
            if ($s['notes'] !== []) {
                $h .= '<h4>Not in this beta</h4>' . $list($s['notes'], '');
            }
        }
        foreach ($teacher['sections'] as $sec) {
            $h .= '<h3>' . $e($sec['heading']) . '</h3>' . $list($sec['lines'], $sec['empty']);
        }
        $h .= '</section></main></body></html>' . "\n";
        return $h;
    }
}
