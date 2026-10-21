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

    public static function contains(string $skillId): bool
    {
        return in_array($skillId, self::SKILLS, true);
    }

    public static function threeVariant(string $skillId): bool
    {
        return in_array($skillId, self::THREE_VARIANT_SKILLS, true);
    }
}
