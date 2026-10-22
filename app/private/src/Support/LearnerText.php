<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

/**
 * Round 5, R5-B: internal vocabulary that never appears in text a learner
 * reads during tutoring, whether the model or the host wrote it. Terms match
 * case-insensitively as whole words or whole phrases ("ghost" never matches
 * "host"); a plural form of a listed noun counts as the term.
 */
final class LearnerText
{
    public const INTERNAL_TERMS = [
        'expected value' => 'expected values?',
        'expected answer' => 'expected answers?',
        'answer key' => 'answer keys?',
        'matched the key' => 'matched the key',
        'guided step' => 'guided steps?',
        'host' => 'hosts?',
        'assisted' => 'assisted',
        'verdict' => 'verdicts?',
        'criterion' => 'criterion|criteria',
    ];

    /** @return list<string> listed terms found in $text */
    public static function internalTerms(string $text): array
    {
        $found = [];
        foreach (self::INTERNAL_TERMS as $term => $pattern) {
            $words = str_replace(' ', '\s+', $pattern);
            if (preg_match('/(?<![\p{L}\p{N}_])(?:' . $words . ')(?![\p{L}\p{N}_])/iu', $text)) {
                $found[] = $term;
            }
        }
        return $found;
    }
}
