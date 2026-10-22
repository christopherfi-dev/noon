<?php
declare(strict_types=1);

namespace AlgebraEngine\Storage;

final class StorageUnavailable extends \RuntimeException
{
    public function __construct(private string $reason)
    {
        parent::__construct($reason);
    }
}
