<?php

namespace App\Filament\Resources\Regions;

use App\Enums\PaymentMode;
use App\Filament\Resources\Regions\Pages\EditRegion;
use App\Filament\Resources\Regions\Pages\ListRegions;
use App\Models\Region;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static ?string $navigationLabel = 'Regions and fees';

    protected static string | UnitEnum | null $navigationGroup = 'Delivery';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMapPin;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Region')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->disabled()->dehydrated(false),
                        Toggle::make('is_active')
                            ->label('We deliver here')
                            ->inline(false),
                        Radio::make('payment_mode')
                            ->label('How customers in this region pay')
                            ->options([
                                PaymentMode::CashOnDelivery->value => PaymentMode::CashOnDelivery->getLabel(),
                                PaymentMode::Prepaid->value => PaymentMode::Prepaid->getLabel(),
                            ])
                            ->descriptions([
                                PaymentMode::CashOnDelivery->value => 'Customer pays the rider after checking the item.',
                                PaymentMode::Prepaid->value => 'Customer must pay by mobile money before we dispatch.',
                            ])
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Delivery time')
                    ->description('Shown to customers at checkout. The delivery cost itself is paid by the customer to the rider or bus company.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('delivery_days_min')
                            ->label('Fastest delivery (days)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('delivery_days_max')
                            ->label('Slowest delivery (days)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
                Section::make('Districts')
                    ->description('Optional. When districts exist, customers must pick one at checkout, which helps riders plan routes.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Repeater::make('districts')
                            ->relationship()
                            ->defaultItems(0)
                            ->addActionLabel('Add district')
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')->required(),
                                    Toggle::make('is_active')->label('Deliver here')->default(true)->inline(false),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('districts'))
            ->defaultSort('name')
            ->paginated([50])
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('payment_mode')->label('Payment')->badge(),
                TextColumn::make('delivery_days_max')
                    ->label('Days')
                    ->formatStateUsing(fn (Region $record) => $record->delivery_days_min === $record->delivery_days_max
                        ? "{$record->delivery_days_max}"
                        : "{$record->delivery_days_min}-{$record->delivery_days_max}"),
                TextColumn::make('districts_count')->label('Districts'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                SelectFilter::make('payment_mode')->options([
                    PaymentMode::CashOnDelivery->value => PaymentMode::CashOnDelivery->getLabel(),
                    PaymentMode::Prepaid->value => PaymentMode::Prepaid->getLabel(),
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false; // all 31 regions already exist
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegions::route('/'),
            'edit' => EditRegion::route('/{record}/edit'),
        ];
    }
}
