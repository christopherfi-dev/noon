<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Support\Json;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Full JSON Schema (draft 2020-12) validation with opis/json-schema. Schemas
 * are registered from local files under a non-resolvable base URI; there is
 * no network schema loading and no runtime schema change from input.
 */
final class SchemaValidator
{
    private const BASE = 'https://algebra-engine.invalid/contracts/';
    private Validator $validator;

    public function __construct(private readonly string $contractsDir)
    {
        $this->validator = new Validator();
        $this->validator->setMaxErrors(10);
        foreach (glob($contractsDir . '/*.schema.json') ?: [] as $file) {
            $this->validator->resolver()->registerFile(self::BASE . basename($file), $file);
        }
    }

    /**
     * @param string $schema file name, e.g. "model-envelope.schema.json"
     * @return list<string> error messages with JSON pointer paths; empty when valid
     */
    public function errors(mixed $data, string $schema): array
    {
        // Objects decoded from raw JSON keep the {} / [] distinction; host arrays are converted.
        $object = is_object($data) ? $data : Json::toObject($data);
        $result = $this->validator->validate($object, self::BASE . $schema);
        if ($result->isValid()) {
            return [];
        }
        $out = [];
        foreach ((new ErrorFormatter())->format($result->error(), true) as $path => $messages) {
            foreach ((array) $messages as $m) {
                $out[] = ($path === '' ? '/' : $path) . ': ' . $m;
            }
        }
        return $out;
    }

    /** Decoded schema (for building the envelope skeleton). */
    public function schema(string $schema): array
    {
        return Json::decode((string) file_get_contents($this->contractsDir . '/' . $schema), 64);
    }
}
