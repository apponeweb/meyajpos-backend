<?php

namespace App\Service;

final class TenantContext
{
    private ?string $schema = null;

    public function setSchema(string $schema): void
    {
        $this->schema = $schema;
    }

    public function getSchema(): ?string
    {
        return $this->schema;
    }

    public function hasTenant(): bool
    {
        return $this->schema !== null;
    }
}