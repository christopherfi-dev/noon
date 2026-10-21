<?php
declare(strict_types=1);

namespace AlgebraEngine\Checking;

use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\FormInspector;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;

final class PolynomialChecker
{
    private const BASE_SCOPE = 'Algebraic identity via canonical polynomial form (no sampling) over polynomials with rational coefficients';

    /** @param list<string> $variables */
    public static function check(string $expectedExpression, string $response, array $variables, ?string $requestedForm): CheckOutcome
    {
        if ($requestedForm !== null && $requestedForm !== 'expanded' && $requestedForm !== 'factored') {
            throw new \LogicException('Unknown requested form: ' . $requestedForm);
        }
        try {
            $expected = Polynomial::fromNode(ExpressionParser::parse($expectedExpression, $variables));
        } catch (MathInputError $e) {
            throw new \LogicException('Host expected expression is invalid: ' . $e->errorCode, 0, $e);
        }
        $scope = self::BASE_SCOPE . ($requestedForm === null
            ? '; requested form not assessed.'
            : "; requested form '" . $requestedForm . "' checked separately from equivalence.");
        $limitations = $requestedForm === null ? ['Equivalence only; requested form not assessed.'] : [];

        try {
            $node = ExpressionParser::parse($response, $variables);
            $actual = Polynomial::fromNode($node);
        } catch (MathInputError $e) {
            return new CheckOutcome(
                'UNSCORABLE',
                'The response could not be read as a supported polynomial expression (' . $e->errorCode . ').',
                $scope,
                $e->clarification(),
                null,
                $limitations,
            );
        }
        $normalized = $actual->toString();
        if (!$actual->equals($expected)) {
            return new CheckOutcome('INCORRECT', 'The response is not algebraically equivalent to the host-derived key.', $scope, null, $normalized, $limitations);
        }
        if ($requestedForm !== null) {
            $issue = $requestedForm === 'expanded' ? FormInspector::expandedSimplified($node) : FormInspector::factored($node);
            if ($issue !== null) {
                return new CheckOutcome(
                    'PARTIAL',
                    'The response is equivalent to the host-derived key but does not meet the requested ' . $requestedForm . ' form (' . $issue . ').',
                    $scope,
                    null,
                    $normalized,
                    $limitations,
                    $issue,
                );
            }
        }
        return new CheckOutcome('CORRECT', 'The response is algebraically equivalent to the host-derived key' . ($requestedForm !== null ? ' and meets the requested ' . $requestedForm . ' form.' : '.'), $scope, null, $normalized, $limitations);
    }
}
