<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

/**
 * Locates the first syntax error in a JSON text (round 4 ruling 2), so a
 * repair request can name the position. PHP's decoder reports only the kind
 * of error. Iterative scan with an explicit stack: no recursion, linear time,
 * bounded depth.
 */
final class JsonSyntax
{
    private const MAX_DEPTH = 64;

    /** @return ?array{position:int,message:string} null when the text is valid JSON */
    public static function firstError(string $s): ?array
    {
        $n = strlen($s);
        $i = 0;
        $stack = [];          // '{' or '['
        $expect = 'value';    // value | key | colon | comma_or_end | end
        while (true) {
            while ($i < $n && strpos(" \t\r\n", $s[$i]) !== false) {
                $i++;
            }
            if ($i >= $n) {
                return $expect === 'end' ? null : ['position' => $i, 'message' => 'unexpected end of input; expected ' . self::describe($expect, $stack)];
            }
            $c = $s[$i];
            switch ($expect) {
                case 'end':
                    return ['position' => $i, 'message' => "unexpected '" . $c . "' after the end of the JSON value"];
                case 'colon':
                    if ($c !== ':') {
                        return ['position' => $i, 'message' => "expected ':' after an object key, found '" . $c . "'"];
                    }
                    $i++;
                    $expect = 'value';
                    break;
                case 'comma_or_end':
                    $top = end($stack);
                    if ($c === ',') {
                        $i++;
                        $expect = $top === '{' ? 'key' : 'value';
                    } elseif (($top === '{' && $c === '}') || ($top === '[' && $c === ']')) {
                        array_pop($stack);
                        $i++;
                        $expect = $stack === [] ? 'end' : 'comma_or_end';
                    } else {
                        return ['position' => $i, 'message' => "expected ',' or '" . ($top === '{' ? '}' : ']') . "', found '" . $c . "'"];
                    }
                    break;
                case 'key':
                    if ($c === '}' && self::emptyContainerAllowed($s, $i)) {
                        array_pop($stack);
                        $i++;
                        $expect = $stack === [] ? 'end' : 'comma_or_end';
                        break;
                    }
                    if ($c !== '"') {
                        return ['position' => $i, 'message' => "expected a string key, found '" . $c . "'"];
                    }
                    $end = self::stringEnd($s, $i);
                    if ($end === null) {
                        return ['position' => $i, 'message' => 'unterminated or invalid string'];
                    }
                    $i = $end;
                    $expect = 'colon';
                    break;
                default: // value
                    if ($c === '{' || $c === '[') {
                        if (count($stack) >= self::MAX_DEPTH) {
                            return ['position' => $i, 'message' => 'nesting deeper than ' . self::MAX_DEPTH];
                        }
                        $stack[] = $c;
                        $i++;
                        $expect = $c === '{' ? 'key' : 'value';
                        break;
                    }
                    if ($c === ']' && end($stack) === '[' && self::emptyContainerAllowed($s, $i)) {
                        array_pop($stack);
                        $i++;
                        $expect = $stack === [] ? 'end' : 'comma_or_end';
                        break;
                    }
                    if ($c === '"') {
                        $end = self::stringEnd($s, $i);
                        if ($end === null) {
                            return ['position' => $i, 'message' => 'unterminated or invalid string'];
                        }
                        $i = $end;
                    } elseif (preg_match('/\G-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/', $s, $m, 0, $i) && $m[0] !== '') {
                        $i += strlen($m[0]);
                    } elseif (preg_match('/\G(?:true|false|null)/', $s, $m, 0, $i)) {
                        $i += strlen($m[0]);
                    } else {
                        return ['position' => $i, 'message' => "expected a value, found '" . $c . "'"];
                    }
                    $expect = $stack === [] ? 'end' : 'comma_or_end';
            }
        }
    }

    /** A closing bracket directly after its opening bracket (an empty object or array). */
    private static function emptyContainerAllowed(string $s, int $i): bool
    {
        $j = $i - 1;
        while ($j >= 0 && strpos(" \t\r\n", $s[$j]) !== false) {
            $j--;
        }
        return $j >= 0 && ($s[$j] === '{' || $s[$j] === '[');
    }

    /** Index just after the closing quote of the string starting at $i, or null. */
    private static function stringEnd(string $s, int $i): ?int
    {
        if (!preg_match('/\G"(?:[^"\\\\\x00-\x1F]|\\\\(?:["\\\\\/bfnrt]|u[0-9a-fA-F]{4}))*"/', $s, $m, 0, $i)) {
            return null;
        }
        return $i + strlen($m[0]);
    }

    private static function describe(string $expect, array $stack): string
    {
        return match ($expect) {
            'key' => 'a string key',
            'colon' => "':'",
            'comma_or_end' => "',' or '" . (end($stack) === '{' ? '}' : ']') . "'",
            default => 'a value',
        };
    }
}
