<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Core\Forms\Components\Grid;
use App\Filament\Core\Forms\Components\Select;
use App\Filament\Core\Forms\Components\TextInput;
use App\Filament\Core\Forms\Form;
use App\Filament\Core\Infolists\Components\SectionEntry;
use App\Filament\Core\Infolists\Components\TextEntry;
use App\Filament\Core\Infolists\Infolist;
use App\Filament\Core\Resource;
use App\Filament\Core\Tables\Components\Action;
use App\Filament\Core\Tables\Components\BadgeColumn;
use App\Filament\Core\Tables\Components\SelectFilter;
use App\Filament\Core\Tables\Components\TextColumn;
use App\Filament\Core\Tables\Table;
use App\Models\User;

class ResidentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Community & Residents';

    protected static ?string $navigationLabel = 'Residents & Staff';

    protected static ?string $slug = 'residents';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required(),

                    TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required(),

                    TextInput::make('phone')
                        ->label('Contact Phone')
                        ->tel(),

                    Select::make('role')
                        ->label('Community Role')
                        ->options(collect(UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->toArray())
                        ->required(),

                    TextInput::make('lot')
                        ->label('Assigned Lot / Unit')
                        ->required(),

                    TextInput::make('street')
                        ->label('Street Address')
                        ->default('Palm Boulevard')
                        ->required(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('lot')
                    ->label('Lot / Residence')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('role')
                    ->label('Role')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Account Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'deactivated',
                    ]),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(collect(UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->toArray()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'deactivated' => 'Deactivated',
                    ]),
            ])
            ->actions([
                Action::make('toggleStatus')
                    ->label('Toggle Status')
                    ->icon('heroicon-o-arrows-right-left'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                SectionEntry::make('Resident Profile')
                    ->schema([
                        TextEntry::make('name')->label('Full Name'),
                        TextEntry::make('email')->label('Email Address')->copyable(),
                        TextEntry::make('phone')->label('Phone Number'),
                        TextEntry::make('role')->label('Assigned Role')->badge(),
                        TextEntry::make('lot')->label('Lot Number'),
                        TextEntry::make('status')->label('Status')->badge(),
                    ]),
            ]);
    }
}
