<?php

namespace App\Filament\Core\Forms\Components;

class Select extends Component
{
    protected array $options = [];

    public static function make(string $name): static
    {
        $comp = new static;
        $comp->name = $name;

        return $comp;
    }

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
