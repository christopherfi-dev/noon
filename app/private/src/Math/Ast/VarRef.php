<?php
declare(strict_types=1);

namespace AlgebraEngine\Math\Ast;

final class VarRef extends Node
{
    public function __construct(public readonly string $name)
    {
    }
}
