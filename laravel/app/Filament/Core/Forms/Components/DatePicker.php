<?php

namespace App\Filament\Core\Forms\Components;

class DatePicker extends Component
{
    public static function make(string $name): static
    {
        $comp = new static;
        $comp->name = $name;

        return $comp;
    }
}
