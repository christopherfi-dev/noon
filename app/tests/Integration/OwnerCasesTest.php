<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use AlgebraEngine\Tutor\EntryReader;
use PHPUnit\Framework\TestCase;

/**
 * Round 6 §9.2: regression tests for the owner's four live cases, and round 7
 * §9's reading cases, through the whole loop with the mock tutor (mock
 * results; not live-provider evidence).
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
        $this->assertStringContainsString('distribution-to-first-term-only (the factor multiplied only the first term inside the parentheses)', $sig['observation']);
        $this->assertSame('Your answer multiplied only the first term inside the parentheses by 9.', $sig['description']);
        $this->assertNull($ctx['CODE_RESULT']['slip'], 'not a slip: the structure is wrong');
        $this->assertStringContainsString('Not yet.', $this->resultLine($h));
        $this->assertStringContainsString('Read as 9x + 8.', $this->resultLine($h));
    }

    public function testAnswerInASentenceIsReadByTheAiAndMarkedCorrectByCode(): void
    {
        $h = new Harness();
        $h->start('N18');
        TutorLoopTest::pose($h, 'practice', 'N18', 'numeric_step', '4 * 4', '16');
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
        TutorLoopTest::pose($h, 'practice', 'E06', 'expand', 'x + x + x + x', '4x');
        $this->assertSame('Simplify x + x + x + x by combining like terms.', $h->view['open_problem']['prompt'], 'code renders the wording');
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
        // The tutor's micro-question is a practice spec; code renders its wording and checks the reply.
        $this->assertSame('practice', $h->view['open_problem']['purpose']);
        $this->assertSame('Calculate 7 × 5.', $h->view['open_problem']['prompt']);
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

    /**
     * Round 7 §9: readings through the loop. The AI's reading (queued on the
     * reading call) is accepted only when it re-expresses what the student
     * wrote; otherwise nothing is graded and the student sees the AI's own
     * clarifying question.
     *
     * @return array{0:Harness,1:list<array>} the harness and the lines the turn added
     */
    private function readAs(string $kind, string $expr, string $stated, string $entry, string $reading, ?string $question = 'Did you mean the whole expression?'): array
    {
        $h = new Harness();
        $h->start($kind === 'expand' ? 'E06' : 'N18');
        TutorLoopTest::pose($h, 'practice', $kind === 'expand' ? 'E06' : 'N18', $kind, $expr, $stated);
        $h->mock->queue('ENVELOPE', ['set' => ['reading' => ['is_answer' => true, 'text' => $reading, 'other_readings' => [], 'clarifying_question' => $question]]]);
        $before = $h->lines();
        $h->send($entry);
        return [$h, $h->lastLines($before)];
    }

    public function testReadingsAreAcceptedOnlyWhenTheyReExpressTheEntry(): void
    {
        [$h] = $this->readAs('numeric_step', '4 * 4', '16', '16 because it\'s 4 x 4', '16');
        $this->assertSame(['16', 'CORRECT'], [$h->attempts()[0]['read_as'], $h->attempts()[0]['verdict']], 'working separated by words may be left out');
        foreach ([
            ['expand', '2(x + 1)', '2x + 2', '2 x x + 2', '2x'],
            ['expand', '2(x + 1)', '2x + 2', '2x plus 2', '2x'],
            ['expand', '2(x + 1)', '2x + 2', '2(x + 1) I think', '2x + 1'],
            ['numeric_step', '5 - 2', '3', '2 - 5', '5 - 2'],
            ['numeric_step', '0 - 5', '-5', 'minus five', '5'],
        ] as [$kind, $expr, $stated, $entry, $reading]) {
            [$h, $new] = $this->readAs($kind, $expr, $stated, $entry, $reading);
            $this->assertCount(1, $h->eventsOf('READING_REJECTED'), "$entry read as $reading");
            $this->assertSame([], $h->attempts(), "$entry read as $reading: nothing graded");
            $this->assertSame([['student', 'message'], ['tutor', 'clarify']], array_map(fn ($l) => [$l['role'], $l['kind']], $new), 'the student sees the AI\'s clarifying question');
            $this->assertSame('Did you mean the whole expression?', $new[1]['text']);
            $this->assertSame('practice', $h->view['open_problem']['purpose'], 'the problem stays open');
        }
        // With no clarifying question from the AI, the reply call answers; code writes no line.
        [$h, $new] = $this->readAs('expand', '2(x + 1)', '2x + 2', '2x plus 2', '2x', null);
        $this->assertSame(['student', 'tutor'], array_column($new, 'role'));
        $this->assertSame('ENTRY_NOT_READ', TutorLoopTest::context($h)['TURN']['kind']);
    }

    public function testNumberWordsAndReversedEquations(): void
    {
        $h = new Harness();
        $h->start('Q06');
        TutorLoopTest::pose($h, 'practice', 'Q06', 'linear_equation', '2x + 3 = 53', '25');
        $calls = $h->mock->calls;
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Here is the next one.', 'item' => [
            'purpose' => 'practice', 'skill_id' => 'Q06', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'linear_equation', 'expression' => '4x + 2 = 14'], 'prompt' => '', 'stated_answer' => '3']]]);
        $h->send('twenty five');
        $this->assertSame(1, $h->mock->calls - $calls, 'code reads a number written in words; no reading call');
        $a = $h->attempts()[0];
        $this->assertSame(['twenty five', '25', EntryReader::NOTE_NUMBER_WORDS, 'CORRECT'], [$a['entry'], $a['read_as'], $a['reading_note'], $a['verdict']]);
        $this->assertStringContainsString('Read as 25.', $this->resultLine($h));
        $this->assertSame('Solve 4x + 2 = 14 for x.', $h->view['open_problem']['prompt']);
        $h->send('3 = x');
        $this->assertSame(['3 = x', 'CORRECT'], [$h->attempts()[1]['read_as'], $h->attempts()[1]['verdict']], 'a reversed equation is accepted');
    }

    private function resultLine(Harness $h): string
    {
        $results = array_values(array_filter($h->lines(), fn ($l) => $l['kind'] === 'result'));
        return (string) end($results)['text'];
    }
}
