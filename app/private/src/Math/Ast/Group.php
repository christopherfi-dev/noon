<?php
declare(strict_types=1);

namespace AlgebraEngine\Math\Ast;

/** Explicit parentheses written by the author of the expression; kept for form checks. */
final class Group extends Node
{
    public function __construct(public readonly Node $inner)
    {
    }
}
