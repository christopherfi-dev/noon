<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

use AlgebraEngine\Math\Ast\Node;

final class EquationParser
{
    /**
     * @param list<string> $allowedVariables
     * @return array{0:Node,1:Node}
     */
    public static function parse(string $text, array $allowedVariables = ['x']): array
    {
        if (strlen($text) > ExpressionParser::MAX_LENGTH * 4) {
            throw new MathInputError('TOO_LONG');
        }
        $s = Normalizer::normalize($text);
        if ($s === '') {
            throw new MathInputError('EMPTY');
        }
        if (substr_count($s, '=') !== 1) {
            throw new MathInputError('NOT_AN_EQUATION');
        }
        [$left, $right] = explode('=', $s, 2);
        if (trim($left) === '' || trim($right) === '') {
            throw new MathInputError('NOT_AN_EQUATION');
        }
        return [
            ExpressionParser::parse($left, $allowedVariables),
            ExpressionParser::parse($right, $allowedVariables),
        ];
    }
}
