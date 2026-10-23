<?php
declare(strict_types=1);

namespace AlgebraEngine\Capability;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Items\ItemFactory;

/**
 * Tested runtime registry (round 6: the AI tutor may pose problems only for
 * these skills, and check problems only from their recipes). A skill is promoted only for what is implemented
 * and covered by tests; it is never copied from the curriculum's required
 * capability list. Everything outside the beta list is UNAVAILABLE.
 */
final class CapabilityRegistry
{
    public const TEST_REFERENCES = [
        'Q07' => ['tests/Integration/TutorLoopTest.php::testJourneyStepsBackAndReturnsWithGoalsComputedByCode', 'tests/Unit/Tutor/ProblemInstrumentsTest.php', 'tests/Unit/Items/TemplatesTest.php'],
        'E06' => ['tests/Integration/TutorLoopTest.php::testJourneyStepsBackAndReturnsWithGoalsComputedByCode', 'tests/Integration/OwnerCasesTest.php', 'tests/Unit/Items/TemplatesTest.php'],
        'Q06' => ['tests/Integration/CheatingTest.php', 'tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore'],
        'Q05' => ['tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore', 'tests/Unit/Tutor/ProblemInstrumentsTest.php'],
        'N06' => ['tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore', 'tests/Unit/Tutor/LearnerInstrumentsTest.php'],
        'Q04' => ['tests/Unit/Items/TemplatesTest.php', 'tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore'],
        'N03' => ['tests/Unit/Items/TemplatesTest.php', 'tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore'],
        'N04' => ['tests/Unit/Items/TemplatesTest.php', 'tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore'],
        'N05' => ['tests/Unit/Tutor/LearnerInstrumentsTest.php', 'tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore'],
        'N18' => ['tests/Integration/OwnerCasesTest.php', 'tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore'],
    ];

    public function __construct(private CurriculumRepository $curriculum, private ItemFactory $factory)
    {
    }

    /** @return list<array<string,mixed>> one entry per curriculum skill */
    public function all(): array
    {
        $out = [];
        foreach ($this->curriculum->ids() as $id) {
            $out[] = $this->entry($id);
        }
        return $out;
    }

    public function entry(string $id): array
    {
        $s = $this->curriculum->skill($id);
        $base = [
            'skill_id' => $id,
            'title' => $s['statement'],
            'curricular_role' => $s['curricular_role'],
            'required_response_types' => $s['assessment']['accepted_response_types'],
            'required_variants' => $s['assessment']['required_variants'],
        ];
        if (!BetaScope::contains($id)) {
            return $base + ['runtime_status' => 'UNAVAILABLE', 'implemented_variants' => [], 'checkers' => [], 'remaining' => ['Not in the beta skill list.'], 'test_references' => []];
        }
        $variants = [];
        $checkers = [];
        foreach ($this->factory->forSkill($id) as $t) {
            $variants[] = $t->variantId() . ' (' . $t->id() . ', ' . $t->responseType() . ')';
            $checkers[] = match ($t->responseType()) {
                'scalar' => 'exact rational',
                'expression' => 'polynomial identity + expanded form',
                'classification' => 'exact option set',
            };
        }
        $three = BetaScope::threeVariant($id);
        return $base + [
            'runtime_status' => $three ? 'AVAILABLE_SCORED' : 'PARTIAL_COMPONENTS',
            'implemented_variants' => $variants,
            'checkers' => array_values(array_unique($checkers)),
            'remaining' => $three
                ? ['Written explanations are not assessed; choice check questions come only from the contrast recipes, so rejecting an invalid method is checked by selection only.']
                : ['Conceptual evidence (explain or reject an invalid method) not implemented.', 'No new-form check pattern.', 'The goal covers the numeric part only.'],
            'test_references' => self::TEST_REFERENCES[$id],
        ];
    }
}
