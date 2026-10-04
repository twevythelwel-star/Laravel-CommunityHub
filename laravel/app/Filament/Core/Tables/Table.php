<?php

namespace App\Filament\Core\Tables;

class Table
{
    protected array $columns = [];

    protected array $filters = [];

    protected array $actions = [];

    protected array $bulkActions = [];

    protected ?string $model = null;

    public static function make(): static
    {
        return new static;
    }

    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function bulkActions(array $bulkActions): static
    {
        $this->bulkActions = $bulkActions;

        return $this;
    }

    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }
}
