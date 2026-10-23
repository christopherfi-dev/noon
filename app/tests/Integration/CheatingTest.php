<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * Round 6 §9.3: each attempt to cheat a guarantee is rejected or recorded
 * correctly. The mock tutor plays the adversary; a rejected output is never
 * shown, and the gradebook, verdicts and goals stay code's.
 */
final class CheatingTest extends TestCase
{
    private function rejectedWith(Harness $h, string $needle): void
    {
        $errors = [];
        foreach ($h->eventsOf('MODEL_OUTPUT_REJECTED') as $e) {
            $errors = array_merge($errors, $e['data']['errors']);
        }
        $this->assertNotSame([], array_filter($errors, fn ($e) => str_contains($e, $needle)), 'expected a rejection containing "' . $needle . '"; got ' . json_encode($errors));
    }

    private function neverShown(Harness $h, string $text): void
    {
        foreach ($h->lines() as $l) {
            $this->assertStringNotContainsString($text, $l['text'], 'rejected text reached the student');
        }
    }

    /** A check question on Q06 is open, posed by the test. */
    private function withCheck(): Harness
    {
        $h = new Harness();
        $h->start('Q06');
        TutorLoopTest::pose($h, 'check', 'Q06', 'linear_equation', '3x + 4 = 19', '5', '', 'Here is a check question.');
        return $h;
    }

    public function testTheAiMarksAGoal(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('MARK_GOAL');
        $h->answerCorrectly();
        $this->rejectedWith($h, 'claims mastery or a met goal');
        $this->neverShown($h, 'mastered');
        $this->assertFalse($h->goal('Q06')['met']);
        $this->assertSame(1, $h->goal('Q06')['count']);
        // A proposal alone is recorded as the AI's and rejected; nothing changes.
        $h2 = $this->withCheck();
        $h2->mock->queue('MARK_GOAL_PROPOSAL');
        $h2->answerCorrectly();
        $rejected = $h2->eventsOf('MODEL_PROPOSAL_REJECTED');
        $this->assertCount(1, $rejected);
        $this->assertSame([], $h2->eventsOf('GOAL_MET'));
        $this->assertFalse($h2->goal('Q06')['met']);
        $this->assertFalse($h2->row()['snapshot']['goals'][0]['met']);
    }

    public function testTheAiOverrulesAVerdict(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('OVERRULE');
        $h->send($h->wrong());
        $this->rejectedWith($h, 'calls the answer correct');
        $this->neverShown($h, 'Correct! Well done.');
        $this->assertSame('INCORRECT', $h->attempts()[0]['verdict']);
        $results = array_values(array_filter($h->lines(), fn ($l) => $l['kind'] === 'result'));
        $this->assertStringStartsWith('Not yet.', end($results)['text'], 'code shows its own verdict');
        $this->assertSame(0, $h->goal('Q06')['count']);
    }

    public function testTheAiPosesAProblemWithAWrongAnswer(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->mock->queue('WRONG_ANSWER');
        $h->send('please give me a different question');
        $this->rejectedWith($h, 'stated answer 7 differs from the answer code computed');
        $this->neverShown($h, '3(x + 4) = 27');
    }

    public function testTheAiPosesAnOffPatternCheckProblem(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->mock->queue('OFF_PATTERN_CHECK');
        $h->send('I am ready for a check question');
        $this->rejectedWith($h, 'must match its pattern');
        $this->neverShown($h, '2x + 3 = 13');
    }

    public function testAReadingAddsNumbers(): void
    {
        $h = new Harness();
        $h->start('N18');
        TutorLoopTest::pose($h, 'practice', 'N18', 'numeric_step', '4 * 4', '16', 'What is 4 × 4?');
        $h->mock->queue('READING_ADDS_NUMBER');
        $h->send('16 because it\'s 4 x 4');
        $rejected = $h->eventsOf('READING_REJECTED');
        $this->assertCount(1, $rejected);
        $this->assertStringContainsString('adds numbers the student did not write: 17', $rejected[0]['data']['reasons'][0]);
        $this->assertSame([], $h->attempts(), 'nothing recorded from a rejected reading');
        $this->assertSame('I couldn\'t read that as an answer. Please write it again, using numbers and symbols.', end($h->view['conversation'])['text']);
        $this->assertSame('practice', $h->view['open_problem']['purpose'], 'the problem stays open for a rewrite');
    }

