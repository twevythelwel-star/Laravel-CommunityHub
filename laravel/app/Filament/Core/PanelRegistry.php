<?php

namespace App\Filament\Core;

class PanelRegistry
{
    /** @var array<string, Panel> */
    protected static array $panels = [];

    public static function register(Panel $panel): void
    {
        static::$panels[$panel->getId()] = $panel;
    }

    public static function get(string $id): ?Panel
    {
        return static::$panels[$id] ?? null;
    }

    /** @return array<string, Panel> */
    public static function all(): array
    {
        return static::$panels;
    }

    public static function clear(): void
    {
        static::$panels = [];
    }
}
