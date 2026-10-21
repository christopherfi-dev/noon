<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Items\Templates;

/** Registry of server-owned templates for the beta skills. */
final class ItemFactory
{
    /** @var array<string,Template> */
    private array $templates = [];

    public function __construct()
    {
        foreach ([
            new Templates\SignedAdd(),
            new Templates\SignedSubtract(),
            new Templates\SignedMultiply(),
            new Templates\SignedDivide(),
            new Templates\WholeMultiplyDivide(),
            new Templates\OneStepAdd(),
            new Templates\OneStepMultiply(),
            new Templates\TwoStep(),
            new Templates\PositiveDistribution(),
            new Templates\DistributionContrast(),
            new Templates\DistributionTransfer(),
            new Templates\GroupedLinear(),
            new Templates\GroupedFirstStepContrast(),
            new Templates\GroupedLinearTransfer(),
        ] as $t) {
            $this->templates[$t->id()] = $t;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->templates[$id]);
    }

    public function get(string $id): Template
    {
        return $this->templates[$id] ?? throw new \InvalidArgumentException('Unknown template ' . $id);
    }

    /** @return list<Template> */
    public function forSkill(string $skillId): array
    {
        return array_values(array_filter($this->templates, fn (Template $t) => $t->skillId() === $skillId));
    }

    /** @return list<string> variant ids implemented for a skill, in canonical order */
    public function variantsFor(string $skillId): array
    {
        $order = ['constructed', 'contrast', 'transfer'];
        $have = array_map(fn (Template $t) => $t->variantId(), $this->forSkill($skillId));
        return array_values(array_filter($order, fn ($v) => in_array($v, $have, true)));
    }

    public function templateFor(string $skillId, string $variantId): Template
    {
        foreach ($this->forSkill($skillId) as $t) {
            if ($t->variantId() === $variantId) {
                return $t;
            }
        }
        throw new \InvalidArgumentException("No template for $skillId/$variantId");
    }

    /** Semantic fingerprint: template + normalized parameters. Changing an ID is not freshness. */
    public static function fingerprint(string $templateId, array $params): string
    {
        $norm = $params;
        ksort($norm);
        return hash('sha256', $templateId . '|' . json_encode($norm, JSON_THROW_ON_ERROR));
    }

    /** @return array<string,Template> */
    public function all(): array
    {
        return $this->templates;
    }
}
