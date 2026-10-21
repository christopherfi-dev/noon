<?php
declare(strict_types=1);

namespace AlgebraEngine\Math\Ast;

/** Power with a non-negative integer literal exponent. */
final class Pow extends Node
{
    public function __construct(public readonly Node $base, public readonly int $exponent)
    {
    }
}
