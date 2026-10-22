<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

final class LinearSolution
{
    /** @param 'UNIQUE'|'NONE'|'ALL' $kind */
    public function __construct(public string $kind, public ?Rational $value = null)
    {
    }
}
