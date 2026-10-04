<?php

namespace App\Filament\Core\Forms\Components;

class TextInput extends Component
{
    protected string $type = 'text';

    public static function make(string $name): static
    {
        $comp = new static;
        $comp->name = $name;

        return $comp;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function email(): static
    {
        $this->type = 'email';

        return $this;
    }

    public function numeric(): static
    {
        $this->type = 'number';

        return $this;
    }

    public function tel(): static
    {
        $this->type = 'tel';

        return $this;
    }
}
