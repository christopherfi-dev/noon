<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Items;

use AlgebraEngine\Checking\ClassificationChecker;
use AlgebraEngine\Checking\ExactRationalChecker;
use AlgebraEngine\Checking\PolynomialChecker;
use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Items\SocraticPlanner;
use AlgebraEngine\Math\EquationParser;
use AlgebraEngine\Math\LinearEquation;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Provider\DisclosureGuard;
use AlgebraEngine\Support\RandomSource;
use PHPUnit\Framework\TestCase;

/** Server-owned templates and guided plans: host keys, bounds, signatures, D1 step properties. */
final class TemplatesTest extends TestCase
{
    private static function check(array $spec, array $objects, string $response): string
    {
        return match ($spec['kind']) {
            'exact_rational' => ExactRationalChecker::check(ScalarParser::parse($spec['expected']), $response)->verdict,
            'polynomial' => PolynomialChecker::check($spec['expected'], $response, $objects['variables'] ?? ['x'], $spec['requested_form'] === 'expanded' ? 'expanded' : null)->verdict,
            'classification' => ClassificationChecker::check($spec['expected'], $objects['option_letters'], $response)->verdict,
        };
    }

    public function testEveryTemplateGeneratesValidItemsWhoseHostKeyChecksCorrect(): void
    {
        $f = new ItemFactory();
        $this->assertCount(14, $f->all());
        foreach ($f->all() as $id => $t) {
            $rng = RandomSource::seeded('t' . $id);
            for ($i = 0; $i < 120; $i++) {
                $p = $t->generate($rng);
                $this->assertSame([], $t->validate($p), $id);
                $spec = $t->answerSpec($p);
                $answer = is_array($spec['expected']) ? implode(',', $spec['expected']) : $spec['expected'];
                $this->assertSame('CORRECT', self::check($spec, $t->mathematicalObjects($p), $answer), $id . ' ' . json_encode($p));
                $this->assertNotSame('', $t->render($p));
            }
        }
    }

    public function testParameterBoundsAreEnforced(): void
    {
        $f = new ItemFactory();
        $q07 = $f->get('GROUPED_LINEAR');
        $this->assertNotSame([], $q07->validate(['a' => 1, 'b' => 2, 'c' => 9]));
        $this->assertNotSame([], $q07->validate(['a' => 3, 'b' => 0, 'c' => 9]));
        $this->assertNotSame([], $q07->validate(['a' => 3, 'b' => 2, 'c' => 10]), 'non-integer solution');
        $this->assertNotSame([], $q07->validate(['a' => 3, 'b' => 2, 'c' => 9, 'evil' => 1]), 'unknown parameter');
        $this->assertNotSame([], $q07->validate(['a' => '3', 'b' => 2, 'c' => 9]), 'type');
        $this->assertSame([], $q07->validate(['a' => 3, 'b' => 4, 'c' => 27]));
        $this->assertNotSame([], $f->get('SIGNED_DIVIDE')->validate(['a' => 7, 'b' => -2]));
        $this->assertNotSame([], $f->get('POSITIVE_DISTRIBUTION')->validate(['a' => -3, 'b' => 2]));
    }

    public function testGroupedLinearSignaturesAreDistinctAndBranchCorrectly(): void
    {
        $t = (new ItemFactory())->get('GROUPED_LINEAR');
        $p = ['a' => 3, 'b' => 4, 'c' => 27];  // 3(x+4)=27, x=5
        $sig = fn (string $r) => array_column($t->matchSignatures($p, $r, ExactRationalChecker::check(Rational::fromInt(5), $r)), 'branch', 'code');
        $this->assertSame(['DIST_X_ONLY' => 'E06'], $sig('23/3'));      // 3x+4=27
        $this->assertSame(['SIGN_ERROR_B' => 'Q06', 'DIVIDE_THEN_ADD_B' => 'Q06'], $sig('13'));
        $this->assertSame(['ARITH_FINAL_PLUS' => 'Q06'], $sig('6'));
        $this->assertSame([], $sig('40'));
        $this->assertSame([], $sig('5'), 'no signature for a correct answer');
        $this->assertSame('Your answer multiplied only the x by 3.', $t->signatureFeedback($p, 'DIST_X_ONLY'));
    }

