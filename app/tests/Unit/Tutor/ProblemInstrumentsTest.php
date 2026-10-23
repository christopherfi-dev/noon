<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Tutor;

use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Tutor\PatternMatcher;
use AlgebraEngine\Tutor\ProblemFactory;
use AlgebraEngine\Tutor\ProblemVerifier;
use AlgebraEngine\Tutor\Recipes;
use AlgebraEngine\Tutor\SpecError;
use AlgebraEngine\Tutor\SpecMath;
use PHPUnit\Framework\TestCase;

/**
 * Round 6 M1: problem verification (§4.2), the recipes used in reverse as
 * check-problem validators, and recipe pre-generation (§4.3). Each rule is
 * also tried with an attempt to cheat it.
 */
final class ProblemInstrumentsTest extends TestCase
{
    private ItemFactory $items;
    private ProblemFactory $problems;
    private PatternMatcher $patterns;
    private ProblemVerifier $verifier;

    protected function setUp(): void
    {
        $this->items = new ItemFactory();
        $this->problems = new ProblemFactory($this->items);
        $this->patterns = new PatternMatcher($this->items);
        $this->verifier = new ProblemVerifier($this->problems, $this->patterns);
    }

    private function ctx(array $recipes = [], array $used = [], ?array $retry = null): array
    {
        return ['recipes' => $recipes, 'used_fingerprints' => $used, 'retry_target' => $retry];
    }

