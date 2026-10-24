<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Normalizer;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Provider\DisclosureGuard;
use AlgebraEngine\Support\LearnerText;
use AlgebraEngine\Support\ProblemText;

/**
 * Detectors on every learner-visible AI text (round 7 §4; round 8 §3; round 9
 * §3.2 and §4.6). Every detector logs only: none blocks, repairs or replaces
 * the text, holds a check or changes a count. The student sees the AI's text
 * unchanged, and each finding is recorded as a signal with the exact text,
 * the guard's name and the problem and response it relates to.
 *
 * Guards: ANSWER_LEAK (open or upcoming problem), INTERNAL_VOCABULARY,
 * SKILL_CODE, MASTERY_OR_GOAL_CLAIM, HELP_CLAIM, VERDICT_CONTRADICTION,
 * TEXT_LENGTH, and the before-check-card audit (CHECK_CARD_ANSWER,
 * CHECK_CARD_ANSWER_SIGNAL, CHECK_CARD_PHRASE, CHECK_CARD_INVITATION,
 * CHECK_CARD_SHARED_NUMBER).
 */
final class TextGuard
{
    public const MAX_LENGTH = 1200;

    /** No mastery claims (and no goal claims: code alone computes goals). */
    private const MASTERY = [
        '/\bmaster(?:ed|y|ing|s)?\b/i',
        '/\byou(?:\'ve|\s+have)\s+(?:now\s+|really\s+|fully\s+)?(?:learned|learnt)\s+(?:this|it|how)\b/i',
        '/\byou\s+know\s+(?:this|it|how\s+to\s+do\s+(?:this|it))\s+now\b/i',
        '/\byou\s+now\s+know\b/i',
        '/\byou\s+(?:fully\s+|really\s+)?understand\s+(?:this|it)\s+now\b/i',
        '/\bproficien(?:t|cy)\b/i',
        '/\bgoals?\s+(?:is\s+|are\s+|has\s+been\s+|have\s+been\s+)?(?:met|reached|achieved|completed?)\b/i',
        '/\b(?:met|reached|achieved)\s+(?:the|your|this|that)\s+goal\b/i',
    ];

    /**
     * The phrase audit for text before a check card. Round 7 §5 built it from
     * the round 6 forward-looking list; round 8 §3.3 keeps on it only hints,
     * methods, strategies and reminders directed at a new problem. A hit is
     * logged as a signal (round 9 §3.2).
     */
    public const CARD_PHRASES = ['remember', 'make sure', "don't forget", 'dont forget', 'do not forget', 'when you',
        'hint', 'tip', 'careful', 'watch out', 'watch for', 'look out for', 'keep in mind', 'start by', 'start with', 'begin by',
        'same way', 'same method', 'same strategy', 'same steps', 'same idea', 'do the same', 'like before', 'as before', 'just like', 'apply'];

    /** Round 8 §3.3: neutral invitations ("next", "try" and the like) before a check card are logged, phrase by phrase. */
    public const INVITATION_PHRASES = ['next', 'try', 'one more', 'another one', 'your turn', 'have a go', 'give it a go'];

    /** §2.1: openings that state the opposite of code's verdict. */
    private const AFFIRM = '/^\W*(?:correct|that\'?s\s+(?:correct|right)|that\s+is\s+(?:correct|right)|right|yes|exactly|perfect|well\s+done|great\s+job|nice\s+work|good\s+job|you\s+got\s+it|nailed\s+it)\b/i';
    private const AFFIRM_ANYWHERE = '/\byour\s+answer\s+(?:is|was)\s+(?:correct|right)\b/i';
    private const DENY = '/^\W*(?:incorrect|wrong|not\s+quite|not\s+right|not\s+correct|that\'?s\s+(?:not|wrong|incorrect)|that\s+is\s+(?:not|wrong|incorrect))\b/i';
    private const DENY_ANYWHERE = '/\byour\s+answer\s+(?:is|was)\s+(?:not\s+(?:correct|right)|incorrect|wrong)\b/i';

    /** Round 9 §4.6: a skill code (Q07, E06) in learner text: one capital letter from the curriculum's prefixes and two digits, standing alone. */
    private const SKILL_CODE = '/(?<![\p{L}\p{N}_])[ACDEFGHILMNPQRSUX]\d{2}(?![\p{L}\p{N}_])/u';

