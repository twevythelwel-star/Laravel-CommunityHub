<?php

namespace App\Filament\Core\Forms;

class Form
{
    protected array $schema = [];

    protected ?string $model = null;

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

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }
}
