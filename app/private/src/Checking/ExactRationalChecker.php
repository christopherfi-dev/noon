<?php
declare(strict_types=1);

namespace AlgebraEngine\Checking;

use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;

final class ExactRationalChecker
{
    public const SCOPE = 'Exact rational comparison with the host-derived key; no tolerance; reasoning not assessed.';

    public static function check(Rational $expected, string $response, ?string $variable = 'x'): CheckOutcome
    {
        try {
            $value = ScalarParser::parse($response, $variable);
        } catch (MathInputError $e) {
            return new CheckOutcome(
                'UNSCORABLE',
                'The response could not be read as a single exact number (' . $e->errorCode . ').',
                self::SCOPE,
                $e->clarification(),
            );
        }
        if ($value->equals($expected)) {
            return new CheckOutcome('CORRECT', 'The response value equals the host-derived key.', self::SCOPE, null, $value->toString());
        }
        return new CheckOutcome('INCORRECT', 'The response value ' . $value->toString() . ' is not equal to the host-derived key.', self::SCOPE, null, $value->toString());
    }
}
