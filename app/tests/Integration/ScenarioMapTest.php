<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** Keeps app/tests/acceptance-map.json honest against the contract and the test files. */
final class ScenarioMapTest extends TestCase
{
    public function testEveryScenarioIsImplementedOrDeferredWithAReason(): void
    {
        $contract = json_decode((string) file_get_contents(ALGEBRA_REPO_ROOT . '/contracts/acceptance-scenarios.json'), true)['scenarios'];
        $map = array_column(json_decode((string) file_get_contents(ALGEBRA_APP_ROOT . '/tests/acceptance-map.json'), true)['scenarios'], null, 'id');
        $this->assertSame(array_column($contract, 'id'), array_keys($map));
        $required = explode(' ', 'ENTRY-01 FLOW-01 FLOW-02 FLOW-04 FLOW-05 FLOW-06 FLOW-07 EVID-01 EVID-02 EVID-03 EVID-06 MATH-01 MATH-02 MATH-03 MATH-06 MATH-08 MATH-09 HOST-01 HOST-02 HOST-03 HOST-05 HOST-06 HOST-07 HOST-08 HOST-09 HOST-10 HOST-11 HOST-12 HOST-13 HOST-15 HOST-16 HOST-17 UI-01 UI-02 DEPLOY-01 DEPLOY-02 COVER-01');
        foreach ($map as $id => $row) {
            if ($row['status'] === 'DEFERRED') {
                $this->assertNotContains($id, $required, "$id is required by the addendum");
                $this->assertStringStartsWith('Deferred', $row['note']);
                continue;
            }
            if (str_contains($row['test'], '::')) {
                [$file, $method] = explode('::', $row['test']);
                $src = (string) file_get_contents(ALGEBRA_APP_ROOT . '/' . $file);
                $this->assertStringContainsString('function ' . $method . '(', $src, $id);
                $this->assertStringContainsString($id . ':', $src, "$id named in the test docblock");
            } else {
                $this->assertStringContainsString($id, (string) file_get_contents(ALGEBRA_APP_ROOT . '/tests/Browser/run_browser.mjs'));
            }
        }
        foreach ($required as $id) {
            $this->assertNotSame('DEFERRED', $map[$id]['status'], $id);
        }
    }
}
