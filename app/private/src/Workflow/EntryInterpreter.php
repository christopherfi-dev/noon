<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

/**
 * Round 5, R5-C: stuck and off-task entries in the answer box. Pure; the
 * engine decides what each kind does. No kind here is ever scored as
 * incorrect, and an entry the item's checker can already read is never
 * reinterpreted.
 *
 * Order: an entry the checker reads as it stands; a readable answer with a
 * trailing "?" (read without it); then the table, matched on the normalised
 * entry (lowercase, trimmed, spaces collapsed, apostrophes removed, trailing
 * . ! ? stripped). The lists are used exactly as written in the round 5
 * instructions.
 */
final class EntryInterpreter
{
    public const STUCK = ['idk', 'i dont know', 'dont know', 'dunno', 'no idea', 'no clue', 'not sure', 'im stuck', 'stuck', 'help'];
    public const ASK_FOR_ANSWER = ['can you just tell me the answer', 'just tell me the answer', 'tell me the answer', 'whats the answer', 'give me the answer'];
    public const NUMBER_WORDS = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty'];

    /** Host lines, exactly as written in the round 5 table. */
    public const LINE_ASK_FOR_ANSWER = 'I won\'t give the answer, but here is a smaller step.';
    public const LINE_QUESTION = 'This box is for your answer. If you\'re stuck, type help.';
    public const LINE_NUMBER_WORD = 'Write the number with digits.';

    public static function normalise(string $text): string
    {
        $s = mb_strtolower($text, 'UTF-8');
        $s = str_replace(["'", "\u{2019}", "\u{2018}", '`', "\u{00B4}"], '', $s);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));
        return trim(rtrim($s, '.!?'));
    }

    /**
     * @param callable(string):bool $readable true when the active item's checker can read the text (any verdict but UNSCORABLE)
     * @param array{response_type:string,prompt:string} $item
     * @return array{kind:string,read_as:?string,normalised:string}
     *   kind ANSWER (score read_as), TRAILING_QUESTION_MARK (score read_as), CAPITAL_X (score read_as),
     *   STUCK, ASK_FOR_ANSWER, QUESTION_TO_TUTOR, NUMBER_WORD (not scored)
     */
    public static function interpret(string $text, array $item, callable $readable): array
    {
        $normalised = self::normalise($text);
        $raw = trim($text);
        if ($readable($text)) {
            return ['kind' => 'ANSWER', 'read_as' => $text, 'normalised' => $normalised];
        }
        $withoutMark = rtrim(rtrim($raw, '?'));
        $endsWithMark = str_ends_with($raw, '?');
        if ($endsWithMark && $withoutMark !== '' && $readable($withoutMark)) {
            return ['kind' => 'TRAILING_QUESTION_MARK', 'read_as' => $withoutMark, 'normalised' => $normalised];
        }
        if (in_array($normalised, self::STUCK, true) || ($raw !== '' && preg_match('/^\?+$/', $raw) === 1)) {
            return ['kind' => 'STUCK', 'read_as' => null, 'normalised' => $normalised];
        }
        if (in_array($normalised, self::ASK_FOR_ANSWER, true)) {
            return ['kind' => 'ASK_FOR_ANSWER', 'read_as' => null, 'normalised' => $normalised];
        }
        if ($endsWithMark && preg_match('/\p{L}{2,}/u', $normalised) === 1) {
            return ['kind' => 'QUESTION_TO_TUTOR', 'read_as' => null, 'normalised' => $normalised];
        }
        if (in_array($item['response_type'], ['scalar', 'expression'], true)
            && preg_match('/^(?:(?:negative|minus) )?(?:' . implode('|', self::NUMBER_WORDS) . ')$/', $normalised) === 1) {
            return ['kind' => 'NUMBER_WORD', 'read_as' => null, 'normalised' => $normalised];
        }
        if ($item['response_type'] === 'scalar' && self::inX($item['prompt']) && preg_match('/^X\s*=\s*(.+)$/u', $withoutMark, $m) === 1) {
            $asX = 'x = ' . trim($m[1]);
            if ($readable($asX)) {
                return ['kind' => 'CAPITAL_X', 'read_as' => $asX, 'normalised' => $normalised];
            }
        }
        return ['kind' => 'ANSWER', 'read_as' => $text, 'normalised' => $normalised];
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
