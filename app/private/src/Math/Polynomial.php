<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

use AlgebraEngine\Math\Ast\BinOp;
use AlgebraEngine\Math\Ast\Group;
use AlgebraEngine\Math\Ast\Neg;
use AlgebraEngine\Math\Ast\Node;
use AlgebraEngine\Math\Ast\Num;
use AlgebraEngine\Math\Ast\Pow;
use AlgebraEngine\Math\Ast\VarRef;

/**
 * Multivariate polynomial with exact rational coefficients in canonical form.
 * Two polynomials are equal exactly when their canonical term maps are equal, which is
 * an algebraic identity check, not agreement at sampled values.
 */
final class Polynomial
{
    public const MAX_DEGREE = 10;
    public const MAX_TERMS = 60;

    /**
     * @param array<string, array{0: Rational, 1: array<string,int>}> $terms monomial key => [coefficient, exponents]
     */
    private function __construct(private array $terms)
    {
    }

    public static function constant(Rational $c): self
    {
        return new self($c->isZero() ? [] : ['' => [$c, []]]);
    }

    public static function variable(string $name): self
    {
        return new self([$name => [Rational::fromInt(1), [$name => 1]]]);
    }

    public static function fromNode(Node $node): self
    {
        if ($node instanceof Num) {
            return self::constant($node->value);
        }
        if ($node instanceof VarRef) {
            return self::variable($node->name);
        }
        if ($node instanceof Group) {
            return self::fromNode($node->inner);
        }
        if ($node instanceof Neg) {
            return self::fromNode($node->operand)->neg();
        }
        if ($node instanceof Pow) {
            return self::fromNode($node->base)->pow($node->exponent);
        }
        if ($node instanceof BinOp) {
            $l = self::fromNode($node->left);
            $r = self::fromNode($node->right);
            return match ($node->op) {
                '+' => $l->add($r),
                '-' => $l->sub($r),
                '*' => $l->mul($r),
                '/' => $l->divideByConstant($r),
                default => throw new MathInputError('MALFORMED'),
            };
        }
        throw new MathInputError('MALFORMED');
    }

    public function add(self $o): self
    {
        $terms = $this->terms;
        foreach ($o->terms as $key => [$c, $exps]) {
            if (isset($terms[$key])) {
                $sum = $terms[$key][0]->add($c);
                if ($sum->isZero()) {
                    unset($terms[$key]);
                } else {
                    $terms[$key] = [$sum, $exps];
                }
            } else {
                $terms[$key] = [$c, $exps];
            }
        }
        return self::checked($terms);
    }

    public function sub(self $o): self
    {
        return $this->add($o->neg());
    }

    public function neg(): self
    {
        $terms = [];
        foreach ($this->terms as $key => [$c, $exps]) {
            $terms[$key] = [$c->neg(), $exps];
        }
        return new self($terms);
    }

    public function mul(self $o): self
    {
        $result = new self([]);
        foreach ($this->terms as [$c1, $e1]) {
            foreach ($o->terms as [$c2, $e2]) {
                $exps = $e1;
                foreach ($e2 as $v => $k) {
                    $exps[$v] = ($exps[$v] ?? 0) + $k;
                }
                if (array_sum($exps) > self::MAX_DEGREE) {
                    throw new MathInputError('TOO_LARGE');
                }
                $result = $result->add(new self([self::key($exps) => [$c1->mul($c2), $exps]]));
            }
        }
        return $result;
    }

    public function pow(int $exponent): self
    {
        if ($exponent < 0) {
            throw new MathInputError('EXPONENT_NOT_ALLOWED');
        }
        if ($exponent > self::MAX_DEGREE) {
            throw new MathInputError('EXPONENT_TOO_LARGE');
        }
        $result = self::constant(Rational::fromInt(1));
        for ($k = 0; $k < $exponent; $k++) {
            $result = $result->mul($this);
        }
        return $result;
    }

    public function equals(self $o): bool
    {
        if (count($this->terms) !== count($o->terms)) {
            return false;
        }
        foreach ($this->terms as $key => [$c]) {
            if (!isset($o->terms[$key]) || !$o->terms[$key][0]->equals($c)) {
                return false;
            }
        }
        return true;
    }

    public function isZero(): bool
    {
        return $this->terms === [];
    }

    /** Total degree; the zero polynomial reports 0. */
    public function degree(): int
    {
        $max = 0;
        foreach ($this->terms as [, $exps]) {
            $max = max($max, array_sum($exps));
        }
        return $max;
    }

    public function degreeIn(string $variable): int
    {
        $max = 0;
        foreach ($this->terms as [, $exps]) {
            $max = max($max, $exps[$variable] ?? 0);
        }
        return $max;
    }

    public function coefficientOf(string $monomialKey): Rational
    {
        return $this->terms[$monomialKey][0] ?? Rational::zero();
    }

    /** @return list<string> */
    public function variables(): array
    {
        $vars = [];
        foreach ($this->terms as [, $exps]) {
            foreach ($exps as $v => $k) {
                $vars[$v] = true;
            }
        }
        $names = array_keys($vars);
        sort($names);
        return $names;
    }

    public function constantValue(): ?Rational
    {
        if ($this->terms === []) {
            return Rational::zero();
        }
        if (count($this->terms) === 1 && isset($this->terms[''])) {
            return $this->terms[''][0];
        }
        return null;
    }

    public function toString(): string
    {
        if ($this->terms === []) {
            return '0';
        }
        $keys = array_keys($this->terms);
        usort($keys, function (string $a, string $b): int {
            $da = array_sum($this->terms[$a][1]);
            $db = array_sum($this->terms[$b][1]);
            return $db <=> $da ?: strcmp($a, $b);
        });
        $out = '';
        foreach ($keys as $i => $key) {
            $c = $this->terms[$key][0];
            $negative = $c->sign() < 0;
            $abs = $c->abs();
            if ($key === '') {
                $body = $abs->toString();
            } elseif ($abs->equals(Rational::fromInt(1))) {
                $body = $key;
            } else {
                $body = $abs->toString() . '*' . $key;
            }
            if ($i === 0) {
                $out .= ($negative ? '-' : '') . $body;
            } else {
                $out .= ($negative ? '-' : '+') . $body;
            }
        }
        return $out;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private function divideByConstant(self $divisor): self
    {
        $c = $divisor->constantValue();
        if ($c === null) {
            throw new MathInputError('NOT_POLYNOMIAL');
        }
        if ($c->isZero()) {
            throw new MathInputError('ZERO_DENOMINATOR');
        }
        return $this->mul(self::constant(Rational::fromInt(1)->div($c)));
    }

    /** @param array<string,int> $exps */
    private static function key(array $exps): string
    {
        $exps = array_filter($exps, static fn (int $k): bool => $k > 0);
        ksort($exps);
        $parts = [];
        foreach ($exps as $v => $k) {
            $parts[] = $k === 1 ? $v : $v . '^' . $k;
        }
        return implode('*', $parts);
    }

    /** @param array<string, array{0: Rational, 1: array<string,int>}> $terms */
    private static function checked(array $terms): self
    {
        if (count($terms) > self::MAX_TERMS) {
            throw new MathInputError('TOO_LARGE');
        }
        return new self($terms);
    }
}
