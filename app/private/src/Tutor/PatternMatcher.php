<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Math\Ast\BinOp;
use AlgebraEngine\Math\Ast\Group;
use AlgebraEngine\Math\Ast\Neg;
use AlgebraEngine\Math\Ast\Node;
use AlgebraEngine\Math\Ast\Num;
use AlgebraEngine\Math\Ast\VarRef;

/**
 * Round 6 §4.2: the recipes used in reverse. A parsed spec is matched
 * against a recipe's exact structure; the numbers it holds become the
 * recipe's parameters, and the recipe's own validate() decides. A check
 * problem must match a recipe of its skill; a matched practice problem also
 * gets that recipe's error signatures. Contrast (choice) recipes have no
 * typed spec and are never matched here: choice problems come only from the
 * recipes themselves.
 */
final class PatternMatcher
{
    /** Recipes per spec kind, in the order they are tried. */
    public const BY_KIND = [
        'linear_equation' => ['ONE_STEP_ADD', 'ONE_STEP_MULTIPLY', 'TWO_STEP', 'GROUPED_LINEAR', 'GROUPED_LINEAR_TRANSFER'],
        'expand' => ['POSITIVE_DISTRIBUTION', 'DISTRIBUTION_TRANSFER'],
        'signed_arithmetic' => ['SIGNED_ADD', 'SIGNED_SUBTRACT', 'SIGNED_MULTIPLY', 'SIGNED_DIVIDE', 'WHOLE_MULTIPLY_DIVIDE'],
        'numeric_step' => ['SIGNED_ADD', 'SIGNED_SUBTRACT', 'SIGNED_MULTIPLY', 'SIGNED_DIVIDE', 'WHOLE_MULTIPLY_DIVIDE'],
    ];

    /** Learner-independent description of each typed pattern, for the AI's context. */
    public const DESCRIPTIONS = [
        'ONE_STEP_ADD' => 'Q04: x + a = b (a, b integers; a not 0)',
        'ONE_STEP_MULTIPLY' => 'Q05: ax = b (integer solution)',
        'TWO_STEP' => 'Q06: ax + b = c (integer solution)',
        'GROUPED_LINEAR' => 'Q07 solve: a(x + b) = c (integer solution)',
        'GROUPED_LINEAR_TRANSFER' => 'Q07 new form: a(x + b) + d = c, or c = a(x + b)',
        'POSITIVE_DISTRIBUTION' => 'E06 expansion: a(x + b), a and b positive',
        'DISTRIBUTION_TRANSFER' => 'E06 new form: a(px + q) or a(x + y + q)',
        'SIGNED_ADD' => 'N03: a + b, at least one negative',
        'SIGNED_SUBTRACT' => 'N04: a - b, at least one negative',
        'SIGNED_MULTIPLY' => 'N05: a * b, at least one negative',
        'SIGNED_DIVIDE' => 'N06: a / b with a whole-number answer, at least one negative',
        'WHOLE_MULTIPLY_DIVIDE' => 'N18: a * b or (a*b) / b with whole numbers 2 to 12',
    ];

    public function __construct(private ItemFactory $factory)
    {
    }

    /**
     * The first recipe (of $skillId when given) whose structure and bounds the
     * spec matches, or null.
     *
     * @param list<Node> $nodes [lhs, rhs] for an equation, [node] otherwise
     * @return array{template_id:string,skill_id:string,variant_id:string,params:array<string,mixed>}|null
     */
    public function match(string $kind, array $nodes, ?string $skillId = null): ?array
    {
        foreach (self::BY_KIND[$kind] ?? [] as $templateId) {
            $template = $this->factory->get($templateId);
            if ($skillId !== null && $template->skillId() !== $skillId) {
                continue;
            }
            $params = self::extract($templateId, $nodes);
            if ($params !== null && $template->validate($params) === []) {
                return ['template_id' => $templateId, 'skill_id' => $template->skillId(), 'variant_id' => $template->variantId(), 'params' => $params];
            }
        }
        return null;
    }

