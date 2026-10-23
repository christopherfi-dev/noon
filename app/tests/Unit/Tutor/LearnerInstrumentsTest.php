<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Tutor;

use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Tutor\AnswerCheck;
use AlgebraEngine\Tutor\EntryReader;
use AlgebraEngine\Tutor\Goals;
use AlgebraEngine\Tutor\Gradebook;
use AlgebraEngine\Tutor\ProblemFactory;
use AlgebraEngine\Tutor\ReadingCheck;
use AlgebraEngine\Tutor\TextGuard;
use PHPUnit\Framework\TestCase;

/**
 * Round 6 M1: the reading check (§4.1), safe normalisation and ambiguous
 * entries (§3.1), slip confirmation (§4.4), the gradebook and goals (§2.2,
 * §2.4, §5.4–5.5) and the text guards (§2.6, §5.2), each also tried with an
 * attempt to cheat it.
 */
final class LearnerInstrumentsTest extends TestCase
{
    private ProblemFactory $problems;
    private AnswerCheck $checker;

    protected function setUp(): void
    {
        $items = new ItemFactory();
        $this->problems = new ProblemFactory($items);
        $this->checker = new AnswerCheck($items);
    }

    // ------------------------------------------------------------ reading (§4.1)

    public function testReadingsThatOnlyReExpressTheEntryPass(): void
    {
        $this->assertSame([], ReadingCheck::check('16 because it\'s 4 x 4', '16', ['x']));
        $this->assertSame([], ReadingCheck::check('4 x x', '4x', ['x']));
        $this->assertSame([], ReadingCheck::check('sixteen', '16', ['x']));
        $this->assertSame([], ReadingCheck::check('negative three', '-3', ['x']));
        $this->assertSame([], ReadingCheck::check('9X + 8', '9x + 8', ['x']));
        $this->assertSame([], ReadingCheck::check('x is 5 i think', 'x = 5', ['x']));
    }

    public function testReadingsThatAddNumbersOrVariablesAreRejected(): void
    {
        // Cheat: a reading that adds numbers (the student wrote 4 x 4; 17 appears nowhere).
        $this->assertNotSame([], ReadingCheck::check('16 because it\'s 4 x 4', '17', ['x']));
        $this->assertStringContainsString('adds numbers', ReadingCheck::check('4 x 4', '16', ['x'])[0]);
        $this->assertNotSame([], ReadingCheck::check('seven', '7x + 35', ['x']));
        // Cheat: a reading that introduces a variable the student did not write.
        $this->assertStringContainsString('did not write', ReadingCheck::check('16 because', '16x', ['x'])[0]);
        $this->assertStringContainsString('not a variable', ReadingCheck::check('4 y y', '4y', ['x'])[0]);
        $this->assertNotSame([], ReadingCheck::check('16', 'system(16)', ['x']));
    }

    // ----------------------------------------------------------- entries (§3.1)

