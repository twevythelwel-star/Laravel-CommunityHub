<?php

namespace App\Filament\Core\Forms\Components;

class Textarea extends Component
{
    protected int $rows = 3;

    public static function make(string $name): static
    {
        $comp = new static;
        $comp->name = $name;

        return $comp;
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function getRows(): int
    {
        return $this->rows;
    }
}
