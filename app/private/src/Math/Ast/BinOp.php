<?php
declare(strict_types=1);

namespace AlgebraEngine\Math\Ast;

/** Binary + - * /. $implicit marks juxtaposition such as 3x or 3(x+2). */
final class BinOp extends Node
{
    public function __construct(
        public string $op,
        public Node $left,
        public Node $right,
        public bool $implicit = false,
    ) {
    }
}
