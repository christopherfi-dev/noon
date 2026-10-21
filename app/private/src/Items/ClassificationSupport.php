<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Checking\CheckOutcome;

/** Shared helpers for lettered multi-select items. */
final class ClassificationSupport
{
    public const LETTERS = ['A', 'B', 'C', 'D'];

    /**
     * @param list<array{code:string,text:string}> $options in display order
     * @return list<array{letter:string,code:string,text:string}>
     */
    public static function letter(array $options): array
    {
        $out = [];
        foreach (array_values($options) as $i => $o) {
            $out[] = ['letter' => self::LETTERS[$i], 'code' => $o['code'], 'text' => $o['text']];
        }
        return $out;
    }

    /** @param list<array{letter:string,text:string}> $lettered */
    public static function promptLines(string $stem, array $lettered): string
    {
        $lines = [$stem];
        foreach ($lettered as $o) {
            $lines[] = $o['letter'] . ') ' . $o['text'];
        }
        return implode("\n", $lines);
    }

    /** @param list<string> $expectedLetters */
    public static function spec(array $expectedLetters, string $form = 'select_all'): array
    {
        sort($expectedLetters);
        return [
            'kind' => 'classification',
            'expected' => $expectedLetters,
            'requested_form' => $form,
            'units' => null,
            'tolerance' => null,
            'rubric' => [],
        ];
    }

    /**
     * Codes the learner selected, read from the checker's normalized response.
     *
     * @param list<array{letter:string,code:string}> $lettered
     * @return list<string>
     */
    public static function selectedCodes(CheckOutcome $outcome, array $lettered): array
    {
        if ($outcome->normalizedResponse === null || $outcome->normalizedResponse === '') {
            return [];
        }
        $letters = explode(',', $outcome->normalizedResponse);
        $codes = [];
        foreach ($lettered as $o) {
            if (in_array($o['letter'], $letters, true)) {
                $codes[] = $o['code'];
            }
        }
        return $codes;
    }

    /**
     * @param list<string> $codes
     * @param list<string> $pool
     * @param list<string> $errors
     */
    public static function validateCodes(mixed $codes, array $pool, array &$errors): bool
    {
        if (!is_array($codes) || !array_is_list($codes) || count($codes) !== 4) {
            $errors[] = 'parameters.options must be a list of exactly 4 option codes';
            return false;
        }
        foreach ($codes as $c) {
            if (!is_string($c) || !in_array($c, $pool, true)) {
                $errors[] = 'parameters.options may only contain: ' . implode(', ', $pool);
                return false;
            }
        }
        if (count(array_unique($codes)) !== 4) {
            $errors[] = 'parameters.options must not repeat a code';
            return false;
        }
        return true;
    }
}
