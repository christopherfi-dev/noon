<?php
declare(strict_types=1);

namespace AlgebraEngine\Math\Ast;

use AlgebraEngine\Math\Rational;

final class Num extends Node
{
    public function __construct(public readonly Rational $value)
    {
    }
}
