<?php

namespace App\Filament\Resources;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Filament\Core\Forms\Components\DatePicker;
use App\Filament\Core\Forms\Components\Grid;
use App\Filament\Core\Forms\Components\Section;
use App\Filament\Core\Forms\Components\Select;
use App\Filament\Core\Forms\Components\Tab;
use App\Filament\Core\Forms\Components\Tabs;
use App\Filament\Core\Forms\Components\TextInput;
use App\Filament\Core\Forms\Components\Toggle;
use App\Filament\Core\Forms\Form;
use App\Filament\Core\Infolists\Components\SectionEntry;
use App\Filament\Core\Infolists\Components\TextEntry;
use App\Filament\Core\Infolists\Infolist;
use App\Filament\Core\Resource;
use App\Filament\Core\Tables\Components\Action;
use App\Filament\Core\Tables\Components\BadgeColumn;
use App\Filament\Core\Tables\Components\BulkAction;
use App\Filament\Core\Tables\Components\SelectFilter;
use App\Filament\Core\Tables\Components\TextColumn;
use App\Filament\Core\Tables\Table;
use App\Models\GatePass;

class GatePassResource extends Resource
{
    protected static ?string $model = GatePass::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Access & Gate Operations';

    protected static ?string $navigationLabel = 'Gate Passes';

    protected static ?string $slug = 'gate-passes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('PassDetails')
                    ->tabs([
                        Tab::make('Core Clearance')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('holder_name')
                                        ->label('Holder / Visitor Name')
                                        ->required()
                                        ->placeholder('e.g. John Doe'),

                                    Select::make('category')
                                        ->label('Pass Category Profile')
                                        ->options(collect(PassCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray())
                                        ->required(),

                                    TextInput::make('property')
                                        ->label('Destination Property')
                                        ->required()
                                        ->placeholder('Lot 42, Pinecrest Way'),

                                    TextInput::make('access_zone')
                                        ->label('Access Perimeter Zone')
                                        ->default('ZONE-HOST-RESIDENCE')
                                        ->required(),
                                ]),
                            ]),

                        Tab::make('Gate & Validity Schedule')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('designated_gate')
                                        ->label('Designated Gate Entry')
                                        ->options(collect(GateId::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])->toArray())
                                        ->required(),

                                    Toggle::make('single_entry')
                                        ->label('Single Entry Pass (auto-expires on exit)'),

                                    DatePicker::make('valid_from')
                                        ->label('Effective Date & Time')
                                        ->required(),

                                    DatePicker::make('valid_until')
                                        ->label('Expiration Date & Time')
                                        ->required(),
                                ]),
                            ]),

                        Tab::make('Audit & Revocation')
                            ->icon('heroicon-o-shield-exclamation')
                            ->schema([
                                Section::make('Security Overrides')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('revocation_reason')
                                            ->label('Reason for Revocation')
                                            ->placeholder('Administrative revocation or lease termination'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pass_id')
                    ->label('Pass Token')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('holder_name')
                    ->label('Holder / Visitor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('property')
                    ->label('Property')
                    ->searchable(),

                BadgeColumn::make('status')
                    ->label('Pass Status')
                    ->sortable()
                    ->colors([
                        'success' => 'ACTIVE',
                        'info' => 'CHECKED_IN',
                        'warning' => 'CHECKED_OUT',
                        'danger' => 'REVOKED',
                    ]),

                TextColumn::make('designated_gate')
                    ->label('Gate')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Expires At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(PassStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray()),

                SelectFilter::make('category')
                    ->label('Category')
                    ->options(collect(PassCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->toArray()),

                SelectFilter::make('designated_gate')
                    ->label('Gate')
                    ->options(collect(GateId::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])->toArray()),
            ])
            ->actions([
                Action::make('checkIn')
                    ->label('Check In')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('success'),

                Action::make('checkOut')
                    ->label('Check Out')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('info'),

                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Gate Pass Credential')
                    ->modalSubheading('This will immediately terminate access for this visitor.'),
            ])
            ->bulkActions([
                BulkAction::make('exportCsv')
                    ->label('Export Selected to CSV'),
                BulkAction::make('revokeSelected')
                    ->label('Revoke Selected')
                    ->color('danger'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                SectionEntry::make('Pass Credential Overview')
                    ->schema([
                        TextEntry::make('pass_id')->label('Security Pass ID')->copyable()->badge(),
                        TextEntry::make('holder_name')->label('Authorized Person'),
                        TextEntry::make('property')->label('Property Access'),
                        TextEntry::make('status')->label('Current State')->badge(),
                        TextEntry::make('designated_gate')->label('Assigned Gate'),
                        TextEntry::make('valid_until')->label('Validity Ends'),
                    ]),
            ]);
    }
}