    /**
     * The recipe's parameters read off the spec's structure, or null when the
     * structure differs. Bounds are not checked here (validate() does that).
     *
     * @param list<Node> $n
     * @return array<string,mixed>|null
     */
    public static function extract(string $templateId, array $n): ?array
    {
        $eq = count($n) === 2;
        switch ($templateId) {
            case 'ONE_STEP_ADD':
                $s = $eq ? self::sum($n[0]) : null;
                return $s !== null && self::isVar($s[0], 'x') && ($b = self::int($n[1])) !== null ? ['a' => $s[1], 'b' => $b] : null;
            case 'ONE_STEP_MULTIPLY':
                $p = $eq ? self::product($n[0]) : null;
                return $p !== null && self::isVar($p[1], 'x') && ($b = self::int($n[1])) !== null ? ['a' => $p[0], 'b' => $b] : null;
            case 'TWO_STEP':
                $s = $eq ? self::sum($n[0]) : null;
                $p = $s !== null ? self::product($s[0]) : null;
                return $p !== null && self::isVar($p[1], 'x') && ($c = self::int($n[1])) !== null ? ['a' => $p[0], 'b' => $s[1], 'c' => $c] : null;
            case 'GROUPED_LINEAR':
                $g = $eq ? self::grouped($n[0]) : null;
                return $g !== null && ($c = self::int($n[1])) !== null ? ['a' => $g[0], 'b' => $g[1], 'c' => $c] : null;
            case 'GROUPED_LINEAR_TRANSFER':
                if (!$eq) {
                    return null;
                }
                $s = self::sum($n[0]);
                $g = $s !== null ? self::grouped($s[0]) : null;
                if ($g !== null && ($c = self::int($n[1])) !== null) {
                    return ['form' => 'PLUS_D', 'a' => $g[0], 'b' => $g[1], 'c' => $c, 'd' => $s[1]];
                }
                $g = self::grouped($n[1]);
                return $g !== null && ($c = self::int($n[0])) !== null ? ['form' => 'SWAPPED', 'a' => $g[0], 'b' => $g[1], 'c' => $c, 'd' => 0] : null;
            case 'POSITIVE_DISTRIBUTION':
                $g = !$eq ? self::grouped($n[0]) : null;
                return $g !== null ? ['a' => $g[0], 'b' => $g[1]] : null;
            case 'DISTRIBUTION_TRANSFER':
                if ($eq) {
                    return null;
                }
                $p = self::product($n[0]);
                if ($p === null || !($p[1] instanceof Group)) {
                    return null;
                }
                $s = self::sum($p[1]->inner);
                if ($s === null) {
                    return null;
                }
                $inner = self::product($s[0]);
                if ($inner !== null && self::isVar($inner[1], 'x')) {
                    return ['form' => 'COEFF_INSIDE', 'a' => $p[0], 'p' => $inner[0], 'q' => $s[1]];
                }
                $xy = $s[0];
                return $xy instanceof BinOp && $xy->op === '+' && self::isVar($xy->left, 'x') && self::isVar($xy->right, 'y')
                    ? ['form' => 'THREE_TERMS', 'a' => $p[0], 'p' => 1, 'q' => $s[1]] : null;
            case 'SIGNED_ADD':
            case 'SIGNED_SUBTRACT':
            case 'SIGNED_MULTIPLY':
            case 'SIGNED_DIVIDE':
                $op = ['SIGNED_ADD' => '+', 'SIGNED_SUBTRACT' => '-', 'SIGNED_MULTIPLY' => '*', 'SIGNED_DIVIDE' => '/'][$templateId];
                $b = !$eq ? self::binary($n[0], $op) : null;
                return $b !== null ? ['a' => $b[0], 'b' => $b[1]] : null;
            case 'WHOLE_MULTIPLY_DIVIDE':
                if ($eq) {
                    return null;
                }
                if (($b = self::binary($n[0], '*')) !== null) {
                    return ['op' => 'multiply', 'a' => $b[0], 'b' => $b[1]];
                }
                if (($b = self::binary($n[0], '/')) !== null && $b[1] !== 0 && $b[0] % $b[1] === 0) {
                    return ['op' => 'divide', 'a' => intdiv($b[0], $b[1]), 'b' => $b[1]];
                }
                return null;
        }
        return null;
    }

    /** A whole number written as a literal, a negated literal, or either in parentheses. */
    public static function int(Node $n): ?int
    {
        if ($n instanceof Group) {
            return self::int($n->inner);
        }
        if ($n instanceof Neg) {
            $v = $n->operand instanceof Num ? self::int($n->operand) : null;
            return $v === null ? null : -$v;
        }
        if ($n instanceof Num && $n->value->isInteger()) {
            return $n->value->num();
        }
        return null;
    }

    private static function isVar(Node $n, string $name): bool
    {
        return $n instanceof VarRef && $n->name === $name;
    }

    /** "<left> + k" or "<left> − k" as [left, signed k]. @return array{0:Node,1:int}|null */
    private static function sum(Node $n): ?array
    {
        if (!($n instanceof BinOp) || ($n->op !== '+' && $n->op !== '-')) {
            return null;
        }
        $k = self::int($n->right);
        if ($k === null) {
            return null;
        }
        return [$n->left, $n->op === '-' ? -$k : $k];
    }

    /** "a·<rest>" with a whole-number factor written first. @return array{0:int,1:Node}|null */
    private static function product(Node $n): ?array
    {
        if (!($n instanceof BinOp) || $n->op !== '*') {
            return null;
        }
        $a = self::int($n->left);
        return $a === null ? null : [$a, $n->right];
    }

    /** "a(x ± b)". @return array{0:int,1:int}|null */
    private static function grouped(Node $n): ?array
    {
        $p = self::product($n);
        if ($p === null || !($p[1] instanceof Group)) {
            return null;
        }
        $s = self::sum($p[1]->inner);
        return $s !== null && self::isVar($s[0], 'x') ? [$p[0], $s[1]] : null;
    }

    /** "a <op> b" with whole-number operands. @return array{0:int,1:int}|null */
    private static function binary(Node $n, string $op): ?array
    {
        if ($n instanceof Group) {
            $n = $n->inner;
        }
        if (!($n instanceof BinOp) || $n->op !== $op) {
            return null;
        }
        $a = self::int($n->left);
        $b = self::int($n->right);
        return $a === null || $b === null ? null : [$a, $b];
    }
}
