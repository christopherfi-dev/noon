<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Curriculum;

use AlgebraEngine\Curriculum\CurriculumRepository;
use PHPUnit\Framework\TestCase;

/** The canonical curriculum reader, kept as an instrument: alternative paths and conditional edges. */
final class CurriculumPathsTest extends TestCase
{
    /** FLOW-05: alternative paths are selected by problem features, never required jointly; conditions apply only when matched. */
    public function testFlow05AlternativeDependencies(): void
    {
        $c = CurriculumRepository::fromFile(ALGEBRA_REPO_ROOT . '/curriculum/algebra-map.json');
        $fraction = $c->resolvePrerequisites('Q10', ['path:fractions', 'fractional-coefficient variant']);
        sort($fraction['required']);
        $this->assertSame(['N09', 'N10', 'Q06'], $fraction['required']);
        $this->assertSame('fractions', $fraction['selected_path']);
        $this->assertSame(['decimals'], $fraction['unselected_paths']);
        $decimal = $c->resolvePrerequisites('Q10', ['path:decimals', 'decimal-coefficient variant']);
        sort($decimal['required']);
        $this->assertSame(['N11', 'Q06'], $decimal['required']);
        $plain = $c->resolvePrerequisites('Q10', []);
        $this->assertSame(['Q06'], $plain['required'], 'no alternative is required jointly');
        // Conditional edges of a beta skill apply only when the problem has the feature.
        $this->assertSame(['N02', 'N17'], $c->resolvePrerequisites('N03', [])['required']);
        $this->assertContains('N09', $c->resolvePrerequisites('N03', ['item uses signed fractions'])['required']);
        $this->assertSame(['edge_type' => 'alternative', 'edge_condition' => 'path:fractions'], $c->edge('Q10', 'N09'));
    }
}
