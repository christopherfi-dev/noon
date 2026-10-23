<?php
declare(strict_types=1);

namespace AlgebraEngine\Report;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;

/**
 * Round UI (U2) and UI v3 rules for the session summary, kept in round 6: the
 * four learner headings and their fixed empty lines, plain words for record
 * text, skill titles (IDs only in the teacher level, in brackets), the
 * "From earlier sessions" label, and "Welcome back" from a progress record.
 * Round 6: the summary itself is built from the gradebook by TutorReport.
 */
final class SummaryView
{
    /**
     * Plain words for record text: the summary never shows these terms
     * (declared prerequisite, descent, implicated, branch, transfer variant,
     * retention claim, faded support, coverage) or raw codes.
     */
    private const WORDS = [
        '/\bRepair goal met \(3 correct in a row without help\) at [A-Z]\d{2}\./' => 'the goal there was met (3 correct in a row without help).',
        '/\bParent ([A-Z]\d{2}) re-verified with fresh work\./' => '$1 was checked again with fresh questions.',
        '/\bPrerequisite check succeeded; repair at the parent\./' => 'the building block was answered correctly, so step-by-step work moved to the skill above it.',
        '/\bPrerequisite ([A-Z]\d{2}) succeeded independently; bounded instructional trial at ([A-Z]\d{2})\./' => 'the building block $1 was answered correctly without help, so a short round of step-by-step questions followed at $2.',
        '/\bImplicated prerequisite ([A-Z]\d{2}) is not available in beta; repair at ([A-Z]\d{2})\./' => 'the error pattern pointed to $1, which is not available in beta, so the step-by-step work stayed at $2.',
        '/\bError not reproduced on the probe; bounded instructional trial before further independent checks\./' => 'the error did not happen again on a fresh question, so a short round of step-by-step questions came before more questions without help.',
        '/\bNo discriminating branch; bounded instructional trial at ([A-Z]\d{2})\./' => 'no error pattern pointed to one building block, so a short round of step-by-step questions followed at $1.',
        '/\bDescent bounded; repair at ([A-Z]\d{2})\./' => 'moving down to building blocks reached its limit, so the step-by-step work stayed at $1.',
        '/\bProbe budget reached without usable evidence; bounded instructional trial\./' => 'the extra questions ran out without a clear result, so a short round of step-by-step questions followed.',
        '/\bLearner reports not having learned this; bounded instructional trial \(not counted as incorrect\)\./' => 'the learner said they had not learned this yet, so a short round of step-by-step questions followed (not counted as incorrect).',
        '/\bCheck at ([A-Z]\d{2}) was answered with help or not in the requested form; bounded instructional trial\./' => 'the check at $1 was answered with help or not in the requested form, so a short round of step-by-step questions followed.',
        '/\bAn independent-check attempt answered with help was not correct; continue guided work \(not a failed cycle\)\./' => 'an answer on the check, given after help, was not correct, so step-by-step work continued (not a failed round).',
        '/\bFading support: whole problem first; smaller questions only if needed\./' => 'the whole problem first, smaller questions only if needed.',
        '/ A new guided problem with different numbers\./' => ' A new problem with different numbers.',
        '/\bThree independent-check cycles at this repair point did not meet the goal after intervening instruction\. Observations are retained; no foundational incapacity is inferred\./' => 'Three rounds of checks here did not reach the goal after step-by-step work. What was seen is kept; nothing is concluded about ability.',
        '/\bModel provider: .*?\. Accepted work is preserved; this is not a learner failure\./' => 'The tutor model did not give a usable reply. Saved work is kept; this is not a learner failure.',
        '/\bThe canonical record for ([A-Z]\d{2}) is not available\./' => 'The curriculum record for $1 is not available.',
        '/\bbounded instructional trial\b/' => 'short round of step-by-step questions',
        '/\b[Dd]eclared prerequisites\b/' => 'listed building blocks',
        '/\b[Dd]eclared prerequisite\b/' => 'listed building block',
        '/\bprerequisites\b/' => 'building blocks',
        '/\bprerequisite\b/' => 'building block',
        '/\bPrerequisite\b/' => 'Building block',
        '/\b[Ii]mplicated\b/' => 'pointed to',
        '/\bbranch\b/' => 'possible cause',
        '/\bDescended\b/' => 'Moved down',
        '/\bdescended\b/' => 'moved down',
        '/\bDescent\b/' => 'Moving down',
        '/\bdescent\b/' => 'moving down',
        '/\b[Tt]ransfer variants?\b/' => 'new kind of question',
        '/\bretention claims?\b/' => 'claim about remembering later',
        '/\bretention\b/' => 'remembering later',
        '/\bfaded support\b/' => 'less help',
        '/\b[Cc]overage\b/' => 'what is checked',
        '/\s*\((?:all_of|any_of)\s+edge\)/' => '',
        '/\s*\((?:AVAILABLE_SCORED|PARTIAL_COMPONENTS|UNAVAILABLE)\)/' => '',
        '/\s*\(POLICY-[A-Z0-9-]+\)/' => '',
    ];

