<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;

abstract class AbstractTemplate implements Template
{
    public function variantId(): string
    {
        return 'constructed';
    }

    public function inputHelp(): string
    {
        return match ($this->responseType()) {
            'scalar' => 'Enter a single number, for example 7, -3 or 5/2.',
            'expression' => 'Enter an expression using x, for example 2x + 5. Use * or nothing for multiplication.',
            'classification' => 'Choose every option that applies (A, B, C, D).',
            default => '',
        };
    }

    public function checkingScope(): string
    {
        return match ($this->responseType()) {
            'scalar' => 'Exact rational value of the response against the host-derived key; method and reasoning not assessed.',
            'expression' => 'Algebraic equivalence by canonical polynomial form and the requested expanded form, checked separately.',
            'classification' => 'Exact set of selected options against a host-derived key; written justification not assessed.',
            default => 'Unspecified.',
        };
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return null;
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        return [];
    }

    /** @return array{kind:string,expected:string,requested_form:string,units:null,tolerance:null,rubric:list<string>} */
    protected function scalarSpec(Rational $expected, string $form = 'single_number'): array
    {
        return [
            'kind' => 'exact_rational',
            'expected' => $expected->toString(),
            'requested_form' => $form,
            'units' => null,
            'tolerance' => null,
            'rubric' => [],
        ];
    }

    /**
     * Compare an incorrect scalar response with signature values.
     *
     * @param list<array{0:string,1:string,2:?string,3:Rational|callable}> $signatures code, label, branch, value or predicate(Rational): bool
     * @return list<array{code:string,label:string,branch:?string}>
     */
    protected function matchScalar(CheckOutcome $outcome, array $signatures): array
    {
        if ($outcome->verdict !== 'INCORRECT' || $outcome->normalizedResponse === null) {
            return [];
        }
        try {
            $r = ScalarParser::parse($outcome->normalizedResponse);
        } catch (MathInputError) {
            return [];
        }
        $out = [];
        foreach ($signatures as [$code, $label, $branch, $test]) {
            $hit = $test instanceof Rational ? $r->equals($test) : (bool) $test($r);
            if ($hit) {
                $out[] = ['code' => $code, 'label' => $label, 'branch' => $branch];
            }
        }
        return $out;
    }

    /** @param list<string> $errors */
    protected static function needInt(array $p, string $key, int $min, int $max, array &$errors, array $exclude = []): ?int
    {
        if (!array_key_exists($key, $p) || !is_int($p[$key])) {
            $errors[] = "parameters.$key must be an integer";
            return null;
        }
        $v = $p[$key];
        if ($v < $min || $v > $max) {
            $errors[] = "parameters.$key must be between $min and $max";
            return null;
        }
        if (in_array($v, $exclude, true)) {
            $errors[] = "parameters.$key must not be " . implode(' or ', $exclude);
            return null;
        }
        return $v;
    }

    /** @param list<string> $allowed @param list<string> $errors */
    protected static function onlyKeys(array $p, array $allowed, array &$errors): void
    {
        foreach (array_keys($p) as $k) {
            if (!in_array($k, $allowed, true)) {
                $errors[] = "parameters.$k is not a parameter of this template";
            }
        }
    }

    /** @param list<Rational> $values */
    protected static function distinct(array $values): bool
    {
        $seen = [];
        foreach ($values as $v) {
            $k = $v->toString();
            if (isset($seen[$k])) {
                return false;
            }
            $seen[$k] = true;
        }
        return true;
    }

    protected static function r(int $n, int $d = 1): Rational
    {
        return Rational::of($n, $d);
    }

    /** Generate by rejection sampling until validate() passes. */
    protected function sample(callable $draw, ?array $avoid = null, int $tries = 400): array
    {
        for ($i = 0; $i < $tries; $i++) {
            $p = $draw();
            if ($this->validate($p) !== []) {
                continue;
            }
            if ($avoid !== null && !$this->variesFrom($p, $avoid)) {
                continue;
            }
            return $p;
        }
        throw new \RuntimeException('Could not generate parameters for ' . $this->id());
    }

    /** A fresh item must differ from the avoided one in at least its leading parameters. */
    protected function variesFrom(array $p, array $avoid): bool
    {
        foreach ($p as $k => $v) {
            if (is_int($v) && isset($avoid[$k]) && $avoid[$k] === $v) {
                return false;
            }
        }
        return true;
    }
}