    /**
     * Detectors that apply to every learner-visible AI text.
     *
     * Round 8 §3.4: when the text is followed directly by a check card, the
     * support words ("on your own", "by yourself", "independently", "without
     * help", "no help") in its last sentence are not flagged; inviting the
     * student to try a check on their own is accurate (architecture v1.7 §4).
     * Everywhere else they are flagged as before.
     *
     * @param list<array> $protected problem records whose answers must not appear (the open problem, a retry target, a problem posed now)
     * @return list<array{guard:string,detail:string,problem_id:?string,problem_version:?int}>
     */
    public static function findings(string $text, array $protected, ?string $verdict = null, bool $beforeCheckCard = false): array
    {
        $out = [];
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $out[] = self::finding('TEXT_LENGTH', 'longer than ' . self::MAX_LENGTH . ' characters');
        }
        foreach ($protected as $p) {
            if (self::leaks($text, $p)) {
                $out[] = self::finding('ANSWER_LEAK', 'contains the answer to a problem the student has not answered yet', $p);
            }
        }
        $terms = LearnerText::internalTerms($text);
        if ($terms !== []) {
            $out[] = self::finding('INTERNAL_VOCABULARY', implode(', ', $terms));
        }
        if (($codes = self::skillCodes($text)) !== []) {
            $out[] = self::finding('SKILL_CODE', implode(', ', $codes)) + ['codes' => $codes];
        }
        if (($claims = self::masteryClaims($text)) !== []) {
            $out[] = self::finding('MASTERY_OR_GOAL_CLAIM', implode(', ', $claims));
        }
        $help = self::helpClaims($text, $beforeCheckCard);
        if ($help !== []) {
            $out[] = self::finding('HELP_CLAIM', implode(', ', $help)) + ['phrases' => $help];
        }
        if ($verdict !== null && ($c = self::contradicts($text, $verdict)) !== []) {
            $out[] = self::finding('VERDICT_CONTRADICTION', $c[0]);
        }
        return $out;
    }

    /**
     * The support words flagged as help claims. Before a check card, the last
     * sentence (the handover) is left out (round 8 §3.4).
     *
     * @return list<string>
     */
    public static function helpClaims(string $text, bool $beforeCheckCard = false): array
    {
        $scope = $beforeCheckCard ? self::withoutLastSentence($text) : $text;
        return array_values(array_intersect(LearnerText::modelTextTerms($scope), array_keys(LearnerText::SUPPORT_CLAIMS)));
    }

    /** The support words in the handover sentence before a check card, which are not flagged (round 8 §3.4). @return list<string> */
    public static function handoverSupportWords(string $text): array
    {
        $last = self::sentences($text);
        $last = $last === [] ? '' : end($last);
        return array_values(array_intersect(LearnerText::modelTextTerms($last), array_keys(LearnerText::SUPPORT_CLAIMS)));
    }

    /** @return list<string> the sentences of a text, each with its closing punctuation */
    public static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?:])\s+|\n+/u', trim($text)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    private static function withoutLastSentence(string $text): string
    {
        $all = self::sentences($text);
        array_pop($all);
        return implode(' ', $all);
    }

    /** @return list<string> the skill codes written in the text */
    public static function skillCodes(string $text): array
    {
        preg_match_all(self::SKILL_CODE, $text, $m);
        return array_values(array_unique($m[0]));
    }

    /** @return list<string> the mastery or goal claims found */
    public static function masteryClaims(string $text): array
    {
        $found = [];
        foreach (self::MASTERY as $re) {
            if (preg_match($re, $text, $m)) {
                $found[] = $m[0];
            }
        }
        return $found;
    }

    /** True when the text contains the problem's answer as the student would write it. */
    public static function leaks(string $text, array $problem): bool
    {
        $answer = ['kind' => $problem['answer']['kind'], 'expected' => $problem['answer']['expected']];
        return DisclosureGuard::violations($text, [[
            'answer' => $answer,
            'variables' => $problem['variables'] ?: ['x'],
            'options' => $problem['options'] ?? [],
        ]]) !== [];
    }

    /**
     * Text that states the opposite of code's verdict on the answer just
     * checked. Code shows its own result line; this detector records it.
     *
     * @return list<string>
     */
    public static function contradicts(string $text, string $verdict): array
    {
        if ($verdict === 'INCORRECT' || $verdict === 'PARTIAL') {
            if (preg_match(self::AFFIRM, $text) || preg_match(self::AFFIRM_ANYWHERE, $text)) {
                return ['calls the answer correct; code checked it and it is not'];
            }
        }
        if ($verdict === 'CORRECT') {
            if (preg_match(self::DENY, $text) || preg_match(self::DENY_ANYWHERE, $text)) {
                return ['calls the answer wrong; code checked it and it is correct'];
            }
        }
        return [];
    }

    /**
     * The before-check-card audit (round 7 §5, round 8 §3.3 and §3.5). Every
     * finding is a signal (round 9 §3.2): a hint, method, strategy or reminder
     * phrase; the check's answer, with where it may come from (the previous
     * problem, its answer, or the student's entry); neutral invitations; and a
     * number shared with the check. Nothing is held.
     *
     * @param array{problems?:list<array>,entries?:list<string>} $previous the previous problem(s) and the student's entries this text may refer to
     * @return array{findings:list<array>,phrases:list<string>,invitations:list<string>,shared:list<string>,answer_in_text:bool,answer_source:?string}
     */
    public static function beforeCheckCard(string $text, array $problem, array $previous = []): array
    {
        $findings = [];
        $written = array_merge(Numbers::digits($text), array_map('strval', Numbers::words($text)));
        $shared = array_values(array_unique(array_intersect($problem['numbers'], $written)));
        if ($shared !== []) {
            $findings[] = self::finding('CHECK_CARD_SHARED_NUMBER', 'shares numbers with the check question: ' . implode(', ', $shared), $problem);
        }
        $leak = self::leaks($text, $problem);
        $source = $leak ? self::answerSource($problem, $previous) : null;
        if ($leak && $source === null) {
            $findings[] = self::finding('CHECK_CARD_ANSWER', 'contains the check question\'s answer', $problem);
        } elseif ($leak) {
            $findings[] = self::finding('CHECK_CARD_ANSWER_SIGNAL', 'contains a number equal to the check question\'s answer that comes from ' . $source, $problem);
        }
        $phrases = self::phrasesIn($text, self::CARD_PHRASES);
        if ($phrases !== []) {
            $findings[] = self::finding('CHECK_CARD_PHRASE', 'directed at the new problem: ' . implode(', ', $phrases), $problem) + ['phrases' => $phrases];
        }
        $invitations = self::phrasesIn($text, self::INVITATION_PHRASES);
        if ($invitations !== []) {
            $findings[] = self::finding('CHECK_CARD_INVITATION', 'a neutral invitation: ' . implode(', ', $invitations), $problem) + ['phrases' => $invitations];
        }
        return ['findings' => $findings, 'phrases' => $phrases, 'invitations' => $invitations,
            'shared' => $shared, 'answer_in_text' => $leak, 'answer_source' => $source];
    }

    /**
     * The round 8 backstop, a signal since round 9 §3.2: during an open check,
     * text that contains the check's expression, a number from it or its
     * answer, and carries no help move, is logged.
     *
     * @return array{expression:bool,numbers:list<string>,answer:bool}
     */
    public static function duringCheck(string $text, array $check): array
    {
        $main = ProblemText::main($check['prompt']);
        $flat = fn (string $s) => (string) preg_replace('/\s+/u', '', mb_strtolower(Normalizer::normalize($s), 'UTF-8'));
        $written = array_merge(Numbers::digits($text), array_map('strval', Numbers::words($text)));
        return [
            'expression' => $main !== '' && str_contains($flat($text), $flat($main)),
            'numbers' => array_values(array_unique(array_intersect($check['numbers'], $written))),
            'answer' => self::leaks($text, $check),
        ];
    }

    /**
     * Where a number equal to the check's answer in the text before its card
     * may come from: the previous problem (its numbers or its answer) or the
     * student's entry. Null when it comes from neither.
     */
    private static function answerSource(array $problem, array $previous): ?string
    {
        $value = null;
        if ($problem['answer']['kind'] === 'exact_rational') {
            try {
                $value = ScalarParser::parse((string) $problem['answer']['expected']);
            } catch (MathInputError) {
                $value = null;
            }
        }
        foreach ($previous['problems'] ?? [] as $prev) {
            if ($value !== null && $value->isInteger() && in_array((string) abs($value->num()), $prev['numbers'] ?? [], true)) {
                return 'the previous problem';
            }
            if (isset($prev['answer']) && self::sameAnswerValue($problem, $prev)) {
                return 'the previous problem\'s answer';
            }
            if (self::leaks((string) ($prev['prompt'] ?? ''), $problem)) {
                return 'the previous problem';
            }
        }
        foreach ($previous['entries'] ?? [] as $entry) {
            if ($value !== null && $value->isInteger() && in_array((string) abs($value->num()), Numbers::digits((string) $entry), true)) {
                return 'the student\'s entry';
            }
            if (self::leaks((string) $entry, $problem)) {
                return 'the student\'s entry';
            }
        }
        return null;
    }

    private static function sameAnswerValue(array $problem, array $prev): bool
    {
        $a = $problem['answer'];
        $b = $prev['answer'];
        if ($a['kind'] !== $b['kind'] || $a['kind'] === 'classification') {
            return false;
        }
        try {
            if ($a['kind'] === 'exact_rational') {
                return ScalarParser::parse((string) $a['expected'])->abs()->equals(ScalarParser::parse((string) $b['expected'])->abs());
            }
            $vars = $problem['variables'] ?: ['x'];
            return Polynomial::fromNode(ExpressionParser::parse((string) $a['expected'], $vars))
                ->equals(Polynomial::fromNode(ExpressionParser::parse((string) $b['expected'], $prev['variables'] ?: $vars)));
        } catch (MathInputError) {
            return false;
        }
    }

    /** @param list<string> $list @return list<string> the phrases of $list found in $text, as whole words */
    private static function phrasesIn(string $text, array $list): array
    {
        $lower = mb_strtolower(str_replace("\u{2019}", "'", $text), 'UTF-8');
        $found = [];
        foreach ($list as $phrase) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($phrase, '/') . '(?![\p{L}])/u', $lower)) {
                $found[] = $phrase;
            }
        }
        return $found;
    }

    private static function finding(string $guard, string $detail, ?array $problem = null): array
    {
        return ['guard' => $guard, 'detail' => $detail, 'problem_id' => $problem['problem_id'] ?? null, 'problem_version' => $problem['version'] ?? null];
    }
}