    /** Round UI v3 (U2): the four learner headings, in this order and with these words. */
    public const HEADINGS = [
        'did_well' => 'What you did well',
        'trouble' => 'Where you had trouble',
        'worked_on' => 'What we worked on',
        'next_time' => 'What to work on next time',
    ];
    /** Round UI v3 (U2): the fixed line for a heading with nothing to report. Nothing is invented to fill one. */
    public const EMPTY = [
        'did_well' => 'No goals were met in this session yet.',
        'trouble' => 'No trouble spots were recorded in this session.',
        'worked_on' => 'This session had no practice with help.',
        'next_time' => 'You met every goal available in this tutor so far. Ask your teacher what comes next.',
    ];
    public const EARLIER = 'From earlier sessions';

    /**
     * What an observed mistake pattern did, as an observation (never a cause).
     * Codes without an entry are not described to the learner.
     */
    public const PATTERNS = [
        // Round 7 §8.3: one description for this mistake on Q07 and E06; the round 6 codes read the same way.
        'FIRST_TERM_ONLY' => 'multiplied only the first term inside the parentheses',
        'FIRST_TERM_ONLY_CHOICE' => 'chose a step that multiplied only the first term inside the parentheses',
        'DIST_X_ONLY' => 'multiplied only the first term inside the parentheses',
        'DIST_X_ONLY_CHOICE' => 'chose a step that multiplied only the first term inside the parentheses',
        'SECOND_TERM_ONLY' => 'multiplied only the number inside the parentheses, not the x',
        'NOT_EVERY_TERM' => 'multiplied some terms inside the parentheses but not all of them',
        'FACTOR_NOT_EVERY_TERM_CHOICE' => 'chose an expression where the number outside did not multiply every term',
        'SIGN_ERROR_B' => 'used the opposite sign for the number inside the parentheses',
        'DIVIDE_THEN_ADD_B' => 'added the number inside the parentheses instead of undoing it',
        'ADD_INSTEAD_OF_SUBTRACT' => 'added a number that needed to be undone',
        'ARITH_FINAL_PLUS' => 'were off by one in the last step',
        'ARITH_FINAL_MINUS' => 'were off by one in the last step',
        'ARITH_OFF_BY_ONE' => 'were off by one in the last step',
        'DIVIDE_FIRST_ONLY' => 'divided only one side before removing the added number',
        'MULTIPLY_INSTEAD_OF_DIVIDE' => 'multiplied by the number in front of x instead of dividing',
        'MULTIPLIED' => 'multiplied by the number in front of x instead of dividing',
        'SUBTRACTED' => 'subtracted the number in front of x instead of dividing',
        'RECIPROCAL' => 'divided the two numbers the other way round',
        'QUOTIENT_SIGN' => 'had the right size but the opposite sign',
        'SIGN_FLIP' => 'had the right size but the opposite sign',
        'MAGNITUDE' => 'had the right sign but a different size',
        'PRODUCT_FACT' => 'set the expression out correctly but made a multiplication slip',
        'ADDED_INSTEAD' => 'added the second number instead of its opposite',
        'ADDED_MAGNITUDES' => 'added the sizes of two numbers with opposite signs',
        'WRONG_INVERSE' => 'used the same operation again instead of undoing it',
        'INVERSE_OPERATION_CHOICE' => 'chose a step that does not keep both sides equal',
    ];

    public function __construct(private CurriculumRepository $curriculum)
    {
    }

