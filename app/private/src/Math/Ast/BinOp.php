<?php
declare(strict_types=1);

namespace AlgebraEngine\Math\Ast;

/** Binary + - * /. $implicit marks juxtaposition such as 3x or 3(x+2). */
final class BinOp extends Node
{
    public function __construct(
        public readonly string $op,
        public readonly Node $left,
        public readonly Node $right,
        public readonly bool $implicit = false,
    ) {
    }
}
