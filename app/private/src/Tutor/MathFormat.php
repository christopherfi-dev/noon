<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Math\Ast\BinOp;
use AlgebraEngine\Math\Ast\Group;
use AlgebraEngine\Math\Ast\Neg;
use AlgebraEngine\Math\Ast\Node;
use AlgebraEngine\Math\Ast\Num;
use AlgebraEngine\Math\Ast\Pow;
use AlgebraEngine\Math\Ast\VarRef;

/**
 * Learner-facing display of a parsed spec: Unicode minus, × and ÷ between
 * numbers, juxtaposition for a number times a letter or a group. It only
 * prints what was parsed; nothing is simplified or evaluated.
 */
final class MathFormat
{
    public static function node(Node $n): string
    {
        if ($n instanceof Num) {
            return Fmt::n($n->value);
        }
        if ($n instanceof VarRef) {
            return $n->name;
        }
        if ($n instanceof Group) {
            return '(' . self::node($n->inner) . ')';
        }
        if ($n instanceof Neg) {
            return Fmt::MINUS . self::node($n->operand);
        }
        if ($n instanceof Pow) {
            return self::node($n->base) . ($n->exponent === 2 ? '²' : ($n->exponent === 3 ? '³' : '^' . $n->exponent));
        }
        if ($n instanceof BinOp) {
            $l = self::node($n->left);
            $r = self::node($n->right);
            // A negative right operand of a binary operation is shown in parentheses: 3 + (−4).
            if ($n->right instanceof Neg) {
                $r = '(' . $r . ')';
            }
            switch ($n->op) {
                case '+':
                    return $l . ' + ' . $r;
                case '-':
                    return $l . ' ' . Fmt::MINUS . ' ' . $r;
                case '/':
                    return $l . ' ÷ ' . $r;
                case '*':
                    $joinable = $n->right instanceof VarRef || $n->right instanceof Group || $n->right instanceof Pow
                        || ($n->right instanceof BinOp && $n->right->implicit);
                    if ($joinable && !($n->left instanceof BinOp && !$n->left->implicit)) {
                        return $l . $r;
                    }
                    if ($n->left instanceof Neg) {
                        $l = '(' . $l . ')';
                    }
                    return $l . ' × ' . $r;
            }
        }
        return '?';
    }

    /** @param array{0:Node,1:Node} $sides */
    public static function equation(array $sides): string
    {
        return self::node($sides[0]) . ' = ' . self::node($sides[1]);
    }
}
