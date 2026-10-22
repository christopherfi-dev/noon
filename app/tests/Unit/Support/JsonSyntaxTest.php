<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Support;

use AlgebraEngine\Support\JsonSyntax;
use PHPUnit\Framework\TestCase;

/** Round 4 ruling 2: the invalid-JSON repair message names the position. */
final class JsonSyntaxTest extends TestCase
{
    public function testPositionOfTheRound3ExtraBrace(): void
    {
        // Shape of the round 3 low-effort failure: an extra '}' after the evidence_proposals array.
        $s = '{"evidence_proposals":[{"kind":"OBSERVATION"}]},"resource_requests":[],"issue":null}';
        $e = JsonSyntax::firstError($s);
        $this->assertSame(47, $e['position']);
        $this->assertSame(',', $s[$e['position']]);
        $this->assertStringContainsString('after the end of the JSON value', $e['message']);
    }

    public function testValidAndInvalidExamples(): void
    {
        foreach (['{}', '[]', '{"a":[1,-2.5e3,true,false,null,"x\\"y\\u00e9"],"b":{}}', ' [ ] '] as $ok) {
            $this->assertNull(JsonSyntax::firstError($ok), $ok);
        }
        $cases = ['{"a":1,}' => 7, '{"a" 1}' => 5, '[1 2]' => 3, '{"a":tru}' => 5, '{"a":"x' => 5, '' => 0, '{"a":01}' => 6];
        foreach ($cases as $bad => $pos) {
            $this->assertSame($pos, JsonSyntax::firstError($bad)['position'] ?? null, $bad);
        }
    }

    /** Agrees with PHP's decoder about validity on many corrupted variants of a valid document. */
    public function testAgreesWithJsonDecodeOnRandomCorruptions(): void
    {
        $valid = json_encode(['a' => [1, 2.5, -3e2, true, null, "s\"q\u{e9}"], 'b' => ['c' => [], 'd' => new \stdClass()], 'f' => 'line']);
        $chars = '{}[],:"0-e. tfnu\\x';
        mt_srand(11);
        for ($k = 0; $k < 3000; $k++) {
            $t = $valid;
            for ($o = mt_rand(1, 3); $o > 0; $o--) {
                $p = mt_rand(0, strlen($t) - 1);
                $t = mt_rand(0, 1) ? substr($t, 0, $p) . $chars[mt_rand(0, strlen($chars) - 1)] . substr($t, $p) : substr($t, 0, $p) . substr($t, $p + 1);
            }
            json_decode($t);
            $this->assertSame(json_last_error() === JSON_ERROR_NONE, JsonSyntax::firstError($t) === null, $t);
        }
    }
}
