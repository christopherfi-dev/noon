<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use AlgebraEngine\Tutor\EntryReader;
use PHPUnit\Framework\TestCase;

/**
 * Round 6 §9.2: regression tests for the owner's four live cases, through the
 * whole loop with the mock tutor (mock results; not live-provider evidence).
 */
final class OwnerCasesTest extends TestCase
{
    public function testCapitalXOnDistributionIsReadAsXAndTheSignatureReachesTheAi(): void
    {
        $h = new Harness();
        $h->start('E06');
        TutorLoopTest::pose($h, 'check', 'E06', 'expand', '9(x + 8)', '9x + 72', '', 'Here is a check question.');
        $this->assertSame('Expand 9(x + 8). Write your answer without parentheses.', $h->view['open_problem']['prompt']);
        $calls = $h->mock->calls;
        $h->send('9X + 8');
        $a = $h->attempts()[0];
        $this->assertSame(['9X + 8', '9x + 8', EntryReader::NOTE_CAPITAL, 'INCORRECT'], [$a['entry'], $a['read_as'], $a['reading_note'], $a['verdict']]);
        $this->assertSame(1, $h->mock->calls - $calls, 'code read it; no reading call');
        $ctx = TutorLoopTest::context($h);
        $sig = $ctx['CODE_RESULT']['signatures'][0];
        $this->assertSame('FIRST_TERM_ONLY', $sig['code']);
        $this->assertStringContainsString('distribution-to-first-term-only (the factor multiplied only the x)', $sig['observation']);
        $this->assertSame('Your answer multiplied only the x by 9.', $sig['description']);
        $this->assertNull($ctx['CODE_RESULT']['slip'], 'not a slip: the structure is wrong');
        $this->assertStringContainsString('Not yet.', $this->resultLine($h));
        $this->assertStringContainsString('Read as 9x + 8.', $this->resultLine($h));
    }

    public function testAnswerInASentenceIsReadByTheAiAndMarkedCorrectByCode(): void
    {
        $h = new Harness();
        $h->start('N18');
        TutorLoopTest::pose($h, 'practice', 'N18', 'numeric_step', '4 * 4', '16', 'What is 4 × 4?');
        $calls = $h->mock->calls;
        $h->send('16 because it\'s 4 x 4');
        $this->assertSame(2, $h->mock->calls - $calls, 'a reading call, then the reply call');
        $a = $h->attempts()[0];
        $this->assertSame(['16', EntryReader::NOTE_AI_READING, 'CORRECT'], [$a['read_as'], $a['reading_note'], $a['verdict']]);
        $this->assertSame('16 because it\'s 4 x 4', $a['entry'], 'the gradebook keeps the student\'s own entry');
        $this->assertStringContainsString('Correct.', $this->resultLine($h));
        $this->assertCount(1, $h->eventsOf('READING_ACCEPTED'));
    }

    public function testFourXXInAHowManyXContextIsReadAsFourX(): void
    {
        $h = new Harness();
        $h->start('E06');
        TutorLoopTest::pose($h, 'practice', 'E06', 'expand', 'x + x + x + x', '4x', 'How many x are there in x + x + x + x? Write it as one term.');
        $h->send('4 x x');
        $a = $h->attempts()[0];
        $this->assertSame(['4x', EntryReader::NOTE_AI_READING, 'CORRECT'], [$a['read_as'], $a['reading_note'], $a['verdict']]);
    }

    public function testSlipIsConfirmedMicroCheckedRetriedAndTheRunPaused(): void
    {
        $h = new Harness();
        $h->start('E06');
        // One correct check question first, so the run has something to keep.
        TutorLoopTest::pose($h, 'check', 'E06', 'expand', '3(x + 4)', '3x + 12', '', 'Here is a check question.');
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Good. Here is another check question.', 'item' => [
            'purpose' => 'check', 'skill_id' => 'E06', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'expand', 'expression' => '7(x + 5)'], 'prompt' => '', 'stated_answer' => '7x + 35']]]);
        $h->answerCorrectly();
        $this->assertSame(1, $h->goal('E06')['count']);
        $this->assertSame('Expand 7(x + 5). Write your answer without parentheses.', $h->view['open_problem']['prompt']);
        $h->send('7x + 25');
        $slipped = $h->attempts()[1];
        $this->assertSame(['INCORRECT', 'PRODUCT_FACT'], [$slipped['verdict'], $slipped['slip']['code']]);
        $g = $h->goal('E06');
        $this->assertSame([1, true], [$g['count'], $g['paused']], 'the run is paused, not reset');
        $ctx = TutorLoopTest::context($h);
        $this->assertTrue($ctx['SLIP']['confirmed']);
        $this->assertSame('7 * 5', $ctx['CODE_RESULT']['slip']['sub_step']['expression']);
        // The tutor's micro-question is a practice spec; code checks the reply.
        $this->assertSame('practice', $h->view['open_problem']['purpose']);
        $this->assertSame('Wait, what is 7 × 5?', $h->view['open_problem']['prompt']);
        $this->assertContains('SLIP_CHECK', array_column(array_column($h->eventsOf('AI_MOVE'), 'data'), 'move'));
        $h->send('35');
        $this->assertSame('CORRECT', $h->attempts()[2]['verdict']);
        // Then a retry of the slipped problem: shown again, never counted.
        $this->assertSame('retry', $h->view['open_problem']['purpose']);
        $this->assertSame('Expand 7(x + 5). Write your answer without parentheses.', $h->view['open_problem']['prompt']);
        $h->answerCorrectly();
        $retry = $h->attempts()[3];
        $this->assertSame(['RETRY', 'CORRECT'], [$retry['purpose'], $retry['verdict']]);
        $g = $h->goal('E06');
        $this->assertSame(1, $g['count'], 'neither the slipped answer nor its retry counts');
        $this->assertTrue($g['pause_used']);
        $this->assertFalse($g['met']);
    }

    private function resultLine(Harness $h): string
    {
        $results = array_values(array_filter($h->lines(), fn ($l) => $l['kind'] === 'result'));
        return (string) end($results)['text'];
    }
}