    public function testContrastKeysAreDerivedFromTheDisplayedEquations(): void
    {
        $t = (new ItemFactory())->get('GROUPED_FIRST_STEP_CONTRAST');
        $p = ['a' => 3, 'b' => 4, 'c' => 27, 'options' => ['MULTIPLY_X_ONLY', 'DIVIDE_BOTH', 'SUBTRACT_INSIDE', 'DISTRIBUTE']];
        $this->assertSame(['B', 'D'], $t->answerSpec($p)['expected']);
        // Independently re-solve every displayed option line.
        [$l, $r] = EquationParser::parse('3(x + 4) = 27');
        $original = LinearEquation::solve($l, $r)->value;
        foreach (explode("\n", $t->render($p)) as $line) {
            if (preg_match('/^([A-D])\) .*: (.*)$/u', $line, $m)) {
                [$ol, $or] = EquationParser::parse($m[2]);
                $same = LinearEquation::solve($ol, $or)->value->equals($original);
                $this->assertSame(in_array($m[1], ['B', 'D'], true), $same, $line);
            }
        }
        $this->assertNotSame([], $t->validate(['a' => 3, 'b' => 4, 'c' => 27, 'options' => ['MULTIPLY_X_ONLY', 'SUBTRACT_INSIDE', 'DIVIDE_LEFT_ONLY', 'MULTIPLY_X_ONLY']]));
        $this->assertNotSame([], $t->validate(['a' => 3, 'b' => 4, 'c' => 27, 'options' => ['MULTIPLY_X_ONLY', 'SUBTRACT_INSIDE', 'DIVIDE_LEFT_ONLY']]));
        $d = (new ItemFactory())->get('DISTRIBUTION_CONTRAST');
        $this->assertSame(['A', 'D'], $d->answerSpec(['a' => 4, 'b' => 3, 'options' => ['EXPANDED', 'FIRST_ONLY', 'SECOND_ONLY', 'UNSIMPLIFIED']])['expected']);
    }

    public function testOptionOrderIsRandomisedPerItem(): void
    {
        $t = (new ItemFactory())->get('GROUPED_FIRST_STEP_CONTRAST');
        $rng = RandomSource::seeded('order');
        $orders = [];
        for ($i = 0; $i < 30; $i++) {
            $orders[] = implode(',', $t->generate($rng)['options']);
        }
        $this->assertGreaterThan(5, count(array_unique($orders)));
    }

    /** D1: every guided step asks one question, its key checks correct, and its own prompt does not contain its answer. */
    /** Round 2 ruling 8: every guided step names the skill it exercises: the repair skill or a declared prerequisite. */
    public function testEveryGuidedStepIsTaggedWithTheSkillItExercises(): void
    {
        $curriculum = \AlgebraEngine\Curriculum\CurriculumRepository::fromFile(ALGEBRA_APP_ROOT . '/private/resources/curriculum/algebra-map.json');
        $planner = new SocraticPlanner(new ItemFactory());
        $seen = [];
        foreach (['N03', 'N04', 'N05', 'N06', 'N18', 'Q04', 'Q05', 'Q06', 'Q07', 'E06'] as $skill) {
            $allowed = array_merge([$skill], $curriculum->declaredPrerequisites($skill));
            foreach ([false, true] as $faded) {
                foreach ($skill === 'Q07' ? ['divide', 'expand'] : ['divide'] as $route) {
                    for ($i = 0; $i < 20; $i++) {
                        $plan = $planner->plan($skill, RandomSource::seeded("tag$skill$i$route" . (int) $faded), $faded, ['route' => $route]);
                        foreach ($planner->flatten($plan['steps']) as $step) {
                            $this->assertContains($step['exercises'], $allowed, "$skill {$step['id']}");
                            $seen[$skill][$step['exercises']] = true;
                        }
                    }
                }
            }
        }
        $this->assertArrayHasKey('N18', $seen['E06']);
        $this->assertArrayHasKey('A07', $seen['E06']);
        $this->assertArrayHasKey('E06', $seen['E06']);
    }

