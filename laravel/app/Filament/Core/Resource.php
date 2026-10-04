<?php

namespace App\Filament\Core;

use App\Filament\Core\Forms\Form;
use App\Filament\Core\Infolists\Infolist;
use App\Filament\Core\Tables\Table;

abstract class Resource
{
    protected static ?string $model = null;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = null;

    public static function getModel(): ?string
    {
        return static::$model;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? class_basename(static::$model ?? 'Resource');
    }

    public static function getSlug(): string
    {
        return static::$slug ?? strtolower(class_basename(static::$model ?? 'resources'));
    }

    abstract public static function form(Form $form): Form;

    abstract public static function table(Table $table): Table;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist;
    }
}
