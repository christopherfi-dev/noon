<?php
declare(strict_types=1);

namespace AlgebraEngine\Curriculum;

final class MissingCurriculum extends \RuntimeException
{
    public function __construct(public string $skillId)
    {
        parent::__construct('Canonical skill record not available: ' . $skillId);
    }
}
