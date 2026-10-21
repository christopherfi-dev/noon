<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

use AlgebraEngine\Math\Ast\Node;

/** Exact solution of a linear equation in one variable. */
final class LinearEquation
{
    public static function solve(Node $lhs, Node $rhs, string $variable = 'x'): LinearSolution
    {
        $p = Polynomial::fromNode($lhs)->sub(Polynomial::fromNode($rhs));
        foreach ($p->variables() as $v) {
            if ($v !== $variable) {
                throw new MathInputError('NOT_LINEAR');
            }
        }
        if ($p->degreeIn($variable) > 1) {
            throw new MathInputError('NOT_LINEAR');
        }
        $a = $p->coefficientOf($variable);
        $b = $p->coefficientOf('');
        if (!$a->isZero()) {
            return new LinearSolution('UNIQUE', $b->neg()->div($a));
        }
        return new LinearSolution($b->isZero() ? 'ALL' : 'NONE');
    }
}
