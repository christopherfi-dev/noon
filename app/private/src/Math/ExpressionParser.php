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
 * Bounded recursive-descent parser for polynomial-style expressions.
 * Nothing is evaluated or executed; unsupported syntax fails closed with MathInputError.
 */
final class ExpressionParser
{
    public const MAX_LENGTH = 200;
    public const MAX_DEPTH = 25;
    public const MAX_TOKENS = 200;
    public const MAX_DIGITS = 12;
    public const MAX_EXPONENT = 10;

    /** @var list<array{0:string,1:string}> */
    private array $tokens = [];
    private int $pos = 0;

    /** @param list<string> $allowedVariables */
    private function __construct(private readonly array $allowedVariables)
    {
    }

    /** @param list<string> $allowedVariables */
    public static function parse(string $text, array $allowedVariables = ['x']): Node
    {
        if (strlen($text) > self::MAX_LENGTH * 4) {
            throw new MathInputError('TOO_LONG');
        }
        $s = Normalizer::normalize($text);
        if (strlen($s) > self::MAX_LENGTH) {
            throw new MathInputError('TOO_LONG');
        }
        if ($s === '') {
            throw new MathInputError('EMPTY');
        }
        if (str_contains($s, '=')) {
            throw new MathInputError('NOT_AN_EXPRESSION');
        }
        $parser = new self($allowedVariables);
        $parser->tokens = $parser->tokenize($s);
        $node = $parser->parseExpr(0);
        if ($parser->pos !== count($parser->tokens)) {
            throw new MathInputError('MALFORMED');
        }
        return $node;
    }

    /** @return list<array{0:string,1:string}> */
    private function tokenize(string $s): array
    {
        $tokens = [];
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            $c = $s[$i];
            if ($c === ' ') {
                $i++;
                continue;
            }
            if (ctype_digit($c) || $c === '.') {
                $start = $i;
                while ($i < $len && (ctype_digit($s[$i]) || $s[$i] === '.')) {
                    $i++;
                }
                $tokens[] = ['NUM', substr($s, $start, $i - $start)];
            } elseif (ctype_alpha($c)) {
                $start = $i;
                while ($i < $len && ctype_alpha($s[$i])) {
                    $i++;
                }
                $word = substr($s, $start, $i - $start);
                foreach (str_split($word) as $letter) {
                    if (!in_array($letter, $this->allowedVariables, true)) {
                        throw new MathInputError(strlen($word) > 1 ? 'UNSUPPORTED_WORD' : 'UNSUPPORTED_VARIABLE');
                    }
                }
                foreach (str_split($word) as $letter) {
                    $tokens[] = ['VAR', $letter];
                }
            } elseif (str_contains('+-*/^()', $c)) {
                $tokens[] = [$c, $c];
                $i++;
            } else {
                throw new MathInputError('UNSUPPORTED_CHARACTER');
            }
            if (count($tokens) > self::MAX_TOKENS) {
                throw new MathInputError('TOO_LONG');
            }
        }
        return $tokens;
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->pos][0] ?? null;
    }

    private function parseExpr(int $depth): Node
    {
        $node = $this->parseTerm($depth);
        while (($t = $this->peek()) === '+' || $t === '-') {
            $this->pos++;
            $node = new BinOp($t, $node, $this->parseTerm($depth));
        }
        return $node;
    }

    private function parseTerm(int $depth): Node
    {
        $node = $this->parseUnary($depth);
        while (true) {
            $t = $this->peek();
            if ($t === '*' || $t === '/') {
                $this->pos++;
                $node = new BinOp($t, $node, $this->parseUnary($depth));
            } elseif ($t === 'VAR' || $t === '(') {
                $node = new BinOp('*', $node, $this->parsePower($depth), true);
            } elseif ($t === 'NUM') {
                // "x2", ")2" and "3 4" are not read as multiplication.
                throw new MathInputError('MALFORMED');
            } else {
                return $node;
            }
        }
    }

    private function parseUnary(int $depth): Node
    {
        $negations = 0;
        while (($t = $this->peek()) === '-' || $t === '+') {
            if ($t === '-') {
                $negations++;
            }
            $this->pos++;
        }
        $node = $this->parsePower($depth);
        for ($k = 0; $k < $negations; $k++) {
            $node = new Neg($node);
        }
        return $node;
    }

    private function parsePower(int $depth): Node
    {
        $base = $this->parseAtom($depth);
        if ($this->peek() !== '^') {
            return $base;
        }
        $this->pos++;
        $tok = $this->tokens[$this->pos] ?? null;
        if ($tok === null || $tok[0] !== 'NUM' || !ctype_digit($tok[1])) {
            throw new MathInputError('EXPONENT_NOT_ALLOWED');
        }
        $this->pos++;
        $digits = ltrim($tok[1], '0');
        if (strlen($digits) > 2 || (int) $digits > self::MAX_EXPONENT) {
            throw new MathInputError('EXPONENT_TOO_LARGE');
        }
        if ($this->peek() === '^') {
            throw new MathInputError('EXPONENT_NOT_ALLOWED');
        }
        return new Pow($base, (int) $digits);
    }

    private function parseAtom(int $depth): Node
    {
        $tok = $this->tokens[$this->pos] ?? null;
        if ($tok === null) {
            throw new MathInputError('MALFORMED');
        }
        if ($tok[0] === 'NUM') {
            $this->pos++;
            return new Num(self::numberLiteral($tok[1]));
        }
        if ($tok[0] === 'VAR') {
            $this->pos++;
            return new VarRef($tok[1]);
        }
        if ($tok[0] === '(') {
            if ($depth + 1 > self::MAX_DEPTH) {
                throw new MathInputError('TOO_DEEP');
            }
            $this->pos++;
            $inner = $this->parseExpr($depth + 1);
            if ($this->peek() !== ')') {
                throw new MathInputError('MALFORMED');
            }
            $this->pos++;
            return new Group($inner);
        }
        throw new MathInputError('MALFORMED');
    }

    private static function numberLiteral(string $lit): Rational
    {
        if (preg_match('/^(\d*)\.?(\d*)$/', $lit, $m) !== 1 || substr_count($lit, '.') > 1 || $lit === '.') {
            throw new MathInputError('MALFORMED');
        }
        $whole = ltrim($m[1], '0');
        $frac = $m[2];
        if (strlen($whole) > self::MAX_DIGITS || strlen($frac) > self::MAX_DIGITS) {
            throw new MathInputError('TOO_MANY_DIGITS');
        }
        $value = Rational::fromInt($whole === '' ? 0 : (int) $whole);
        if ($frac !== '') {
            $value = $value->add(Rational::of((int) $frac, 10 ** strlen($frac)));
        }
        return $value;
    }
}
