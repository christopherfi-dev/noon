<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Support\RandomSource;

/**
 * A server-owned item template. The host renders the prompt and derives the
 * key from parameters; a model may only propose parameters.
 */
interface Template
{
    public function id(): string;

    public function skillId(): string;

    /** constructed | contrast | transfer */
    public function variantId(): string;

    public function componentId(): string;

    /** scalar | expression | classification */
    public function responseType(): string;

    /** Human/model-readable parameter description with bounds. @return array<string,string> */
    public function parameterSpec(): array;

    /** @param array<string,mixed>|null $avoid parameters of an item to vary from */
    public function generate(RandomSource $rng, ?array $avoid = null): array;

    /** @return list<string> validation errors; empty when acceptable */
    public function validate(array $p): array;

    public function render(array $p): string;

    /** Item answer_spec as in item.schema.json. */
    public function answerSpec(array $p): array;

    /** @return array<string,mixed> private mathematical objects (equation, options, signature values) */
    public function mathematicalObjects(array $p): array;

    public function inputHelp(): string;

    public function checkingScope(): string;

    /**
     * Error-pattern observations for a checked response. Each entry:
     * ['code' => string, 'label' => 'matches …', 'branch' => skill id or null].
     * A match is an observation, never a confirmed cause.
     *
     * @return list<array{code:string,label:string,branch:?string}>
     */
    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array;

    /** Learner-facing statement of what an answer did, for a matched signature code. */
    public function signatureFeedback(array $p, string $code): ?string;
}