    private function spec(string $purpose, string $skill, string $kind, string $expr, ?string $stated, string $prompt = ''): array
    {
        return ['purpose' => $purpose, 'skill_id' => $skill, 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => $kind, 'expression' => $expr], 'prompt' => $prompt, 'stated_answer' => $stated];
    }

    public function testCodeComputesEveryKind(): void
    {
        $this->assertSame('3', SpecMath::compute('linear_equation', '3(x + 4) = 21')['answer']['expected']);
        $this->assertSame('-5/2', SpecMath::compute('linear_equation', '2x + 9 = 4')['answer']['expected']);
        $this->assertSame('7*x+35', SpecMath::compute('expand', '7(x + 5)')['answer']['expected']);
        $this->assertSame('4*x', SpecMath::compute('expand', 'x + x + x + x')['answer']['expected']);
        $this->assertSame('-42', SpecMath::compute('signed_arithmetic', '-6 * 7')['answer']['expected']);
        $this->assertSame('35', SpecMath::compute('numeric_step', '7*5')['answer']['expected']);
        $this->assertSame('3', SpecMath::compute('numeric_step', '21 / 7')['answer']['expected']);
        $this->assertSame('7(x + 5)', SpecMath::compute('expand', '7*(x+5)')['display']);
        $this->assertSame('(−6) × 7', SpecMath::compute('signed_arithmetic', '-6*7')['display']);
    }

    public function testSpecRulesFailClosed(): void
    {
        $cases = [
            ['linear_equation', 'x + 1 = x + 2', 'NO_UNIQUE_SOLUTION'],
            ['linear_equation', '2x = 2x', 'NO_UNIQUE_SOLUTION'],
            ['linear_equation', 'x^2 = 4', 'UNREADABLE'],
            ['linear_equation', '300x = 600', 'OUT_OF_RANGE'],
            ['linear_equation', '7x = 1', 'OUT_OF_RANGE'],              // denominator 7 is fine, 1/7: allowed? see below
            ['expand', '3x + 5', 'NOTHING_TO_DO'],
            ['expand', '12', 'OUT_OF_RANGE'],
            ['expand', 'system(ls)', 'UNREADABLE'],
            ['signed_arithmetic', '3 + 4 + 5', 'NOT_THIS_KIND'],
            ['signed_arithmetic', '7 / 2', 'OUT_OF_RANGE'],
            ['numeric_step', '35', 'NOT_THIS_KIND'],
            ['numeric_step', '2.5 * 2', 'OUT_OF_RANGE'],
            ['numeric_step', '1/0', 'UNREADABLE'],
            ['quadratic', 'x^2', 'UNKNOWN_KIND'],
        ];
        foreach ($cases as [$kind, $expr, $reason]) {
            if ($expr === '7x = 1') {
                $this->assertSame('1/7', SpecMath::compute($kind, $expr)['answer']['expected'], 'a denominator within the range is allowed');
                continue;
            }
            try {
                SpecMath::compute($kind, $expr);
                $this->fail("$kind '$expr' should be rejected");
            } catch (SpecError $e) {
                $this->assertSame($reason, $e->reason, "$kind '$expr'");
            }
        }
    }

    public function testRecipesInReverseMatchEveryTypedRecipeAndItsBounds(): void
    {
        $rng = RandomSource::seeded('reverse');
        foreach ($this->items->all() as $id => $t) {
            if ($t->responseType() === 'classification') {
                continue;
            }
            for ($i = 0; $i < 25; $i++) {
                $params = $t->generate($rng);
                $p = $this->problems->fromRecipe($id, $params, 'CHECK');
                $kind = $p['kind'];
                $c = SpecMath::compute($kind, (string) $p['spec']);
                $m = $this->patterns->match($kind, $c['nodes'], $t->skillId());
                $this->assertNotNull($m, "$id: recipe instance " . json_encode($params) . ' must match its own pattern');
                $this->assertSame($id, $m['template_id']);
                $this->assertEquals($params, $m['params'], "$id parameters read back");
                $this->assertSame($p['answer']['expected'], $this->problems->fromRecipe($m['template_id'], $m['params'], 'CHECK')['answer']['expected']);
            }
        }
    }

    public function testOffPatternCheckProblemsAreRejected(): void
    {
        // Correct mathematics, wrong structure for the skill: a two-step equation is not a Q07 check problem.
        $r = $this->verifier->verify($this->spec('check', 'Q07', 'linear_equation', '2x + 3 = 11', '4'), $this->ctx());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('must match its pattern', $r['errors'][0]);
        // E06 is the distributive property with positive factors: a negative inside is off pattern.
        $r = $this->verifier->verify($this->spec('check', 'E06', 'expand', '3(x - 4)', '3x - 12'), $this->ctx());
        $this->assertFalse($r['ok']);
        // Out of the recipe's bounds (Q07 a is 2..9).
        $r = $this->verifier->verify($this->spec('check', 'Q07', 'linear_equation', '12(x + 1) = 36', '2'), $this->ctx());
        $this->assertFalse($r['ok']);
        // The same problems are fine as practice.
        $this->assertTrue($this->verifier->verify($this->spec('practice', 'Q07', 'linear_equation', '2x + 3 = 11', '4', 'Solve 2x + 3 = 11.'), $this->ctx())['ok']);
        // On pattern: accepted, with code's wording and the recipe's variant.
        $r = $this->verifier->verify($this->spec('check', 'Q07', 'linear_equation', '5(x - 2) = 20', '6', 'Here, find x in 5(x - 2) = 20 quickly'), $this->ctx());
        $this->assertTrue($r['ok'], implode('; ', $r['errors']));
        $this->assertSame('Solve 5(x − 2) = 20 for x.', $r['problem']['prompt'], 'code writes a check problem\'s wording');
        $this->assertSame('GROUPED_LINEAR', $r['problem']['template_id']);
        $this->assertSame('constructed', $r['problem']['variant_id']);
        $this->assertGreaterThan(0, $r['dropped_prompt_chars']);
        $r = $this->verifier->verify($this->spec('check', 'Q07', 'linear_equation', '3(x + 2) + 4 = 25', '5'), $this->ctx());
        $this->assertTrue($r['ok']);
        $this->assertSame('transfer', $r['problem']['variant_id']);
    }

    public function testWrongStatedAnswerIsRejected(): void
    {
        foreach ([
            ['practice', 'Q07', 'linear_equation', '3(x + 4) = 21', '4'],
            ['check', 'Q07', 'linear_equation', '3(x + 4) = 21', '7'],
            ['practice', 'E06', 'expand', '7(x + 5)', '7x + 5'],
            ['practice', 'N05', 'signed_arithmetic', '-6 * 7', '42'],
            ['practice', 'N18', 'numeric_step', '7 * 5', '36'],
            ['practice', 'Q07', 'linear_equation', '3(x + 4) = 21', null],
        ] as [$purpose, $skill, $kind, $expr, $stated]) {
            $r = $this->verifier->verify($this->spec($purpose, $skill, $kind, $expr, $stated, 'Try ' . $expr), $this->ctx());
            $this->assertFalse($r['ok'], "$expr with stated answer " . var_export($stated, true));
            $this->assertStringContainsString('stated_answer', str_replace('stated answer', 'stated_answer', $r['errors'][0]));
        }
        // Equivalent forms of the right answer are the same answer.
        $this->assertTrue($this->verifier->verify($this->spec('practice', 'Q07', 'linear_equation', '3(x + 4) = 27', 'x = 5', 'Solve 3(x + 4) = 27.'), $this->ctx())['ok']);
        $this->assertTrue($this->verifier->verify($this->spec('practice', 'E06', 'expand', '7(x + 5)', '35 + 7x', 'Expand 7(x + 5).'), $this->ctx())['ok']);
    }

    public function testPracticeWordingMustShowTheSpecAndNotItsAnswer(): void
    {
        // The AI may phrase practice in its own words, but the spec's numbers must be shown...
        $r = $this->verifier->verify($this->spec('practice', 'Q07', 'linear_equation', '3(x + 4) = 21', '3', 'Solve 3(x + 4) = 12 for x.'), $this->ctx());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('missing: 21', $r['errors'][0]);
        // ...and the wording may not contain its own answer.
        $r = $this->verifier->verify($this->spec('practice', 'E06', 'expand', '7(x + 5)', '7x + 35', 'Expand 7(x + 5), which gives 7x + 35.'), $this->ctx());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('gives away the answer', $r['errors'][0]);
        // Number words count as the number.
        $r = $this->verifier->verify($this->spec('practice', 'N18', 'numeric_step', '7 * 5', '35', 'Wait, what is seven times 5?'), $this->ctx());
        $this->assertTrue($r['ok'], implode('; ', $r['errors']));
        $this->assertSame('Wait, what is seven times 5?', $r['problem']['prompt']);
        // A question whose answer is a number shown in it is rejected (round 5.1 post-beta item).
        $r = $this->verifier->verify($this->spec('practice', 'Q07', 'linear_equation', '3(x + 3) = 18', '3', 'Solve 3(x + 3) = 18.'), $this->ctx());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('answer', $r['errors'][0]);
        // Internal terms and mastery claims are not allowed in a prompt either.
        $r = $this->verifier->verify($this->spec('practice', 'N18', 'numeric_step', '7 * 5', '35', 'Since you mastered this, what is 7 × 5?'), $this->ctx());
        $this->assertFalse($r['ok']);
    }

    public function testRecipeChoicesAndFreshness(): void
    {
        $rng = RandomSource::seeded('recipes');
        $recipes = (new Recipes($this->problems))->pregenerate(['Q07', 'E06', 'Q06'], $rng, [], ['Q07' => ['contrast', 'transfer']]);
        $this->assertCount(6, $recipes, 'two fresh problems per relevant skill');
        $q07 = array_values(array_filter($recipes, fn ($p) => $p['skill_id'] === 'Q07'));
        $this->assertSame(['contrast', 'transfer'], array_column($q07, 'variant_id'), 'Q07 recipes cover the kinds its goal still needs');
        $ref = array_search($q07[0], $recipes, true);
        // A recipe check problem: code's wording, choice options from the contrast recipe.
        $r = $this->verifier->verify(['purpose' => 'check', 'skill_id' => 'Q07', 'source' => 'recipe', 'recipe_ref' => $ref, 'spec' => null, 'prompt' => 'Pick fast', 'stated_answer' => null], $this->ctx($recipes));
        $this->assertTrue($r['ok'], implode('; ', $r['errors']));
        $this->assertSame('classification', $r['problem']['response_type']);
        $this->assertSame('CHECK', $r['problem']['purpose']);
        // Cheat: a recipe reference that was not prepared, or the wrong skill for it.
        $this->assertFalse($this->verifier->verify(['purpose' => 'check', 'skill_id' => 'Q07', 'source' => 'recipe', 'recipe_ref' => 'R99', 'spec' => null, 'prompt' => '', 'stated_answer' => null], $this->ctx($recipes))['ok']);
        $this->assertFalse($this->verifier->verify(['purpose' => 'check', 'skill_id' => 'E06', 'source' => 'recipe', 'recipe_ref' => $ref, 'spec' => null, 'prompt' => '', 'stated_answer' => null], $this->ctx($recipes))['ok']);
        // Cheat: a wrong stated answer on a recipe.
        $this->assertFalse($this->verifier->verify(['purpose' => 'check', 'skill_id' => 'Q07', 'source' => 'recipe', 'recipe_ref' => $ref, 'spec' => null, 'prompt' => '', 'stated_answer' => 'A, B, C, D'], $this->ctx($recipes))['ok']);
        // Cheat: a choice check problem written as a spec (choice problems come only from the contrast recipes).
        $r = $this->verifier->verify(['purpose' => 'check', 'skill_id' => 'Q07', 'source' => 'spec', 'recipe_ref' => null, 'spec' => ['kind' => 'classification', 'expression' => 'A'], 'prompt' => '', 'stated_answer' => 'A'], $this->ctx($recipes));
        $this->assertFalse($r['ok']);
        // Cheat: reusing a problem already shown as a check problem.
        $used = [$recipes[$ref]['fingerprint']];
        $this->assertFalse($this->verifier->verify(['purpose' => 'check', 'skill_id' => 'Q07', 'source' => 'recipe', 'recipe_ref' => $ref, 'spec' => null, 'prompt' => '', 'stated_answer' => null], $this->ctx($recipes, $used))['ok']);
        // Pre-generation never offers a problem already shown.
        $again = (new Recipes($this->problems))->pregenerate(['Q07'], RandomSource::seeded('recipes'), $used, ['Q07' => ['contrast', 'transfer']]);
        $this->assertNotContains($recipes[$ref]['fingerprint'], array_column($again, 'fingerprint'));
    }

    public function testPregeneratedProblemsRarelyShowTheirAnswer(): void
    {
        $rec = new Recipes($this->problems);
        $shown = 0;
        $total = 0;
        foreach (range(1, 40) as $i) {
            foreach ($rec->pregenerate(['Q04', 'Q05', 'Q06', 'Q07', 'N03', 'N04', 'N05', 'N06', 'N18', 'E06'], RandomSource::seeded('coincide' . $i), []) as $p) {
                $total++;
                $shown += ProblemFactory::answerShownInProblem($p) ? 1 : 0;
            }
        }
        $this->assertSame(800, $total);
        $this->assertLessThan(8, $shown, 'the answer is a number shown in the problem only when no alternative was found');
    }

    public function testRetryOnlyAfterASlip(): void
    {
        $slipped = $this->problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 7, 'b' => 5], 'CHECK');
        $proposal = ['purpose' => 'retry', 'skill_id' => 'E06', 'source' => 'retry', 'recipe_ref' => null, 'spec' => null, 'prompt' => '', 'stated_answer' => null];
        $this->assertFalse($this->verifier->verify($proposal, $this->ctx())['ok'], 'no slipped problem, no retry');
        $r = $this->verifier->verify($proposal, $this->ctx([], [$slipped['fingerprint']], $slipped));
        $this->assertTrue($r['ok']);
        $this->assertSame('RETRY', $r['problem']['purpose']);
        $this->assertSame($slipped['problem_id'], $r['problem']['retry_of']);
        $this->assertSame($slipped['prompt'], $r['problem']['prompt']);
        // A retry cannot be relabelled as a check problem.
        $this->assertFalse($this->verifier->verify(['purpose' => 'check'] + $proposal, $this->ctx([], [], $slipped))['ok']);
    }

    public function testOnlyBetaSkills(): void
    {
        $r = $this->verifier->verify($this->spec('practice', 'A07', 'expand', '3(x + 4)', '3x + 12', 'Expand 3(x + 4).'), $this->ctx());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('ten skills', $r['errors'][0]);
    }
}