    /** Round UI v3 (U10): "Welcome back" from a progress record alone, in the same four headings. */
    public function fromRecord(array $record): array
    {
        $out = ['did_well' => [], 'trouble' => [], 'worked_on' => [], 'next_time' => []];
        foreach ($record['skills'] ?? [] as $id => $r) {
            $when = ' (' . self::date($r['date']) . ')';
            switch ($r['status']) {
                case 'GOAL_MET':
                    $out['did_well'][] = $this->title($id) . ' — goal met: 3 correct in a row without help' . $when . '.';
                    break;
                case 'NEEDED_HELP':
                    $out['trouble'][] = $this->title($id) . ': not met yet' . $when . '.';
                    $out['worked_on'][] = $this->title($id) . ': practised with help' . $when . '.';
                    break;
                case 'UNRESOLVED':
                    $out['trouble'][] = $this->title($id) . ': not met yet' . $when . '.';
                    break;
                case 'NOT_IN_BETA':
                    // The record keeps no dependencies, so a skill outside the tutor is listed while any recorded goal is unmet.
                    if (array_filter($record['skills'], fn ($o) => in_array($o['status'], ['NEEDED_HELP', 'UNRESOLVED'], true)) !== []) {
                        $out['next_time'][] = 'Practise with your teacher: ' . $this->title($id) . ' (not in this tutor yet).';
                    }
                    break;
            }
            foreach ($r['patterns'] ?? [] as $code) {
                if (isset(self::PATTERNS[$code])) {
                    $out['trouble'][] = 'At least once you ' . self::PATTERNS[$code] . '.';
                }
            }
        }
        $next = $record['next_skill'] ?? null;
        if ($next !== null) {
            array_unshift($out['next_time'], 'Continue with “' . $this->title($next) . '”.');
        }
        return array_map(fn ($lines) => self::unique($lines), $out) + ['headings' => self::HEADINGS, 'empty' => self::EMPTY];
    }

    /**
     * Round UI v3 (U2, U10): earlier sessions' results, each with its date. They are listed
     * on their own and never counted as this session's goals.
     *
     * @return list<string>
     */
    public function earlier(array $earlier, bool $withIds): array
    {
        $words = ['GOAL_MET' => 'goal met', 'NEEDED_HELP' => 'needed help', 'UNRESOLVED' => 'not met yet', 'NOT_IN_BETA' => 'not in this tutor yet'];
        $out = [];
        foreach ($earlier as $id => $r) {
            $out[] = $this->title($id) . ($withIds ? ' (' . $id . ')' : '') . ': ' . ($words[$r['status']] ?? 'recorded') . ' on ' . self::date($r['date']) . '.';
        }
        return $out;
    }

    public static function date(string $ymd): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new \DateTimeZone('UTC'));
        return $d === false ? $ymd : $d->format('j F Y');
    }

    // ------------------------------------------------------------ text

    /** Plain words for record text, then "title (ID)" for every skill ID. */
    public function text(string $line): string
    {
        $line = (string) preg_replace(array_keys(self::WORDS), array_values(self::WORDS), self::plain($line));
        return (string) preg_replace_callback('/(?<![\w(])([A-Z]\d{2})(?![\w)])/', fn ($m) => $this->curriculum->has($m[1]) ? $this->title($m[1]) . ' (' . $m[1] . ')' : $m[1], $line);
    }

    public function title(string $skillId): string
    {
        return $this->curriculum->has($skillId) ? $this->curriculum->title($skillId) : $skillId;
    }

    /** @param list<string> $lines @return list<string> */
    public static function unique(array $lines): array
    {
        return array_values(array_unique(array_filter($lines, fn ($l) => trim($l) !== '')));
    }

    /** Plain words for text copied from stored records into the learner-visible summary (R5-B). */
    public static function plain(string $text): string
    {
        static $map = [
            '/\b(?:a|the) host-derived key\b/i' => 'the answer the app computed',
            '/\bhost-derived key\b/i' => 'answer the app computed',
            '/\bhost-matched\b/' => 'app-matched',
            '/\bHost\b/' => 'App',
            '/\bhost\b/' => 'app',
            '/\bIMMEDIATE_CRITERION_MET\b/' => 'goal met: 3 correct in a row without help',
            '/\bCOMPONENT_CRITERION_MET\b/' => 'goal met: 3 correct in a row without help (numeric part only)',
            '/\b[Cc]riterion met\b/' => 'goal met (3 correct in a row without help)',
            '/\bImmediate criterion\b/' => 'Goal',
            '/\bimmediate criterion\b/' => 'goal',
            '/\bcriteria\b/' => 'goals',
            '/\bCriterion\b/' => 'Goal',
            '/\bcriterion\b/' => 'goal',
            '/\bwas assisted\b/' => 'was answered with help',
            '/\bAssisted independent-check attempt\b/' => 'An independent-check attempt answered with help',
            '/\bAssisted\b/' => 'With help',
            '/\bassisted\b/' => 'with help',
            '/\b([Gg])uided step\(s\)/' => 'step-by-step question(s)',
            '/\bGuided steps\b/' => 'Step-by-step questions',
            '/\bGuided step\b/' => 'Step-by-step question',
            '/\bguided steps\b/' => 'step-by-step questions',
            '/\bguided step\b/' => 'step-by-step question',
            '/\bverdicts\b/' => 'results',
            '/\bverdict\b/' => 'result',
            '/\bexpected (?:value|answer)\b/' => 'correct answer',
            '/\banswer key\b/' => 'correct answer',
            '/\bmatched the key\b/' => 'matched the correct answer',
        ];
        return (string) preg_replace(array_keys($map), array_values($map), $text);
    }
}
