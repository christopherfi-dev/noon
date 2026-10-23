<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Config;

/**
 * Round 6 §3.6: reasoning per call, decided by code before the call.
 * - provider_thinking 'disabled' or 'enabled': the same setting on every call
 *   (Stage 2 runs a, c and d).
 * - 'per_call' (the default when nothing is configured, as in mock mode):
 *   thinking enabled at the configured effort (low) for an entry the AI must
 *   read, a wrong answer with no code signature, and a free-text message
 *   about the math; disabled for everything else.
 * The decision and its reason are logged on every call.
 */
final class ReasoningPolicy
{
    private const MATH_WORDS = ['solve', 'equation', 'expand', 'multiply', 'multiplied', 'divide', 'divided', 'add', 'subtract', 'negative', 'positive',
        'sign', 'parenthes', 'bracket', 'distribut', 'term', 'coefficient', 'variable', 'fraction', 'answer', 'step', 'why', 'how', 'times', 'minus', 'plus',
        'equal', 'factor', 'product', 'sum', 'difference', 'quotient', 'inverse', 'undo'];

    /** The setting for a call made outside a learner turn (the binding call). @return array{thinking:string,effort:?string} */
    public static function configured(Config $c): array
    {
        return $c->string('provider_thinking') === 'enabled'
            ? ['thinking' => 'enabled', 'effort' => $c->string('provider_reasoning_effort') ?: 'low']
            : ['thinking' => 'disabled', 'effort' => null];
    }

    /**
     * @param string $purpose READING | REPLY | REPAIR | BINDING
     * @param array{kind:string,verdict?:?string,signatures?:array,text?:?string} $turn
     * @return array{thinking:string,effort:?string,reason:string}
     */
    public static function decide(Config $c, string $purpose, array $turn): array
    {
        $mode = $c->string('provider_thinking') ?: 'per_call';
        $effort = $c->string('provider_reasoning_effort') ?: 'low';
        if ($mode === 'disabled' || $purpose === 'BINDING') {
            return ['thinking' => 'disabled', 'effort' => null, 'reason' => $purpose === 'BINDING' ? 'binding call' : 'configured: disabled on every call'];
        }
        if ($mode === 'enabled') {
            return ['thinking' => 'enabled', 'effort' => $effort, 'reason' => 'configured: enabled on every call'];
        }
        $reason = null;
        if ($purpose === 'READING') {
            $reason = 'an entry the AI must read';
        } elseif ($turn['kind'] === 'ANSWER' && ($turn['verdict'] ?? null) === 'INCORRECT' && ($turn['signatures'] ?? []) === [] && ($turn['slip'] ?? null) === null) {
            $reason = 'a wrong answer with no code signature';
        } elseif ($turn['kind'] === 'MESSAGE' && self::aboutMath((string) ($turn['text'] ?? ''))) {
            $reason = 'a free-text message about the math';
        }
        return $reason === null
            ? ['thinking' => 'disabled', 'effort' => null, 'reason' => 'per call: none of the three cases']
            : ['thinking' => 'enabled', 'effort' => $effort, 'reason' => 'per call: ' . $reason];
    }

    /** A message about the math: a number, a variable, an operator or a mathematical word. */
    public static function aboutMath(string $text): bool
    {
        if (preg_match('/\d|[+\-*\/=×÷^()]|(?<![\p{L}])[xy](?![\p{L}])/u', $text)) {
            return true;
        }
        $lower = mb_strtolower($text, 'UTF-8');
        foreach (self::MATH_WORDS as $w) {
            if (preg_match('/(?<![\p{L}])' . $w . '/u', $lower)) {
                return true;
            }
        }
        return false;
    }
}