    public function testOwnerCasesAreClassifiedBeforeAnyCall(): void
    {
        $e06 = $this->problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 9, 'b' => 8], 'CHECK');
        $r = EntryReader::classify('9X + 8', $e06);
        $this->assertSame('ANSWER', $r['kind'], 'a capital X is read as x when the entry uses no other letters');
        $this->assertSame('9x + 8', $r['read_as']);
        $this->assertSame(EntryReader::NOTE_CAPITAL, $r['note']);
        $n18 = $this->problems->fromRecipe('WHOLE_MULTIPLY_DIVIDE', ['op' => 'multiply', 'a' => 4, 'b' => 4], 'PRACTICE');
        $this->assertSame('AMBIGUOUS', EntryReader::classify('16 because it\'s 4 x 4', $n18)['kind'], 'words: the AI reads it');
        $howMany = $this->problems->fromSpec(\AlgebraEngine\Tutor\SpecMath::compute('expand', 'x + x + x + x'), null, 'PRACTICE', 'E06', 'How many x are in x + x + x + x?');
        $this->assertSame('AMBIGUOUS', EntryReader::classify('4 x x', $howMany)['kind'], 'the variable alone between spaces next to a variable');
        $this->assertSame('AMBIGUOUS', EntryReader::classify('4 x 4', $n18)['kind']);
        $this->assertSame('AMBIGUOUS', EntryReader::classify('4×4', $n18)['kind']);
        $this->assertSame('AMBIGUOUS', EntryReader::classify('4 times 4', $n18)['kind']);
        $this->assertSame('ANSWER', EntryReader::classify('7x + 25', $this->problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 7, 'b' => 5], 'CHECK'))['kind']);
    }

    public function testOtherEntries(): void
    {
        $q07 = $this->problems->fromRecipe('GROUPED_LINEAR', ['a' => 3, 'b' => 4, 'c' => 27], 'CHECK');
        $this->assertSame('STUCK', EntryReader::classify('idk', $q07)['kind']);
        $this->assertSame('STUCK', EntryReader::classify('???', $q07)['kind']);
        $this->assertSame('MESSAGE', EntryReader::classify('why do we divide first?', $q07)['kind']);
        $this->assertSame('MESSAGE', EntryReader::classify('anything', null)['kind'], 'no open problem: a message');
        $r = EntryReader::classify('5?', $q07);
        $this->assertSame(['ANSWER', '5', EntryReader::NOTE_QUESTION_MARK], [$r['kind'], $r['read_as'], $r['note']]);
        $r = EntryReader::classify('X = 5', $q07);
        $this->assertSame(['ANSWER', 'x = 5'], [$r['kind'], $r['read_as']]);
        $this->assertSame('UNREADABLE', EntryReader::classify('x2', $q07)['kind']);
        $this->assertSame('AMBIGUOUS', EntryReader::classify('is it 5', $q07)['kind']);
        // Capitals are not normalised when the entry uses another letter as well.
        $this->assertNotSame('ANSWER', EntryReader::classify('9X + 8y', $this->problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 9, 'b' => 8], 'CHECK'))['kind']);
        $choice = $this->problems->fromRecipe('DISTRIBUTION_CONTRAST', ['a' => 3, 'b' => 4, 'options' => ['EXPANDED', 'FIRST_ONLY', 'SECOND_ONLY', 'ADDED_FACTOR']], 'CHECK');
        $this->assertSame('ANSWER', EntryReader::classify('A', $choice)['kind']);
        $this->assertSame('AMBIGUOUS', EntryReader::classify('i think A', $choice)['kind']);
        $this->assertSame('MESSAGE', EntryReader::classify('what does equal mean here', $choice)['kind']);
    }

    // -------------------------------------------------------------- slips (§4.4)

    public function testDistributionToXOnlyIsNotASlip(): void
    {
        $p = $this->problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 9, 'b' => 8], 'CHECK');
        $r = $this->checker->check($p, '9x + 8');
        $this->assertSame('INCORRECT', $r['verdict']);
        $this->assertSame(['FIRST_TERM_ONLY'], array_column($r['signatures'], 'code'));
        $this->assertStringContainsString('distribution-to-first-term-only', $r['signatures'][0]['label']);
        $this->assertNull($r['slip'], 'a slip the structure contradicts is never confirmed');
    }

    public function testProductSlipConfirmedWithItsMicroStep(): void
    {
        $p = $this->problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 7, 'b' => 5], 'CHECK');
        $r = $this->checker->check($p, '7x + 25');
        $this->assertSame('INCORRECT', $r['verdict']);
        $this->assertSame('PRODUCT_FACT', $r['slip']['code']);
        $this->assertSame(['kind' => 'numeric_step', 'expression' => '7 * 5', 'answer' => '35'], $r['slip']['micro']);
        // Two wrong coefficients, or a wrong copied coefficient, are not one arithmetic slip.
        $this->assertNull($this->checker->check($p, '6x + 25')['slip']);
        $this->assertNull($this->checker->check($p, '6x + 35')['slip']);
        $this->assertNull($this->checker->check($p, '7x + 35')['slip'], 'a correct answer is no slip');
        // A factor of 1 leaves nothing to multiply: 4x + 5 for 4(x + 1) is not a multiplication slip
        // (and its micro-question "4 × 1" would show its own answer).
        $one = $this->problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 4, 'b' => 1], 'CHECK');
        $this->assertNull($this->checker->check($one, '4x + 5')['slip']);
    }

    public function testOtherSlipSignatures(): void
    {
        $q07 = $this->problems->fromRecipe('GROUPED_LINEAR', ['a' => 3, 'b' => 4, 'c' => 27], 'CHECK'); // x = 5
        $this->assertSame('ARITH_FINAL_PLUS', $this->checker->check($q07, '6')['slip']['code']);
        $this->assertSame(['ARITH_FINAL_MINUS', '9 - (4)', '5'], [$this->checker->check($q07, '4')['slip']['code'], $this->checker->check($q07, '4')['slip']['micro']['expression'], $this->checker->check($q07, '4')['slip']['micro']['answer']]);
        $this->assertNull($this->checker->check($q07, '9')['slip'], '27/3 = 9 without removing the 4 is not a slip');
        $n05 = $this->problems->fromRecipe('SIGNED_MULTIPLY', ['a' => -6, 'b' => 7], 'CHECK');
        $this->assertSame('MAGNITUDE', $this->checker->check($n05, '-48')['slip']['code']);
        $this->assertNull($this->checker->check($n05, '42')['slip'], 'a sign error is not a slip');
        $this->assertNull($this->checker->check($n05, '-40')['slip'], 'not a times-table neighbour');
    }

    // ------------------------------------------------------ gradebook and goals

    /** A ledger with one problem per attempt: [purpose, skill, variant, verdict, slip?, help?]. */
    private function ledger(array $rows): array
    {
        $events = [];
        foreach ($rows as $i => $r) {
            [$purpose, $skill, $variant, $verdict] = $r;
            $pid = 'prb_' . $i;
            $events[] = ['event_id' => 'e' . $i . 'p', 'type' => 'PROBLEM_PRESENTED', 'data' => ['problem_id' => $pid, 'version' => 1, 'purpose' => $purpose,
                'skill_id' => $skill, 'source' => 'RECIPE', 'kind' => 'linear_equation', 'template_id' => null, 'variant_id' => $variant, 'prompt' => 'P' . $i, 'fingerprint' => 'f' . $i, 'retry_of' => null]];
            if (!empty($r[5])) {
                $events[] = ['event_id' => 'e' . $i . 'h', 'type' => 'HELP_GIVEN', 'data' => ['problem_id' => $pid, 'version' => 1, 'choice' => $r[5]]];
            }
            $events[] = ['event_id' => 'e' . $i . 'a', 'type' => 'ATTEMPT_RECORDED', 'data' => ['problem_id' => $pid, 'version' => 1, 'entry' => 'v' . $i, 'read_as' => 'v' . $i,
                'reading_note' => null, 'verdict' => $verdict, 'normalized' => 'v' . $i, 'signatures' => [], 'slip' => !empty($r[4]) ? ['code' => 'PRODUCT_FACT'] : null,
                'response_sha256' => 'h', 'scope' => 's']];
        }
        return $events;
    }

    private function goal(array $rows, string $skill): array
    {
        return Goals::forSkill(Goals::compute(Gradebook::attempts($this->ledger($rows))), $skill);
    }

    public function testThreeCheckProblemsInARowWithoutHelp(): void
    {
        $c = fn ($v = 'constructed', $verdict = 'CORRECT', $slip = false, $help = null) => ['CHECK', 'Q06', $v, $verdict, $slip, $help];
        $this->assertTrue($this->goal([$c(), $c(), $c()], 'Q06')['met']);
        $this->assertFalse($this->goal([$c(), $c(), $c('constructed', 'INCORRECT'), $c()], 'Q06')['met']);
        $this->assertSame(1, $this->goal([$c(), $c(), $c('constructed', 'INCORRECT'), $c()], 'Q06')['count']);
        // An answer after help resets the run, even when correct.
        $g = $this->goal([$c(), $c(), $c('constructed', 'CORRECT', false, 'HINT')], 'Q06');
        $this->assertSame([0, false], [$g['count'], $g['met']]);
        $this->assertFalse($this->goal([$c(), $c(), $c('constructed', 'CORRECT', false, 'TYPED_STUCK')], 'Q06')['met'], 'a stuck entry is help');
        // Practice never counts and never resets.
        $p = ['PRACTICE', 'Q06', 'constructed', 'INCORRECT'];
        $this->assertTrue($this->goal([$c(), $p, $c(), ['PRACTICE', 'Q06', 'constructed', 'CORRECT'], $c()], 'Q06')['met']);
        $this->assertSame(0, $this->goal([$p, $p, $p], 'Q06')['count']);
        // Partially correct neither advances nor resets.
        $this->assertTrue($this->goal([$c(), $c('constructed', 'PARTIAL'), $c(), $c()], 'Q06')['met']);
    }

    public function testThreeVariantSkillsNeedEachKind(): void
    {
        $c = fn ($v) => ['CHECK', 'Q07', $v, 'CORRECT'];
        $g = $this->goal([$c('constructed'), $c('constructed'), $c('constructed')], 'Q07');
        $this->assertFalse($g['met']);
        $this->assertSame(['contrast', 'transfer'], $g['still_needed']);
        $this->assertTrue($this->goal([$c('constructed'), $c('contrast'), $c('transfer')], 'Q07')['met']);
        $this->assertTrue($this->goal([$c('constructed'), $c('constructed'), $c('contrast'), $c('transfer')], 'Q07')['met']);
    }

    public function testOneSlipPausesTheCountASecondResetsIt(): void
    {
        $c = fn ($verdict = 'CORRECT', $slip = false) => ['CHECK', 'E06', 'constructed', $verdict, $slip];
        $r = ['RETRY', 'E06', 'constructed', 'CORRECT'];
        $g = $this->goal([$c(), $c(), $c('INCORRECT', true)], 'E06');
        $this->assertSame([2, true, true], [$g['count'], $g['pause_used'], $g['paused']], 'the slip pauses the count; the run is not reset');
        $g = $this->goal([$c(), $c(), $c('INCORRECT', true), $r], 'E06');
        $this->assertSame(2, $g['count'], 'the retry does not count');
        $this->assertFalse($g['met']);
        $this->assertSame(2, $this->goal([$c(), $c('INCORRECT', true), $r, $c()], 'E06')['count']);
        $g = $this->goal([$c(), $c('INCORRECT', true), $c(), $c('INCORRECT', true)], 'E06');
        $this->assertSame([0, false], [$g['count'], $g['pause_used']], 'a second slip in the run resets it');
        // E06 is a three-variant skill; give it the three kinds to check that a paused run still meets the goal.
        $v = fn ($variant, $verdict = 'CORRECT', $slip = false) => ['CHECK', 'E06', $variant, $verdict, $slip];
        $this->assertTrue($this->goal([$v('constructed'), $v('contrast'), $v('transfer', 'INCORRECT', true), $r, $v('transfer')], 'E06')['met']);
    }

    public function testGradebookRecordsEveryRequiredField(): void
    {
        $a = Gradebook::attempts($this->ledger([['CHECK', 'Q07', 'constructed', 'CORRECT', false, 'HINT']]))[0];
        foreach (['problem_id', 'prompt', 'skill_id', 'purpose', 'entry', 'reading_note', 'verdict', 'help'] as $k) {
            $this->assertArrayHasKey($k, $a);
        }
        $this->assertSame(['HINT'], $a['help']);
        // An attempt that names a problem never presented is not an attempt.
        $events = $this->ledger([['CHECK', 'Q07', 'constructed', 'CORRECT']]);
        $events[] = ['event_id' => 'x', 'type' => 'ATTEMPT_RECORDED', 'data' => ['problem_id' => 'prb_forged', 'version' => 1] + $events[1]['data']];
        $this->assertCount(1, Gradebook::attempts($events));
    }

    public function testNothingButCheckAttemptsCanMarkAGoal(): void
    {
        // Cheat: events with any other type, even ones that look like goal or verdict records, change nothing.
        $events = $this->ledger([['CHECK', 'Q06', 'constructed', 'CORRECT']]);
        foreach (['GOAL_MET', 'CRITERION_MET', 'AI_MOVE', 'MODEL_PROPOSAL'] as $type) {
            $events[] = ['event_id' => $type, 'type' => $type, 'data' => ['skill_id' => 'Q06', 'met' => true, 'verdict' => 'CORRECT']];
        }
        $g = Goals::forSkill(Goals::compute(Gradebook::attempts($events)), 'Q06');
        $this->assertSame([1, false], [$g['count'], $g['met']]);
    }

    // -------------------------------------------------------------- guards (§2.6)

    public function testGuardsOnLearnerVisibleText(): void
    {
        $open = $this->problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 7, 'b' => 5], 'PRACTICE');
        $this->assertSame([], TextGuard::violations('What does the 7 multiply? Have a look at each term inside the parentheses.', [$open]), 'questions are allowed');
        $this->assertNotSame([], TextGuard::violations('It comes out as 7x + 35.', [$open]), 'answer leak');
        $this->assertNotSame([], TextGuard::violations('The answer key says otherwise.', [$open]), 'internal vocabulary');
        $this->assertNotSame([], TextGuard::violations('Do this one on your own.', [$open]), 'support claim');
        foreach (['You\'ve mastered this!', 'Great, you have learned this.', 'You know this now.', 'Goal met!', 'You met the goal for this skill.', 'That shows mastery.'] as $claim) {
            $this->assertNotSame([], TextGuard::violations($claim, []), 'mastery claim: ' . $claim);
        }
        $this->assertSame([], TextGuard::violations('You multiplied both terms by 7, using the distributive property.', []));
    }

    public function testTheAiCannotOverruleTheVerdict(): void
    {
        $this->assertNotSame([], TextGuard::contradicts('Correct! Nicely done.', 'INCORRECT'));
        $this->assertNotSame([], TextGuard::contradicts('Yes, that\'s it.', 'INCORRECT'));
        $this->assertNotSame([], TextGuard::contradicts('I think your answer is correct after all.', 'INCORRECT'));
        $this->assertNotSame([], TextGuard::contradicts('Not quite. Check the sign.', 'CORRECT'));
        $this->assertSame([], TextGuard::contradicts('Not yet. Your first step was right, though: you divided both sides.', 'INCORRECT'));
        $this->assertSame([], TextGuard::contradicts('You multiplied both terms by 7.', 'CORRECT'));
    }

    public function testTextBeforeACheckCard(): void
    {
        $card = $this->problems->fromRecipe('GROUPED_LINEAR', ['a' => 4, 'b' => -3, 'c' => 28], 'CHECK'); // 4(x − 3) = 28, x = 10
        $r = TextGuard::beforeCheckCard('You divided both sides by 4 first.', $card);
        $this->assertNotSame([], $r['violations'], 'a number from the new problem');
        $r = TextGuard::beforeCheckCard('You divided both sides first, then undid the subtraction.', $card);
        $this->assertSame([], $r['violations']);
        $r = TextGuard::beforeCheckCard('Twenty-eight is a lot.', $card);
        $this->assertNotSame([], $r['violations'], 'compound number words are detected before a check card');
        $r = TextGuard::beforeCheckCard('Twenty is a lot.', $card);
        $this->assertSame([], $r['violations']);
        $r = TextGuard::beforeCheckCard('Three times something gives the total.', $this->problems->fromRecipe('GROUPED_LINEAR', ['a' => 3, 'b' => 4, 'c' => 27], 'CHECK'));
        $this->assertNotSame([], $r['violations'], 'a number word for a number in the new problem');
        $r = TextGuard::beforeCheckCard('Good. Next, try this one and remember to check both sides. When you finish, make sure you don\'t forget the sign.', $card);
        $this->assertSame([], $r['violations']);
        $this->assertSame(['next', 'try', 'remember', 'make sure', "don't forget", 'when you'], $r['forward'], 'forward-looking phrases go on the audit list');
    }
}
