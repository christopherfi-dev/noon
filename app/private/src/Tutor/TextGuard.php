<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Provider\DisclosureGuard;
use AlgebraEngine\Support\LearnerText;

/**
 * Detectors on every learner-visible AI text (architecture v1.5 §5.6; round 7
 * §4). They log; they do not block, repair or replace: the student sees the
 * AI's text unchanged, and each finding is recorded with the exact text, the
 * guard's name and the problem and response it relates to. The only findings
 * that change anything are the two held-attempt cases (round 7 §5), decided
 * by the engine: help before a check card, and a verdict conflict raised in
 * the envelope's issue.
 *
 * Guards: ANSWER_LEAK (open or upcoming problem), INTERNAL_VOCABULARY,
 * MASTERY_OR_GOAL_CLAIM, HELP_CLAIM, VERDICT_CONTRADICTION, TEXT_LENGTH, and
 * the before-check-card audit (CHECK_CARD_ANSWER, CHECK_CARD_PHRASE,
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
     * Round 7 §5: the phrase audit for text before a check card. The round 6
     * forward-looking list, extended with hints, methods, strategies and
     * reminders directed at a new problem. A hit holds the check attempt.
     */
    public const FORWARD = ['next', 'try', 'remember', 'make sure', "don't forget", 'dont forget', 'do not forget', 'when you',
        'hint', 'tip', 'careful', 'watch out', 'watch for', 'look out for', 'keep in mind', 'start by', 'start with', 'begin by',
        'same way', 'same method', 'same strategy', 'same steps', 'same idea', 'do the same', 'like before', 'as before', 'just like', 'apply'];

    /** §2.1: openings that state the opposite of code's verdict. */
    private const AFFIRM = '/^\W*(?:correct|that\'?s\s+(?:correct|right)|that\s+is\s+(?:correct|right)|right|yes|exactly|perfect|well\s+done|great\s+job|nice\s+work|good\s+job|you\s+got\s+it|nailed\s+it)\b/i';
    private const AFFIRM_ANYWHERE = '/\byour\s+answer\s+(?:is|was)\s+(?:correct|right)\b/i';
    private const DENY = '/^\W*(?:incorrect|wrong|not\s+quite|not\s+right|not\s+correct|that\'?s\s+(?:not|wrong|incorrect)|that\s+is\s+(?:not|wrong|incorrect))\b/i';
    private const DENY_ANYWHERE = '/\byour\s+answer\s+(?:is|was)\s+(?:not\s+(?:correct|right)|incorrect|wrong)\b/i';

    /**
     * Detectors that apply to every learner-visible AI text.
     *
     * @param list<array> $protected problem records whose answers must not appear (the open problem, a retry target, a problem posed now)
     * @return list<array{guard:string,detail:string,problem_id:?string,problem_version:?int}>
     */
    public static function findings(string $text, array $protected, ?string $verdict = null): array
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
        if (($claims = self::masteryClaims($text)) !== []) {
            $out[] = self::finding('MASTERY_OR_GOAL_CLAIM', implode(', ', $claims));
        }
        $help = array_values(array_diff(LearnerText::modelTextTerms($text), $terms));
        if ($help !== []) {
            $out[] = self::finding('HELP_CLAIM', implode(', ', $help));
        }
        if ($verdict !== null && ($c = self::contradicts($text, $verdict)) !== []) {
            $out[] = self::finding('VERDICT_CONTRADICTION', $c[0]);
        }
        return $out;
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
     * The before-check-card audit (round 7 §5). Text before a check card
     * refers only to the previous answer. The check's answer or a phrase from
     * FORWARD holds the attempt; a number it shares with the check is a signal
     * only (architecture v1.5 §5.3).
     *
     * @return array{findings:list<array>,hold:bool,phrases:list<string>,shared:list<string>}
     */
    public static function beforeCheckCard(string $text, array $problem): array
    {
        $findings = [];
        $written = array_merge(Numbers::digits($text), array_map('strval', Numbers::words($text)));
        $shared = array_values(array_unique(array_intersect($problem['numbers'], $written)));
        if ($shared !== []) {
            $findings[] = self::finding('CHECK_CARD_SHARED_NUMBER', 'shares numbers with the check question: ' . implode(', ', $shared) . ' (a signal; nothing is held)', $problem);
        }
        $leak = self::leaks($text, $problem);
        if ($leak) {
            $findings[] = self::finding('CHECK_CARD_ANSWER', 'contains the check question\'s answer', $problem);
        }
        $phrases = [];
        $lower = mb_strtolower(str_replace("\u{2019}", "'", $text), 'UTF-8');
        foreach (self::FORWARD as $phrase) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($phrase, '/') . '(?![\p{L}])/u', $lower)) {
                $phrases[] = $phrase;
            }
        }
        if ($phrases !== []) {
            $findings[] = self::finding('CHECK_CARD_PHRASE', 'directed at the new problem: ' . implode(', ', $phrases), $problem);
        }
        return ['findings' => $findings, 'hold' => $leak || $phrases !== [], 'phrases' => $phrases, 'shared' => $shared];
    }

    private static function finding(string $guard, string $detail, ?array $problem = null): array
    {
        return ['guard' => $guard, 'detail' => $detail, 'problem_id' => $problem['problem_id'] ?? null, 'problem_version' => $problem['version'] ?? null];
    }
}