    /** R4-B: structural features per template, and a planner that keeps them. */
    public function testStructuralFeaturesAreKeptByThePlanner(): void
    {
        $this->assertSame(['sign_a' => '+', 'sign_b' => '-'], \AlgebraEngine\Items\StructuralFeatures::of('SIGNED_DIVIDE', ['a' => 72, 'b' => -9]));
        $this->assertSame(['form' => 'c=a(x+b)', 'sign_a' => '+', 'sign_b' => '-', 'sign_c' => '+'],
            \AlgebraEngine\Items\StructuralFeatures::of('GROUPED_LINEAR_TRANSFER', ['form' => 'SWAPPED', 'a' => 3, 'b' => -2, 'c' => 12, 'd' => 0]));
        $planner = new SocraticPlanner(new ItemFactory());
        $factory = new ItemFactory();
        foreach (['N03' => 'SIGNED_ADD', 'N04' => 'SIGNED_SUBTRACT', 'N05' => 'SIGNED_MULTIPLY', 'N06' => 'SIGNED_DIVIDE'] as $skill => $template) {
            // Only sign patterns the skill's items can have: N03–N06 items always include a negative operand.
            $patterns = [];
            $rng = RandomSource::seeded("patterns$skill");
            for ($i = 0; $i < 2000; $i++) {
                $f = \AlgebraEngine\Items\StructuralFeatures::of($template, $factory->get($template)->generate($rng));
                $patterns[$f['sign_a'] . $f['sign_b']] = [$f['sign_a'], $f['sign_b']];
            }
            $this->assertCount(3, $patterns, $skill . ': at least one operand is negative by the item specification');
            foreach ($patterns as [$sa, $sb]) {
                for ($i = 0; $i < 10; $i++) {
                    $plan = $planner->plan($skill, RandomSource::seeded("keep$skill$sa$sb$i"), $i % 2 === 1, ['keep' => ['sign_a' => $sa, 'sign_b' => $sb]]);
                    $this->assertSame(['sign_a' => $sa, 'sign_b' => $sb], \AlgebraEngine\Items\StructuralFeatures::of($template, $plan['params']), "$skill $sa$sb");
                    $this->assertSame([], $plan['unkept_features']);
                }
            }
        }
        // Q07 guided problems use a(x+b)=c; the SWAPPED form cannot be kept, the signs still are.
        $plan = $planner->plan('Q07', RandomSource::seeded('keepq07'), false, ['keep' => ['form' => 'c=a(x+b)', 'sign_a' => '+', 'sign_b' => '-', 'sign_c' => '-']]);
        $this->assertSame(['form'], $plan['unkept_features']);
        $this->assertSame(['-', '-'], [\AlgebraEngine\Items\StructuralFeatures::of('GROUPED_LINEAR', $plan['params'])['sign_b'], \AlgebraEngine\Items\StructuralFeatures::of('GROUPED_LINEAR', $plan['params'])['sign_c']]);
    }

