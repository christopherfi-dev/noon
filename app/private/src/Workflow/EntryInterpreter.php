<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

/**
 * What the learner typed in the answer box, read by the host first. Pure; the
 * engine decides what each kind does.
 *
 * Round 6 (architecture 1.3, §3): deterministic first. The host applies only
 * safe normalisation: capital letters become the item's variable when the item
 * has exactly one variable and the entry uses no other letter, and a trailing
 * "?" is dropped when the rest is readable. It then parses. It needs the model
 * to read the entry when it cannot parse it, or when the entry contains words,
 * the sign × or the word "times", or the variable letter standing alone between
 * spaces next to a number or another variable ("4 x 4", "4 x x"). The exact
 * stuck and ask-for-answer phrases of round 5 stay host-matched; none of them
 * is ever scored as incorrect.
 */
final class EntryInterpreter
{
    public const STUCK = ['idk', 'i dont know', 'dont know', 'dunno', 'no idea', 'no clue', 'not sure', 'im stuck', 'stuck', 'help'];
    public const ASK_FOR_ANSWER = ['can you just tell me the answer', 'just tell me the answer', 'tell me the answer', 'whats the answer', 'give me the answer'];
    public const NUMBER_WORDS = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty'];

    /**
     * The round 5.1 lines for "just tell me the answer", one per follow-on. Since round 6 the
     * model writes this reply; the simulated model uses these words.
     */
    public const LINE_ASK_HINT = 'I won\'t give the answer, but here is a hint.';
    public const LINE_ASK_SMALLER = 'I won\'t give the answer, but here is a smaller step.';
    public const LINE_ASK_ANOTHER_WAY = 'I won\'t give the answer, but let\'s look at it another way.';
    public const LINE_ASK_PREREQUISITE = 'I won\'t give the answer, but let\'s check something that comes before this.';
    public const LINE_ASK_DIFFERENT_PROBLEM = 'I won\'t give the answer, but let\'s try a different problem.';

    /** Host fallbacks, used only when no model reading is available for an entry the host cannot read. */
    public const LINE_QUESTION = 'This box is for your answer. If you\'re stuck, type help.';
    public const LINE_NUMBER_WORD = 'Write the number with digits.';
    public const LINE_X_AS_TIMES = 'I can\'t tell whether that x means times. Write multiplication with *, or write the result.';
    public const LINE_UNREADABLE = 'That answer could not be read. Please write it again with numbers and symbols.';

