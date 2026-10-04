<?php

namespace App\Filament\Core\Infolists;

class Infolist
{
    protected array $schema = [];

    public static function make(): static
    {
        return new static;
    }

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }
}
