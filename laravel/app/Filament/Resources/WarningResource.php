<?php

namespace App\Filament\Resources;

use App\Filament\Core\Forms\Components\Textarea;
use App\Filament\Core\Forms\Components\TextInput;
use App\Filament\Core\Forms\Form;
use App\Filament\Core\Resource;
use App\Filament\Core\Tables\Components\Action;
use App\Filament\Core\Tables\Components\TextColumn;
use App\Filament\Core\Tables\Table;
use App\Models\Warning;

class WarningResource extends Resource
{
    protected static ?string $model = Warning::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Safety & Security';

    protected static ?string $navigationLabel = 'Incident Warnings';

    protected static ?string $slug = 'warnings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Notice Headline')
                    ->required()
                    ->placeholder('e.g. Broken gate arm at Gate 2'),

                Textarea::make('description')
                    ->label('Incident Details')
                    ->rows(4)
                    ->required()
                    ->placeholder('Describe the situation and recommended precautions...'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Headline')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('author_name')
                    ->label('Reported By')
                    ->searchable(),

                TextColumn::make('issued_at')
                    ->label('Broadcast Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success'),

                Action::make('deny')
                    ->label('Deny')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger'),
            ]);
    }
}
