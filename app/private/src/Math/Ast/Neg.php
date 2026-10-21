<?php
declare(strict_types=1);

namespace AlgebraEngine\Math\Ast;

final class Neg extends Node
{
    public function __construct(public readonly Node $operand)
    {
    }
}
