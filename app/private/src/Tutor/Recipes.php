<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Support\RandomSource;

/**
 * Round 6 §3.2 and §4.3: fresh recipe problems pre-generated before each AI
 * call, two per relevant skill, so the AI never needs a round trip to get
 * one. For Q07 and E06 the two cover the kinds of question the skill's goal
 * still needs (a solve or expansion, a choice question, a new form).
 */
final class Recipes
{
    public const PER_SKILL = 2;
    private const TRIES = 60;

    public function __construct(private ProblemFactory $problems)
    {
    }

    /**
     * @param list<string> $skills relevant skills, in order
     * @param list<string> $usedFingerprints problems already shown this session
     * @param array<string,list<string>> $neededVariants per skill, the variants its goal still needs
     * @param array<string,list<string>> $usedNumberSets per skill, the number sets of problems already shown
     * @return array<string,array> problem records keyed by recipe reference ("R1", "R2", …); purpose is set when one is chosen
     */
    public function pregenerate(array $skills, RandomSource $rng, array $usedFingerprints, array $neededVariants = [], array $usedNumberSets = []): array
    {
        $out = [];
        $seen = array_flip($usedFingerprints);
        $n = 0;
        foreach (array_values(array_unique($skills)) as $skill) {
            foreach ($this->variantsFor($skill, $neededVariants[$skill] ?? []) as $variant) {
                $problem = $this->fresh($skill, $variant, $rng, $seen, $usedNumberSets[$skill] ?? []);
                if ($problem === null) {
                    continue;
                }
                $seen[$problem['fingerprint']] = true;
                $usedNumberSets[$skill][] = ProblemFactory::numberSet($problem);
                $out['R' . (++$n)] = $problem;
            }
        }
        return $out;
    }

    /** @return list<string> exactly PER_SKILL variants */
    private function variantsFor(string $skill, array $needed): array
    {
        $have = $this->problems->items()->variantsFor($skill);
        if (!BetaScope::threeVariant($skill)) {
            return array_fill(0, self::PER_SKILL, 'constructed');
        }
        $order = array_values(array_intersect(BetaScope::VARIANTS, $needed));
        foreach (BetaScope::VARIANTS as $v) {
            if (!in_array($v, $order, true)) {
                $order[] = $v;
            }
        }
        return array_slice(array_values(array_intersect($order, $have)), 0, self::PER_SKILL);
    }

    /**
     * Round 8 §3.6: every prepared problem may be chosen as a check problem,
     * so each one satisfies the check rule (its single-number answer is not a
     * number in the question). None is returned rather than one that fails it.
     */
    private function fresh(string $skill, string $variant, RandomSource $rng, array $seen, array $usedSets): ?array
    {
        $template = $this->problems->items()->templateFor($skill, $variant);
        for ($i = 0; $i < self::TRIES; $i++) {
            $params = $template->generate($rng);
            $problem = $this->problems->fromRecipe($template->id(), $params, 'PRACTICE');
            if (isset($seen[$problem['fingerprint']]) || in_array(ProblemFactory::numberSet($problem), $usedSets, true)) {
                continue;
            }
            if (!ProblemFactory::answerShownInProblem($problem)) {
                return $problem;
            }
        }
        return null;
    }
}
