<?php

namespace App\Filament\Core\Forms\Components;

class Grid
{
    protected int $columns = 2;

    protected array $schema = [];

    public static function make(int $columns = 2): static
    {
        $grid = new static;
        $grid->columns = $columns;

        return $grid;
    }

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }
}
