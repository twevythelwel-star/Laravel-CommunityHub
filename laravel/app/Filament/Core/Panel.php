<?php

namespace App\Filament\Core;

class Panel
{
    protected string $id;

    protected string $path;

    protected string $brandName = 'Community Hub';

    protected array $resources = [];

    protected array $widgets = [];

    protected array $navigationGroups = [];

    protected string $authGuard = 'web';

    protected ?string $homeUrl = null;

    public static function make(string $id): static
    {
        $panel = new static;
        $panel->id = $id;
        $panel->path = $id;

        return $panel;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function path(string $path): static
    {
        $this->path = trim($path, '/');

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function brandName(string $name): static
    {
        $this->brandName = $name;

        return $this;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function resources(array $resources): static
    {
        $this->resources = $resources;

        return $this;
    }

    public function getResources(): array
    {
        return $this->resources;
    }

    public function widgets(array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    public function getWidgets(): array
    {
        return $this->widgets;
    }

    public function navigationGroups(array $groups): static
    {
        $this->navigationGroups = $groups;

        return $this;
    }

    public function getNavigationGroups(): array
    {
        return $this->navigationGroups;
    }

    public function authGuard(string $guard): static
    {
        $this->authGuard = $guard;

        return $this;
    }

    public function getAuthGuard(): string
    {
        return $this->authGuard;
    }
}
