<?php

namespace App\Filament\Core\Infolists\Components;

class SectionEntry
{
    protected string $heading;

    protected array $schema = [];

    public static function make(string $heading): static
    {
        $s = new static;
        $s->heading = $heading;

        return $s;
    }

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function getHeading(): string
    {
        return $this->heading;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }
}
