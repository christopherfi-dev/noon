<?php
declare(strict_types=1);

namespace AlgebraEngine\Capability;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Items\ItemFactory;

/**
 * Tested runtime registry. A skill is promoted only for what is implemented
 * and covered by tests; it is never copied from the curriculum's required
 * capability list. Everything outside the beta list is UNAVAILABLE.
 */
final class CapabilityRegistry
{
    public const TEST_REFERENCES = [
        'Q07' => ['tests/Integration/BetaJourneyTest.php::testB1DistributionJourney', 'tests/Integration/BetaJourneyTest.php::testB2ProbeCorrectNoDescent', 'tests/Unit/Items/TemplatesTest.php'],
        'E06' => ['tests/Integration/BetaJourneyTest.php::testB1DistributionJourney', 'tests/Unit/Items/TemplatesTest.php'],
        'Q06' => ['tests/Integration/BetaJourneyTest.php::testB3DeepDescentAndReturn', 'tests/Integration/EvidenceTest.php::testEvid03NumericOnlySkill'],
        'Q05' => ['tests/Integration/BetaJourneyTest.php::testB3DeepDescentAndReturn'],
        'N06' => ['tests/Integration/BetaJourneyTest.php::testB3DeepDescentAndReturn'],
        'Q04' => ['tests/Unit/Items/TemplatesTest.php', 'tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore'],
        'N03' => ['tests/Unit/Items/TemplatesTest.php', 'tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore'],
        'N04' => ['tests/Unit/Items/TemplatesTest.php', 'tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore'],
        'N05' => ['tests/Unit/Items/TemplatesTest.php', 'tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore'],
        'N18' => ['tests/Unit/Items/TemplatesTest.php', 'tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore'],
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
                ? ['Written explanations are not assessed; the contrast item checks rejection of invalid methods by selection only.']
                : ['Conceptual evidence (explain or reject an invalid method) not implemented.', 'Transfer variant not implemented.', 'Criterion reported as COMPONENT_CRITERION_MET only.'],
            'test_references' => self::TEST_REFERENCES[$id],
        ];
    }
}
