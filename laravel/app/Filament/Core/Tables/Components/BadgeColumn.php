<?php

namespace App\Filament\Core\Tables\Components;

class BadgeColumn extends Column
{
    protected array $colors = [];

    public static function make(string $name): static
    {
        $col = new static;
        $col->name = $name;

        return $col;
    }

    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function getColors(): array
    {
        return $this->colors;
    }
}
