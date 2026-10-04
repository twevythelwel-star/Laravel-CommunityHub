<?php

namespace App\Filament\Core\Tables\Components;

class TextColumn extends Column
{
    protected ?int $limit = null;

    protected bool $dateTime = false;

    public static function make(string $name): static
    {
        $col = new static;
        $col->name = $name;

        return $col;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function dateTime(): static
    {
        $this->dateTime = true;

        return $this;
    }
}
