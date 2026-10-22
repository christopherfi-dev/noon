<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

/**
 * Structural features of an item that a repair must keep (round 4, R4-B):
 * the first guided problem, and the first fresh guided problem after it,
 * practise the pattern the learner failed.
 *
 * - N03–N06: the sign of each operand.
 * - E06: the sign of the constant inside the parentheses.
 * - Q04–Q07: the equation form and the sign of each constant.
 */
final class StructuralFeatures
{
    /** @return array<string,string> feature => value; empty for templates without kept features */
    public static function of(string $templateId, array $p): array
    {
        $sign = static fn ($v): string => !is_int($v) ? '?' : ($v > 0 ? '+' : ($v < 0 ? '-' : '0'));
        return match ($templateId) {
            'SIGNED_ADD', 'SIGNED_SUBTRACT', 'SIGNED_MULTIPLY', 'SIGNED_DIVIDE' => ['sign_a' => $sign($p['a'] ?? null), 'sign_b' => $sign($p['b'] ?? null)],
            'POSITIVE_DISTRIBUTION', 'DISTRIBUTION_CONTRAST' => ['inside_constant_sign' => $sign($p['b'] ?? null)],
            'DISTRIBUTION_TRANSFER' => ['inside_constant_sign' => $sign($p['q'] ?? null)],
            'ONE_STEP_ADD' => ['form' => 'x+a=b', 'sign_a' => $sign($p['a'] ?? null), 'sign_b' => $sign($p['b'] ?? null)],
            'ONE_STEP_MULTIPLY' => ['form' => 'ax=b', 'sign_a' => $sign($p['a'] ?? null), 'sign_b' => $sign($p['b'] ?? null)],
            'TWO_STEP' => ['form' => 'ax+b=c', 'sign_a' => $sign($p['a'] ?? null), 'sign_b' => $sign($p['b'] ?? null), 'sign_c' => $sign($p['c'] ?? null)],
            'GROUPED_LINEAR', 'GROUPED_FIRST_STEP_CONTRAST' => ['form' => 'a(x+b)=c', 'sign_a' => $sign($p['a'] ?? null), 'sign_b' => $sign($p['b'] ?? null), 'sign_c' => $sign($p['c'] ?? null)],
            'GROUPED_LINEAR_TRANSFER' => ['form' => ($p['form'] ?? null) === 'SWAPPED' ? 'c=a(x+b)' : 'a(x+b)+d=c',
                'sign_a' => $sign($p['a'] ?? null), 'sign_b' => $sign($p['b'] ?? null), 'sign_c' => $sign($p['c'] ?? null)],
            default => [],
        };
    }

    /**
     * Kept features that a candidate does not match, as "feature must be value".
     * A feature the candidate's template does not have at all is not a mismatch.
     *
     * @param array<string,string> $keep
     * @return list<string>
     */
    public static function mismatches(array $keep, string $templateId, array $params): array
    {
        $have = self::of($templateId, $params);
        $out = [];
        foreach ($keep as $k => $v) {
            if (array_key_exists($k, $have) && $have[$k] !== $v) {
                $out[] = $k . ' must be ' . $v;
            }
        }
        return $out;
    }
}
