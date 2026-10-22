<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

/**
 * Raised when mathematical input cannot be read safely within the declared bounds.
 * It is never a verdict about the learner: callers turn it into UNSCORABLE plus a clarification.
 */
final class MathInputError extends \RuntimeException
{
    private const CLARIFICATIONS = [
        'EMPTY' => 'Nothing was entered. Type your answer in the box.',
        'TOO_LONG' => 'That entry is longer than this question accepts. Enter a shorter answer.',
        'UNSUPPORTED_CHARACTER' => 'That entry contains a symbol this question cannot read. Use digits, x, + - * / ^ and parentheses.',
        'UNSUPPORTED_WORD' => 'That entry contains words or function names this question cannot read. Enter only numbers, the variable and operations.',
        'UNSUPPORTED_VARIABLE' => 'That entry uses a letter this question does not use. Check which variable the question asks about.',
        'MALFORMED' => 'That entry could not be read as mathematics. Check for a missing number, sign or parenthesis.',
        'TOO_DEEP' => 'That entry has more nested parentheses than this question accepts. Simplify how it is written.',
        'TOO_MANY_DIGITS' => 'That entry has a number with more digits than this question accepts.',
        'EXPONENT_NOT_ALLOWED' => 'Exponents here must be whole numbers written directly after ^, such as x^2.',
        'EXPONENT_TOO_LARGE' => 'That exponent is larger than this question accepts.',
        'ZERO_DENOMINATOR' => 'That entry divides by zero, so it does not name a number. Check the denominator.',
        'AMBIGUOUS_MIXED_NUMBER' => 'A space between a whole number and a fraction can be read more than one way. Write it as one fraction, such as 3/2, or as a decimal.',
        'TOO_LARGE' => 'That entry involves numbers larger than this question accepts.',
        'NOT_A_SINGLE_NUMBER' => 'Enter a single number, such as 5, -2.5 or 7/3.',
        'NOT_AN_EXPRESSION' => 'Enter an expression, not an equation. Leave out the = sign.',
        'NOT_POLYNOMIAL' => 'That entry divides by an expression containing a variable, which this question cannot check. Enter it without a variable in a denominator.',
        'NOT_LINEAR' => 'That equation is not linear in the variable, which this question cannot check.',
        'NOT_AN_EQUATION' => 'Enter an equation with exactly one = sign.',
    ];

    public function __construct(public string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public function clarification(): string
    {
        return self::CLARIFICATIONS[$this->errorCode]
            ?? 'That entry could not be read. Check how it is written and try again.';
    }
}