    /** R4-B: the gate rejects a model proposal that changes a kept feature (bounded repair follows); host-chosen numbers keep them. */
    public function testGateRejectsAProposalThatChangesAKeptFeature(): void
    {
        $gate = new \AlgebraEngine\Items\AcceptanceGate(new ItemFactory(), new \AlgebraEngine\Provider\SchemaValidator(ALGEBRA_APP_ROOT . '/private/resources/contracts'));
        $alloc = ['item_id' => 'itm_00000000000000000000000a', 'item_version' => 1, 'skill_id' => 'N06', 'role' => 'GUIDED', 'variant_id' => 'constructed',
            'component_id' => 'numeric', 'template_ids' => ['SIGNED_DIVIDE'], 'avoid_parameters' => null, 'keep_features' => ['sign_a' => '+', 'sign_b' => '-']];
        $draft = fn (array $p) => ['item_id' => $alloc['item_id'], 'item_version' => 1, 'skill_id' => 'N06', 'role' => 'GUIDED', 'variant_id' => 'constructed',
            'component_id' => 'numeric', 'template_id' => 'SIGNED_DIVIDE', 'response_type' => 'scalar', 'parameters' => $p];
        $rng = RandomSource::seeded('gatekeep');
        $bad = $gate->accept($draft(['a' => -72, 'b' => 9]), $alloc, [], $rng);
        $this->assertFalse($bad['ok']);
        $this->assertSame(['parameters must keep the structure of the item the learner failed: sign_a must be +, sign_b must be -'], $bad['errors']);
        $this->assertTrue($gate->accept($draft(['a' => 40, 'b' => -8]), $alloc, [], $rng)['ok']);
        for ($i = 0; $i < 20; $i++) {
            $host = $gate->accept($draft([]), $alloc, [], $rng);
            $this->assertSame(['sign_a' => '+', 'sign_b' => '-'], \AlgebraEngine\Items\StructuralFeatures::of('SIGNED_DIVIDE', $host['record']['parameters']));
        }
    }

    /** Regression (found by the round 3 gate): no step answer may equal a number in the facts shown with the opening step. */
    public function testOpeningFactsNeverContainAStepAnswer(): void
    {
        $planner = new SocraticPlanner(new ItemFactory());
        for ($i = 0; $i < 200; $i++) {
            $factor = 2 + $i % 8;
            $facts = "Your answer multiplied only the x by $factor.";
            $plan = $planner->plan('E06', RandomSource::seeded("facts$i"), false, ['avoid_text' => $facts]);
            foreach ($planner->flatten($plan['steps']) as $step) {
                if ($step['answer']['kind'] === 'exact_rational') {
                    $this->assertNotSame((string) $factor, $step['answer']['expected'], "seed $i {$step['id']}");
                }
            }
        }
    }

    public function testGuidedPlansAreQuestionsThatNeverStateTheirAnswer(): void
    {
        $f = new ItemFactory();
        $planner = new SocraticPlanner($f);
        foreach (['N03', 'N04', 'N05', 'N06', 'N18', 'Q04', 'Q05', 'Q06', 'Q07', 'E06'] as $skill) {
            foreach ([false, true] as $faded) {
                foreach ($skill === 'Q07' ? ['divide', 'expand'] : ['divide'] as $route) {
                    for ($i = 0; $i < 40; $i++) {
                        $plan = $planner->plan($skill, RandomSource::seeded("$skill$i$route" . (int) $faded), $faded, ['route' => $route]);
                        $finals = array_filter($plan['steps'], fn ($s) => $s['final']);
                        $this->assertCount(1, $finals, $skill);
                        foreach ($planner->flatten($plan['steps']) as $step) {
                            $objects = ['variables' => $step['variables'], 'option_letters' => array_column($step['options'] ?? [], 'letter')];
                            $answer = is_array($step['answer']['expected']) ? implode(',', $step['answer']['expected']) : $step['answer']['expected'];
                            $this->assertSame('CORRECT', self::check($step['answer'], $objects, $answer), "$skill {$step['id']}");
                            $question = explode("\n", $step['prompt'])[0];
                            $this->assertSame(1, substr_count($question, '?'), "$skill {$step['id']} asks exactly one question: $question");
                            if ($step['response_type'] !== 'classification') {
                                foreach (array_filter([$step['prompt'], $step['alt_prompt']]) as $text) {
                                    $this->assertSame([], DisclosureGuard::violations($text, [['answer' => $step['answer'], 'variables' => $step['variables']]]), "$skill {$step['id']}: $text");
                                }
                            } else {
                                $this->assertCount(1, $step['answer']['expected'], 'single-choice guided step');
                            }
                        }
                    }
                }
            }
        }
    }
}