    public function testASlipThatTheStructureContradicts(): void
    {
        $h = new Harness();
        $h->start('E06');
        TutorLoopTest::pose($h, 'check', 'E06', 'expand', '9(x + 8)', '9x + 72', '', 'Here is a check question.');
        $h->mock->queue('FALSE_SLIP');
        $h->send('9x + 8');
        $this->rejectedWith($h, 'SLIP_CHECK is only possible right after a slip that code confirmed');
        $this->neverShown($h, 'Just a small slip.');
        $a = $h->attempts()[0];
        $this->assertNull($a['slip']);
        $g = $h->goal('E06');
        $this->assertFalse($g['paused'], 'no pause without a code-confirmed slip');
        $this->assertNotContains('SLIP_CHECK', array_column(array_column($h->eventsOf('AI_MOVE'), 'data'), 'move'));
    }

    public function testTextBeforeACheckCardContainsItsNumbers(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->mock->queue('CARD_NUMBERS', ['skill' => 'Q07']);
        $h->answerCorrectly(); // the tutor answers with a check card and text that names one of its numbers
        $this->rejectedWith($h, 'contains numbers from the new check question');
        $this->neverShown($h, 'Watch the');
    }

    public function testTextLeaksAnAnswer(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('LEAK');
        $h->stuck('HINT');
        $this->rejectedWith($h, 'gives away the answer');
        $this->neverShown($h, 'The answer is 5');
        $this->assertSame(['HINT'], $h->open()['help'], 'help was recorded before any text was generated');
    }

    public function testTextClaimsMasteryOrUsesInternalTerms(): void
    {
        foreach (['MASTERY' => 'You know this now.', 'INTERNAL' => 'The host matched the answer key.'] as $fault => $text) {
            $h = new Harness();
            $h->start('Q07');
            $h->mock->queue($fault);
            $h->send('why does that work');
            $this->assertNotSame([], $h->eventsOf('MODEL_OUTPUT_REJECTED'), $fault);
            $this->neverShown($h, $text);
        }
    }

    public function testAFreeTextMessageDuringAnOpenCheckProblem(): void
    {
        $h = $this->withCheck();
        $calls = $h->mock->calls;
        $before = $h->lines();
        $h->send('can you explain this to me first?');
        $this->assertSame($calls, $h->mock->calls, 'no AI call: the conversation pauses');
        $new = $h->lastLines($before);
        $this->assertSame([['student', 'message'], ['app', 'notice']], array_map(fn ($l) => [$l['role'], $l['kind']], $new));
        $this->assertSame('Let\'s finish this check question first. Answer it, or tap I\'m stuck.', $new[1]['text']);
        $this->assertSame([], $h->attempts());
        $this->assertSame('check', $h->view['open_problem']['purpose']);
        $this->assertCount(1, $h->eventsOf('CONVERSATION_PAUSED'));
        $metrics = $h->eventsOf('TURN_METRICS');
        $this->assertSame(0, end($metrics)['data']['calls']);
    }

    public function testWithoutHelpIsNeverRecordedAfterAStuckEntry(): void
    {
        $h = $this->withCheck();
        $h->send('idk');
        $help = $h->eventsOf('HELP_GIVEN');
        $this->assertSame('TYPED_STUCK', $help[0]['data']['choice'], 'a typed stuck phrase is the I\'m stuck choice');
        $this->assertTrue($h->view['open_problem']['with_help']);
        $h->answerCorrectly();
        $a = $h->attempts()[0];
        $this->assertSame(['CORRECT', ['TYPED_STUCK']], [$a['verdict'], $a['help']]);
        $this->assertSame(0, $h->goal('Q06')['count'], 'answered after help: the run starts again');
        $report = $h->request('GET', 'report')->body;
        $skill = $report['summary']['teacher']['skills'][0];
        $this->assertSame([0, 1], [$skill['independent'], $skill['assisted']], 'the report counts it as with help');
    }

    public function testTheAiPosesAProblemWhileACheckIsOpen(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('POSE_ANYWAY');
        $h->stuck('EXPLAIN');
        $this->rejectedWith($h, 'no problem may be posed this turn');
        $this->assertSame('check', $h->view['open_problem']['purpose'], 'the check question is still the open problem');
    }
}
