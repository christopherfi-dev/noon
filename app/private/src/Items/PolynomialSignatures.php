<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;

final class PolynomialSignatures
{
    /**
     * @param list<string> $vars
     * @param list<array{0:string,1:string,2:?string,3:string}> $patterns exact-identity patterns
     * @param array{0:string,1:string,2:?string,3:string}|null $structural same monomials as the key, some coefficient wrong
     * @return list<array{code:string,label:string,branch:?string}>
     */
    public static function match(CheckOutcome $outcome, array $vars, array $patterns, ?array $structural = null): array
    {
        if ($outcome->verdict !== 'INCORRECT' || $outcome->normalizedResponse === null) {
            return [];
        }
        try {
            $r = Polynomial::fromNode(ExpressionParser::parse($outcome->normalizedResponse, $vars));
        } catch (MathInputError) {
            return [];
        }
        $out = [];
        foreach ($patterns as [$code, $label, $branch, $expr]) {
            if ($r->equals(Polynomial::fromNode(ExpressionParser::parse($expr, $vars)))) {
                $out[] = ['code' => $code, 'label' => $label, 'branch' => $branch];
            }
        }
        if ($out === [] && $structural !== null) {
            [$code, $label, $branch, $expr] = $structural;
            $key = Polynomial::fromNode(ExpressionParser::parse($expr, $vars));
            if (self::sameMonomials($r, $key) && self::sharesACoefficient($r, $key)) {
                $out[] = ['code' => $code, 'label' => $label, 'branch' => $branch];
            }
        }
        return $out;
    }

    /** Monomial keys of a polynomial of degree <= 1 (constant key is ''). @return list<string> */
    private static function monomials(Polynomial $p): array
    {
        $keys = [];
        foreach (array_merge([''], $p->variables()) as $k) {
            if (!$p->coefficientOf($k)->isZero()) {
                $keys[] = $k;
            }
        }
        sort($keys);
        return $keys;
    }

    private static function sameMonomials(Polynomial $a, Polynomial $b): bool
    {
        return self::monomials($a) === self::monomials($b) && $a->degree() <= 1 && $b->degree() <= 1;
    }

    private static function sharesACoefficient(Polynomial $a, Polynomial $b): bool
    {
        foreach (self::monomials($b) as $k) {
            if ($a->coefficientOf($k)->equals($b->coefficientOf($k))) {
                return true;
            }
        }
        return false;
    }
}
