<?php
declare(strict_types=1);

namespace AlgebraEngine\Curriculum;

use AlgebraEngine\Support\Json;

/**
 * Loads the canonical curriculum map. Only canonical IDs and edges are
 * returned; nothing is invented when a record is absent.
 */
final class CurriculumRepository
{
    public const CURRICULUM_ID = 'ACE-ALGEBRA1';
    public const CURRICULUM_VERSION = '1.1.0';

    /** @var array<string,array<string,mixed>> */
    private array $skills = [];
    private array $metadata;

    /** @param array<string,mixed> $map */
    private function __construct(array $map)
    {
        $this->metadata = $map['metadata'] ?? [];
        if (($this->metadata['curriculum_id'] ?? null) !== self::CURRICULUM_ID
            || ($this->metadata['curriculum_version'] ?? null) !== self::CURRICULUM_VERSION) {
            throw new \RuntimeException('Unexpected curriculum identity');
        }
        foreach ($map['skills'] ?? [] as $s) {
            $this->skills[$s['skill_id']] = $s;
        }
    }

    public static function fromFile(string $path): self
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Curriculum file not readable');
        }
        return new self(Json::decode($raw, 64));
    }

    /** Test seam: a repository with some canonical records withheld (HOST-07). */
    public function without(array $skillIds): self
    {
        $copy = clone $this;
        foreach ($skillIds as $id) {
            unset($copy->skills[$id]);
        }
        return $copy;
    }

    public function has(string $id): bool
    {
        return isset($this->skills[$id]);
    }

    /** @return array<string,mixed> */
    public function skill(string $id): array
    {
        return $this->skills[$id] ?? throw new MissingCurriculum($id);
    }

    public function title(string $id): string
    {
        return (string) $this->skill($id)['statement'];
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->skills);
    }

    public function count(): int
    {
        return count($this->skills);
    }

    /**
     * Every prerequisite the record declares, in any edge form.
     *
     * @return list<string>
     */
    public function declaredPrerequisites(string $id): array
    {
        $p = $this->skill($id)['prerequisites'];
        $ids = $p['all_of'];
        foreach ($p['alternative_paths'] as $path) {
            $ids = array_merge($ids, $path['all_of']);
        }
        foreach ($p['conditional'] as $c) {
            $ids = array_merge($ids, $c['requires']);
        }
        return array_values(array_unique($ids));
    }

    public function isDeclaredPrerequisite(string $parent, string $child): bool
    {
        return in_array($child, $this->declaredPrerequisites($parent), true);
    }

    /** @return array{edge_type:string,edge_condition:?string} */
    public function edge(string $parent, string $child): array
    {
        $p = $this->skill($parent)['prerequisites'];
        if (in_array($child, $p['all_of'], true)) {
            return ['edge_type' => 'all_of', 'edge_condition' => null];
        }
        foreach ($p['alternative_paths'] as $path) {
            if (in_array($child, $path['all_of'], true)) {
                return ['edge_type' => 'alternative', 'edge_condition' => 'path:' . $path['path_id']];
            }
        }
        foreach ($p['conditional'] as $c) {
            if (in_array($child, $c['requires'], true)) {
                return ['edge_type' => 'conditional', 'edge_condition' => $c['when']];
            }
        }
        throw new \InvalidArgumentException("$child is not a declared prerequisite of $parent");
    }

    /**
     * Resolve the prerequisites that apply to an item with the given features.
     * all_of edges always apply; an alternative path applies only when the
     * item selects it ("path:<id>"); a conditional edge applies only when its
     * condition is one of the item's declared features. Alternatives are never
     * required jointly.
     *
     * @param list<string> $features
     * @return array{required:list<string>,selected_path:?string,unselected_paths:list<string>,conditions_applied:list<string>}
     */
    public function resolvePrerequisites(string $id, array $features): array
    {
        $p = $this->skill($id)['prerequisites'];
        $required = $p['all_of'];
        $selected = null;
        $unselected = [];
        foreach ($p['alternative_paths'] as $path) {
            if (in_array('path:' . $path['path_id'], $features, true) && $selected === null) {
                $selected = $path['path_id'];
                $required = array_merge($required, $path['all_of']);
            } else {
                $unselected[] = $path['path_id'];
            }
        }
        $applied = [];
        foreach ($p['conditional'] as $c) {
            if (in_array($c['when'], $features, true)) {
                $applied[] = $c['when'];
                $required = array_merge($required, $c['requires']);
            }
        }
        return [
            'required' => array_values(array_unique($required)),
            'selected_path' => $selected,
            'unselected_paths' => $unselected,
            'conditions_applied' => $applied,
        ];
    }

    /**
     * Record for model context: assessment.anchor_item is stripped (addendum
     * F2). Anchors remain available to tests and the ItemFactory only.
     *
     * @return array<string,mixed>
     */
    public function promptRecord(string $id): array
    {
        $s = $this->skill($id);
        unset($s['assessment']['anchor_item']);
        return [
            'skill_id' => $s['skill_id'],
            'statement' => $s['statement'],
            'curricular_role' => $s['curricular_role'],
            'observable_performance' => $s['observable_performance'],
            'scope' => $s['scope'],
            'prerequisites' => $s['prerequisites'],
            'assessment' => $s['assessment'],
            'diagnostic_guidance' => $s['diagnostic_guidance'],
            'instructional_guidance' => $s['instructional_guidance'],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->skills as $s) {
            $out[] = [
                'skill_id' => $s['skill_id'],
                'title' => $s['statement'],
                'domain_id' => $s['domain_id'],
                'domain_name' => $s['domain_name'],
                'curricular_role' => $s['curricular_role'],
            ];
        }
        return $out;
    }
}
