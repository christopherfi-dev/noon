<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Support;

use AlgebraEngine\Support\ProblemText;
use PHPUnit\Framework\TestCase;

/** Round UI (U1, U3): the main equation or expression of a prompt, and the line that names an earlier answer. */
final class ProblemTextTest extends TestCase
{
    public function testMainExpressionOfEachPromptShape(): void
    {
        foreach ([
            'Solve 4(x − 9) = −68 for x.' => '4(x − 9) = −68',
            'Expand 2(x + 7). Write your answer without parentheses.' => '2(x + 7)',
            "Which of these expressions are equal to 8(x + 8) for every value of x? Select every one that is.\nA) x + 64\nB) 8x + 64" => '8(x + 8)',
            "Here is an equation: 4(x − 2) = 4. Which of these first steps give an equation with exactly the same solution?\nA) Multiply both terms in the parentheses by 4: 4x − 8 = 4" => '4(x − 2) = 4',
            'Solve 2(x + 3) + 2 = −6 for x.' => '2(x + 3) + 2 = −6',
            'Solve −15 = 3(x − 2) for x.' => '−15 = 3(x − 2)',
            'What is −12 ÷ (−3)?' => '−12 ÷ (−3)',
            'What is 7 + (−9)?' => '7 + (−9)',
            'Expand 3(x + y + 2).' => '3(x + y + 2)',
            'How many x\'s are in all 4 groups? Write them as one term.' => '',
            'Response matches distribution-to-x-only.' => '',
        ] as $prompt => $main) {
            $this->assertSame($main, ProblemText::main($prompt), $prompt);
        }
    }

    public function testAnswerLine(): void
    {
        $this->assertSame('On 8(x + 5) = 64 you answered 13.', ProblemText::answerLine('Solve 8(x + 5) = 64 for x.', 'scalar', ' 13 '));
        $this->assertSame('On 4(x − 2) = 4 you chose A and C.', ProblemText::answerLine("Here is an equation: 4(x − 2) = 4. Which?\nA) a\nC) c", 'classification', 'A,C'));
        $this->assertSame('You answered 3x.', ProblemText::answerLine('How many x\'s are in all 3 groups?', 'expression', '3x'));
        $this->assertNull(ProblemText::answerLine('Solve 8(x + 5) = 64 for x.', 'scalar', '   '));
        $long = str_repeat('9', 80);
        $this->assertSame('On 8(x + 5) = 64 you answered ' . str_repeat('9', 39) . '….', ProblemText::answerLine('Solve 8(x + 5) = 64 for x.', 'scalar', $long));
        $this->assertNull(ProblemText::answerLine('Solve 8(x + 5) = 64 for x.', 'scalar', 'the answer key'), 'never a host line with internal terms');
    }
}
