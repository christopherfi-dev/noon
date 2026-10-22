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

    /**
     * Round 5.1 (P3): claims about support that only the host may make. Rejected in model
     * text; host lines are exempt ("Here is one to try on your own." is true there).
     */
    public const SUPPORT_CLAIMS = [
        'on your own' => 'on your own',
        'without help' => 'without help',
        'independently' => 'independently',
        'by yourself' => 'by yourself',
        'no help' => 'no help',
    ];

    /** @return list<string> listed terms found in $text */
    public static function internalTerms(string $text): array
    {
        return self::find($text, self::INTERNAL_TERMS);
    }

    /** Terms rejected in model-written learner text: the internal terms plus the support claims. @return list<string> */
    public static function modelTextTerms(string $text): array
    {
        return array_merge(self::find($text, self::INTERNAL_TERMS), self::find($text, self::SUPPORT_CLAIMS));
    }

    /** @param array<string,string> $terms @return list<string> */
    private static function find(string $text, array $terms): array
    {
        $found = [];
        foreach ($terms as $term => $pattern) {
            $words = str_replace(' ', '\s+', $pattern);
            if (preg_match('/(?<![\p{L}\p{N}_])(?:' . $words . ')(?![\p{L}\p{N}_])/iu', $text)) {
                $found[] = $term;
            }
        }
        return $found;
    }
}
