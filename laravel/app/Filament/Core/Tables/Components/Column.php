<?php

namespace App\Filament\Core\Tables\Components;

abstract class Column
{
    protected string $name;

    protected ?string $label = null;

    protected bool $sortable = false;

    protected bool $searchable = false;

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucwords(str_replace('_', ' ', $this->name));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }
}
