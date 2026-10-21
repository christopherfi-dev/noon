<?php
declare(strict_types=1);

namespace AlgebraEngine\Checking;

use AlgebraEngine\Math\Normalizer;

/** Exact-set check for "select every option that applies" items with lettered options. */
final class ClassificationChecker
{
    public const SCOPE = 'Exact set comparison of the selected option letters with the host-derived key; order-independent; no partial credit; reasoning not assessed.';

    /**
     * @param list<string> $expectedLetters
     * @param list<string> $allowedLetters
     */
    public static function check(array $expectedLetters, array $allowedLetters, string $response): CheckOutcome
    {
        $allowed = array_map('strtoupper', $allowedLetters);
        $selected = self::parseSelection($response, $allowed);
        if ($selected === null || $selected === []) {
            $first = $allowed[0] ?? 'A';
            $last = $allowed[count($allowed) - 1] ?? 'D';
            return new CheckOutcome(
                'UNSCORABLE',
                $selected === [] ? 'No option was selected.' : 'The response contained something other than option letters.',
                self::SCOPE,
                'Choose from the letters ' . $first . ' to ' . $last . ', separated by commas, for example ' . $first . ', ' . $last . '.',
            );
        }
        $expected = array_values(array_unique(array_map('strtoupper', $expectedLetters)));
        sort($expected);
        $normalized = implode(',', $selected);
        if ($selected === $expected) {
            return new CheckOutcome('CORRECT', 'The selected options match the host-derived key exactly.', self::SCOPE, null, $normalized);
        }
        return new CheckOutcome('INCORRECT', 'The selected options (' . $normalized . ') differ from the host-derived key.', self::SCOPE, null, $normalized);
    }

    /**
     * @param list<string> $allowed
     * @return list<string>|null null when an unknown token is present
     */
    private static function parseSelection(string $response, array $allowed): ?array
    {
        if (strlen($response) > 200) {
            return null;
        }
        $s = strtoupper(Normalizer::normalize($response));
        $s = preg_replace('/[,;\/&+().]/', ' ', $s) ?? '';
        $letters = [];
        foreach (preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if ($token === 'AND') {
                continue;
            }
            if (preg_match('/^[A-Z]+$/', $token) !== 1) {
                return null;
            }
            foreach (str_split($token) as $letter) {
                if (!in_array($letter, $allowed, true)) {
                    return null;
                }
                $letters[$letter] = true;
            }
        }
        $result = array_keys($letters);
        sort($result);
        return $result;
    }
}
