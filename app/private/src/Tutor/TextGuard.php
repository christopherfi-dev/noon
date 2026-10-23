<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Provider\DisclosureGuard;
use AlgebraEngine\Support\LearnerText;

/**
 * Round 6 §2.6: the guards on every learner-visible AI text, plus the
 * check-card rule (§5.2) and the verdict rule (§2.1: the AI never overrules
 * code's verdict). Questions are allowed. Each method returns the reasons a
 * text is rejected; an empty list means the text may be shown.
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

    /** §5.2: forward-looking phrases before a check card go on the audit list (not rejected). */
    public const FORWARD = ['next', 'try', 'remember', 'make sure', "don't forget", 'dont forget', 'do not forget', 'when you'];

    /** §2.1: openings that state the opposite of code's verdict. */
    private const AFFIRM = '/^\W*(?:correct|that\'?s\s+(?:correct|right)|that\s+is\s+(?:correct|right)|right|yes|exactly|perfect|well\s+done|great\s+job|nice\s+work|good\s+job|you\s+got\s+it|nailed\s+it)\b/i';
    private const AFFIRM_ANYWHERE = '/\byour\s+answer\s+(?:is|was)\s+(?:correct|right)\b/i';
    private const DENY = '/^\W*(?:incorrect|wrong|not\s+quite|not\s+right|not\s+correct|that\'?s\s+(?:not|wrong|incorrect)|that\s+is\s+(?:not|wrong|incorrect))\b/i';
    private const DENY_ANYWHERE = '/\byour\s+answer\s+(?:is|was)\s+(?:not\s+(?:correct|right)|incorrect|wrong)\b/i';

    /**
     * Guards that apply to every learner-visible AI text.
     *
     * @param list<array> $protected problem records whose answers must not appear (every open problem)
     * @return list<string>
     */
    public static function violations(string $text, array $protected): array
    {
        $out = [];
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $out[] = 'the text is too long (limit ' . self::MAX_LENGTH . ' characters)';
        }
        foreach ($protected as $p) {
            if (self::leaks($text, $p)) {
                $out[] = 'the text gives away the answer to a question the student has not answered yet';
                break;
            }
        }
        $terms = LearnerText::internalTerms($text);
        if ($terms !== []) {
            $out[] = 'the text uses internal terms: ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $terms));
        }
        if (self::masteryClaims($text) !== []) {
            $out[] = 'the text claims mastery or a met goal; code alone computes goals and nothing here claims mastery';
        }
        $claims = array_values(array_diff(LearnerText::modelTextTerms($text), $terms));
        if ($claims !== []) {
            $out[] = 'the text says whether work is done without help; code says that. It uses: ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $claims));
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
     * §2.1: text that states the opposite of code's verdict on the answer just
     * checked. Code shows its own result line; the AI may not contradict it.
     *
     * @return list<string>
     */
    public static function contradicts(string $text, string $verdict): array
    {
        if ($verdict === 'INCORRECT' || $verdict === 'PARTIAL') {
            if (preg_match(self::AFFIRM, $text) || preg_match(self::AFFIRM_ANYWHERE, $text)) {
                return ['the text calls the answer correct, but code checked it and it is not; describe what the student did instead'];
            }
        }
        if ($verdict === 'CORRECT') {
            if (preg_match(self::DENY, $text) || preg_match(self::DENY_ANYWHERE, $text)) {
                return ['the text calls the answer wrong, but code checked it and it is correct'];
            }
        }
        return [];
    }

    /**
     * §5.2: the text before a check card refers only to the previous answer.
     * It may contain no number from the new problem and must not leak its
     * answer. Forward-looking phrases are returned for the audit list.
     *
     * @return array{violations:list<string>,forward:list<string>}
     */
    public static function beforeCheckCard(string $text, array $problem): array
    {
        $violations = [];
        $written = array_merge(Numbers::digits($text), array_map('strval', Numbers::wordsExtended($text)));
        $shared = array_values(array_unique(array_intersect($problem['numbers'], $written)));
        if ($shared !== []) {
            $violations[] = 'the text before a check question may refer only to the previous answer; it contains numbers from the new check question (' . implode(', ', $shared) . ')';
        }
        if (self::leaks($text, $problem)) {
            $violations[] = 'the text before a check question gives away its answer';
        }
        $forward = [];
        $lower = mb_strtolower(str_replace("\u{2019}", "'", $text), 'UTF-8');
        foreach (self::FORWARD as $phrase) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($phrase, '/') . '(?![\p{L}])/u', $lower)) {
                $forward[] = $phrase;
            }
        }
        return ['violations' => $violations, 'forward' => $forward];
    }
}
