<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

use AlgebraEngine\Math\Ast\BinOp;
use AlgebraEngine\Math\Ast\Group;
use AlgebraEngine\Math\Ast\Neg;
use AlgebraEngine\Math\Ast\Node;
use AlgebraEngine\Math\Ast\Num;
use AlgebraEngine\Math\Ast\Pow;
use AlgebraEngine\Math\Ast\VarRef;

/**
 * Checks the written form of an expression, separately from its equivalence.
 * Reasons are codes; null means the requested form is met.
 */
final class FormInspector
{
    /**
     * Expanded and simplified: no grouped sum is multiplied, divided, raised or negated,
     * each term has at most one numeric factor, like terms are combined and no term is 0.
     */
    public static function expandedSimplified(Node $node): ?string
    {
        $node = self::stripGroups($node);
        $terms = [];
        self::collectTerms($node, false, $terms);

        $infos = [];
        foreach ($terms as [$term, $subtracted]) {
            if ($subtracted && self::isSum($term)) {
                return 'STILL_GROUPED';
            }
            $info = self::analyzeTerm($term);
            if ($info['grouped']) {
                return 'STILL_GROUPED';
            }
            $infos[] = $info;
        }
        foreach ($infos as $info) {
            if ($info['numeric'] > 1) {
                return 'NUMBERS_NOT_MULTIPLIED';
            }
        }
        if (count($infos) > 1) {
            foreach ($infos as $info) {
                if ($info['zero']) {
                    return 'ZERO_TERM';
                }
            }
        }
        $seen = [];
        foreach ($infos as $info) {
            if (isset($seen[$info['key']])) {
                return 'LIKE_TERMS_NOT_COMBINED';
            }
            $seen[$info['key']] = true;
        }
        return null;
    }

    /**
     * Factored: the top level (ignoring outer parentheses and a leading minus) is a product of
     * at least two factors, at least one being a parenthesised sum or a power of one.
     * Completeness of the factorisation and irreducibility of the factors are NOT checked.
     */
    public static function factored(Node $node): ?string
    {
        $node = self::stripGroups($node);
        while ($node instanceof Neg) {
            $node = self::stripGroups($node->operand);
        }
        if ($node instanceof Pow && $node->exponent >= 2 && self::isGroupedSum($node->base)) {
            return null;
        }
        if (!($node instanceof BinOp) || ($node->op !== '*' && $node->op !== '/')) {
            return 'NOT_A_PRODUCT';
        }
        $factors = [];
        self::collectFactors($node, $factors);
        if (count($factors) < 2) {
            return 'NOT_A_PRODUCT';
        }
        foreach ($factors as $f) {
            if (self::isGroupedSum($f) || ($f instanceof Pow && self::isGroupedSum($f->base))) {
                return null;
            }
        }
        return 'NOT_A_PRODUCT';
    }

    private static function stripGroups(Node $node): Node
    {
        while ($node instanceof Group) {
            $node = $node->inner;
        }
        return $node;
    }

    private static function isSum(Node $node): bool
    {
        $node = self::stripGroups($node);
        return $node instanceof BinOp && ($node->op === '+' || $node->op === '-');
    }

    private static function isGroupedSum(Node $node): bool
    {
        while ($node instanceof Neg) {
            $node = $node->operand;
        }
        return $node instanceof Group && self::isSum($node->inner);
    }

    /** @param list<array{0:Node,1:bool}> $terms */
    private static function collectTerms(Node $node, bool $subtracted, array &$terms): void
    {
        if ($node instanceof BinOp && ($node->op === '+' || $node->op === '-')) {
            self::collectTerms($node->left, $subtracted, $terms);
            self::collectTerms($node->right, $node->op === '-' ? !$subtracted : $subtracted, $terms);
            return;
        }
        // A parenthesised sum that is simply added is transparent; a subtracted one is not.
        if ($node instanceof Group && self::isSum($node) && !$subtracted) {
            self::collectTerms(self::stripGroups($node), $subtracted, $terms);
            return;
        }
        $terms[] = [$node, $subtracted];
    }

    /** @param list<Node> $factors */
    private static function collectFactors(Node $node, array &$factors): void
    {
        if ($node instanceof BinOp && ($node->op === '*' || $node->op === '/')) {
            self::collectFactors($node->left, $factors);
            self::collectFactors($node->right, $factors);
            return;
        }
        if ($node instanceof Group && !self::isSum($node)) {
            self::collectFactors($node->inner, $factors);
            return;
        }
        $factors[] = $node;
    }

    /** @return array{grouped:bool, numeric:int, zero:bool, key:string} */
    private static function analyzeTerm(Node $term): array
    {
        $info = ['grouped' => false, 'numeric' => 0, 'zero' => false, 'exps' => []];
        self::analyzeFactor($term, $info);
        $exps = array_filter($info['exps'], static fn (int $k): bool => $k > 0);
        ksort($exps);
        $parts = [];
        foreach ($exps as $v => $k) {
            $parts[] = $k === 1 ? $v : $v . '^' . $k;
        }
        return [
            'grouped' => $info['grouped'],
            'numeric' => $info['numeric'],
            'zero' => $info['zero'],
            'key' => implode('*', $parts),
        ];
    }

    /** @param array{grouped:bool, numeric:int, zero:bool, exps:array<string,int>} $info */
    private static function analyzeFactor(Node $node, array &$info, int $power = 1): void
    {
        if ($node instanceof Num) {
            $info['numeric']++;
            if ($node->value->isZero()) {
                $info['zero'] = true;
            }
            return;
        }
        if ($node instanceof VarRef) {
            $info['exps'][$node->name] = ($info['exps'][$node->name] ?? 0) + $power;
            return;
        }
        if ($node instanceof Neg) {
            if (self::isSum($node->operand)) {
                $info['grouped'] = true;
                return;
            }
            self::analyzeFactor($node->operand, $info, $power);
            return;
        }
        if ($node instanceof Group) {
            if (self::isSum($node->inner)) {
                $info['grouped'] = true;
                return;
            }
            self::analyzeFactor($node->inner, $info, $power);
            return;
        }
        if ($node instanceof Pow) {
            $base = self::stripGroups($node->base);
            if ($base instanceof VarRef) {
                $info['exps'][$base->name] = ($info['exps'][$base->name] ?? 0) + $power * $node->exponent;
                return;
            }
            if ($base instanceof Num) {
                // An unevaluated numeric power such as 2^3 still needs its numbers multiplied.
                $info['numeric'] += 2;
                return;
            }
            $info['grouped'] = true;
            return;
        }
        if ($node instanceof BinOp && $node->op === '*') {
            self::analyzeFactor($node->left, $info, $power);
            self::analyzeFactor($node->right, $info, $power);
            return;
        }
        if ($node instanceof BinOp && $node->op === '/') {
            self::analyzeFactor($node->left, $info, $power);
            if (!(self::stripGroups($node->right) instanceof Num)) {
                $info['grouped'] = true;
            }
            return;
        }
        // A sum nested inside a product.
        $info['grouped'] = true;
    }
}
