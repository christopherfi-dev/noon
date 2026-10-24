<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

/**
 * Round UI (U1): presentation helpers that name the problem a piece of
 * feedback is about. They only read text the learner has already seen (an
 * item's prompt and the learner's own answer); nothing here is evaluated.
 * The browser uses the same reading to keep equations on one line.
 */
final class ProblemText
{
    public const MAX_ANSWER = 40;

    /**
     * Runs of mathematical text: numbers, the variables x and y, operators and
     * parentheses, separated by at most one space.
     *
     * @return list<string>
     */
    public static function runs(string $text): array
    {
        $token = '(?:\d+(?:\.\d+)?|(?<![\p{L}])[xy](?![\p{L}]|-\p{L})|[+\-−×÷·=\/^()²])';
        preg_match_all('/' . $token . '(?: ?' . $token . ')*/u', $text, $m);
        $out = [];
        foreach ($m[0] as $run) {
            $run = self::trim($run);
            if ($run !== '' && preg_match('/[\dxy]/u', $run) && preg_match('/[+\-−×÷·=\/^()]/u', $run)) {
                $out[] = $run;
            }
        }
        return $out;
    }

    /** The item's main equation or expression: the longest run with "=", else the longest run; '' if none. */
    public static function main(string $prompt): string
    {
        $stem = preg_split('/\n(?=[A-D]\) )/u', $prompt)[0];
        $best = '';
        foreach (self::runs($stem) as $run) {
            $better = str_contains($run, '=') && !str_contains($best, '=')
                || (str_contains($run, '=') === str_contains($best, '=') && mb_strlen($run) > mb_strlen($best));
            if ($better) {
                $best = $run;
            }
        }
        return $best;
    }

    /**
     * "On 8(x + 5) = 64 you answered 13." for an answer the learner can no
     * longer see next to its problem; null when there is nothing to show.
     */
    public static function answerLine(string $prompt, string $responseType, string $answer): ?string
    {
        $answer = trim((string) preg_replace('/\s+/u', ' ', $answer));
        if ($answer === '') {
            return null;
        }
        if ($responseType === 'classification') {
            $letters = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/u', strtoupper($answer)) ?: [])));
            $shown = count($letters) > 1 ? implode(', ', array_slice($letters, 0, -1)) . ' and ' . end($letters) : ($letters[0] ?? $answer);
            $verb = 'you chose ' . $shown;
        } else {
            if (mb_strlen($answer) > self::MAX_ANSWER) {
                $answer = mb_substr($answer, 0, self::MAX_ANSWER - 1) . '…';
            }
            $verb = 'you answered ' . $answer;
        }
        $main = self::main($prompt);
        $line = $main === '' ? ucfirst($verb) . '.' : 'On ' . $main . ' ' . $verb . '.';
        return LearnerText::internalTerms($line) === [] ? $line : null;
    }

    /** Drop operators left dangling at either end ("= 4 for" gives "4"); keep a leading minus sign and balanced parentheses. */
    private static function trim(string $run): string
    {
        $run = trim($run);
        $run = (string) preg_replace('/^(?:[+=×÷·\/^)]\s?)+/u', '', $run);
        $run = (string) preg_replace('/(?:\s?[+\-−=×÷·\/^(])+$/u', '', $run);
        while ($run !== '' && substr_count($run, '(') > substr_count($run, ')') && str_starts_with($run, '(')) {
            $run = ltrim(mb_substr($run, 1));
        }
        while ($run !== '' && substr_count($run, ')') > substr_count($run, '(') && str_ends_with($run, ')')) {
            $run = rtrim(mb_substr($run, 0, -1));
        }
        return trim($run);
    }
}