    public static function normalise(string $text): string
    {
        $s = mb_strtolower($text, 'UTF-8');
        $s = str_replace(["'", "\u{2019}", "\u{2018}", '`', "\u{00B4}"], '', $s);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));
        return trim(rtrim($s, '.!?'));
    }

    /**
     * @param callable(string):bool $readable true when the active item's checker can read the text (any verdict but UNSCORABLE)
     * @param array{response_type:string,prompt:string,objects?:array} $item
     * @return array{kind:string,read_as:?string,normalised:string,code_reading:?string,triggers:list<string>}
     *   kind ANSWER, TRAILING_QUESTION_MARK, CAPITAL_LETTER (score read_as);
     *   STUCK, ASK_FOR_ANSWER (not scored);
     *   NEEDS_READING: the host asks the model to read the entry. code_reading is the host's own parse, or null.
     */
    public static function interpret(string $text, array $item, callable $readable): array
    {
        $normalised = self::normalise($text);
        $raw = trim($text);
        $out = fn (string $kind, ?string $readAs, ?string $code = null, array $triggers = []) =>
            ['kind' => $kind, 'read_as' => $readAs, 'normalised' => $normalised, 'code_reading' => $code, 'triggers' => $triggers];
        if (in_array($normalised, self::STUCK, true) || ($raw !== '' && preg_match('/^\?+$/', $raw) === 1)) {
            return $out('STUCK', null);
        }
        if (in_array($normalised, self::ASK_FOR_ANSWER, true)) {
            return $out('ASK_FOR_ANSWER', null);
        }
        if ($raw === '') {
            return $out('ANSWER', $text, null); // nothing to read: the checker's own "nothing was entered" line
        }
        $kind = 'ANSWER';
        $s = $raw;
        $var = self::singleVariable($item);
        if ($var !== null && preg_match('/\p{Lu}/u', $s) === 1) {
            preg_match_all('/\p{L}/u', $s, $m);
            $letters = array_unique(array_map(fn ($l) => mb_strtolower($l, 'UTF-8'), $m[0]));
            if ($letters === [$var]) {
                $s = (string) preg_replace('/' . preg_quote(mb_strtoupper($var, 'UTF-8'), '/') . '/u', $var, $s);
                $kind = 'CAPITAL_LETTER';
            }
        }
        $code = null;
        if ($readable($s)) {
            $code = $s;
        } elseif (str_ends_with($s, '?') && ($trimmed = rtrim(rtrim($s, '?'))) !== '' && $readable($trimmed)) {
            $code = $trimmed;
            $kind = $kind === 'ANSWER' ? 'TRAILING_QUESTION_MARK' : $kind;
        }
        $triggers = self::triggers($code ?? $s, $item);
        if ($code === null) {
            $triggers = array_merge(['unparseable'], $triggers);
        }
        if ($triggers !== []) {
            return $out('NEEDS_READING', null, $code, $triggers);
        }
        return $out($kind, $code, $code);
    }

    /**
     * Why the host needs the model to read this entry.
     *
     * @return list<string> words, times, standalone_variable
     */
    public static function triggers(string $text, array $item): array
    {
        $vars = self::variables($item);
        $out = [];
        // A word: two or more letters in a row that are not just the item's variables written together.
        if (preg_match_all('/\p{L}{2,}/u', $text, $m)) {
            foreach ($m[0] as $run) {
                if ($vars === [] || preg_match('/[^' . preg_quote(implode('', $vars), '/') . ']/iu', $run) === 1) {
                    $out[] = 'words';
                    break;
                }
            }
        }
        if (str_contains($text, '×') || preg_match('/\btimes\b/iu', $text) === 1) {
            $out[] = 'times';
        }
        foreach ($vars as $v) {
            if (preg_match('/(?:\d|\p{L})\s+' . preg_quote($v, '/') . '\s+(?:\d|\p{L}|\()/iu', $text) === 1) {
                $out[] = 'standalone_variable';
                break;
            }
        }
        return $out;
    }

    /** The host's own line when an entry it cannot read gets no model reading (provider unavailable or the reading was rejected). */
    public static function fallbackLine(string $text, array $entry, ?string $checkerClarification): string
    {
        $normalised = self::normalise($text);
        if (str_ends_with(trim($text), '?') && preg_match('/\p{L}{2,}/u', $normalised) === 1) {
            return self::LINE_QUESTION;
        }
        if (preg_match('/^(?:(?:negative|minus) )?(?:' . implode('|', self::NUMBER_WORDS) . ')$/', $normalised) === 1) {
            return self::LINE_NUMBER_WORD;
        }
        if (in_array('standalone_variable', $entry['triggers'] ?? [], true)) {
            return self::LINE_X_AS_TIMES;
        }
        return $checkerClarification ?? self::LINE_UNREADABLE;
    }

    /** The item's variables: an expression item's declared variables, or x for a one-variable item in x. @return list<string> */
    public static function variables(array $item): array
    {
        $declared = $item['objects']['variables'] ?? null;
        if (is_array($declared) && $declared !== [] && $item['response_type'] === 'expression') {
            return array_values($declared);
        }
        return self::inX($item['prompt'] ?? '') ? ['x'] : [];
    }

    /** The item's variable when it has exactly one. */
    private static function singleVariable(array $item): ?string
    {
        if ($item['response_type'] === 'classification') {
            return null;
        }
        $vars = self::variables($item);
        return count($vars) === 1 ? $vars[0] : null;
    }

    /** A one-variable item in x: the question names x and no other letter variable. */
    private static function inX(string $prompt): bool
    {
        if (preg_match('/(?<![\p{L}])x(?![\p{L}])/u', $prompt) !== 1) {
            return false;
        }
        // Other single-letter variables (a word of one letter other than x, "a" and "I") would make it a different item.
        preg_match_all('/(?<![\p{L}])([b-hj-wyz])(?![\p{L}])/u', $prompt, $m);
        return $m[1] === [];
    }
}
