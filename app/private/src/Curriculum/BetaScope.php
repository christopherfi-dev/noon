<?php
declare(strict_types=1);

namespace AlgebraEngine\Curriculum;

/** Conductor Addendum Beta v1 §5: the ten skills in scope. */
final class BetaScope
{
    public const SKILLS = ['Q07', 'Q06', 'Q05', 'Q04', 'E06', 'N18', 'N03', 'N04', 'N05', 'N06'];
    public const DEFAULT_TARGET = 'Q07';
    /** Skills whose whole-node criterion uses the three-variant rule (D3). */
    public const THREE_VARIANT_SKILLS = ['Q07', 'E06'];
    public const VARIANTS = ['constructed', 'contrast', 'transfer'];
    /** Round UI (U2, U7): learner words for the three kinds of question in the three-variant goal. */
    public const VARIANT_KINDS = [
        'Q07' => ['constructed' => 'a solve', 'contrast' => 'a step choice', 'transfer' => 'a new kind of equation'],
        'E06' => ['constructed' => 'an expansion', 'contrast' => 'a choice question', 'transfer' => 'a new kind of expression'],
    ];

    /** "a solve, a step choice and a new kind of equation" @param list<string> $variants */
    public static function kinds(string $skillId, array $variants = self::VARIANTS): string
    {
        $words = array_values(array_map(fn ($v) => self::VARIANT_KINDS[$skillId][$v] ?? $v, $variants));
        return count($words) > 1 ? implode(', ', array_slice($words, 0, -1)) . ' and ' . end($words) : implode('', $words);
    }

    public static function contains(string $skillId): bool
    {
        return in_array($skillId, self::SKILLS, true);
    }

    public static function threeVariant(string $skillId): bool
    {
        return in_array($skillId, self::THREE_VARIANT_SKILLS, true);
    }
}
