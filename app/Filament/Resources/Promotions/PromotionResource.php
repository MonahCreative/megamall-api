<?php

namespace App\Filament\Resources\Promotions;

use App\Filament\Resources\Promotions\Pages\ManagePromotions;
use App\Models\Promotion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Time-limited discounts that change shop prices automatically while they run. */
class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string | UnitEnum | null $navigationGroup = 'Promo';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->placeholder('Weekend fashion sale')->required()->maxLength(120),
                TextInput::make('badge')->label('Badge on products')->placeholder('Flash sale')->maxLength(30),
                Radio::make('discount_type')
                    ->label('Discount')
                    ->options(['percent' => 'Percentage off', 'fixed' => 'Amount off (TZS)'])
                    ->default('percent')
                    ->inline()
                    ->live()
                    ->required(),
                TextInput::make('discount_value')
                    ->label(fn (Get $get) => $get('discount_type') === 'fixed' ? 'Amount off each item' : 'Percent off')
                    ->prefix(fn (Get $get) => $get('discount_type') === 'fixed' ? 'TZS' : null)
                    ->suffix(fn (Get $get) => $get('discount_type') === 'fixed' ? null : '%')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (Get $get) => $get('discount_type') === 'fixed' ? null : 90)
                    ->required(),
                Radio::make('applies_to')
                    ->label('Applies to')
                    ->options(['products' => 'Chosen products', 'categories' => 'Whole categories', 'all' => 'Everything in the shop'])
                    ->default('products')
                    ->live()
                    ->required()
                    ->columnSpanFull(),
                Select::make('products')
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => $get('applies_to') === 'products')
                    ->required(fn (Get $get) => $get('applies_to') === 'products')
                    ->columnSpanFull(),
                Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload()
                    ->helperText('Sub-categories are included automatically.')
                    ->visible(fn (Get $get) => $get('applies_to') === 'categories')
                    ->required(fn (Get $get) => $get('applies_to') === 'categories')
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at')->label('Starts')->default(now())->seconds(false)->required(),
                DateTimePicker::make('ends_at')->label('Ends')->default(now()->addDays(3)->endOfDay())->seconds(false)->after('starts_at')->required(),
                Toggle::make('is_active')->label('Switched on')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')->description(fn (Promotion $record) => $record->badge),
                TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(fn (Promotion $record) => $record->discount_type === 'percent'
                        ? "{$record->discount_value}% off"
                        : 'TZS '.number_format($record->discount_value).' off'),
                TextColumn::make('applies_to')
                    ->label('On')
                    ->formatStateUsing(fn (Promotion $record) => match ($record->applies_to) {
                        'all' => 'Everything',
                        'categories' => $record->categories()->pluck('name')->join(', '),
                        default => $record->products()->count().' product(s)',
                    })
                    ->wrap(),
                TextColumn::make('starts_at')->label('Runs')
                    ->formatStateUsing(fn (Promotion $record) => $record->starts_at->format('d M H:i').' → '.$record->ends_at->format('d M H:i')),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Promotion $record) => match (true) {
                        ! $record->is_active => 'Off',
                        $record->starts_at->isFuture() => 'Scheduled',
                        $record->ends_at->isPast() => 'Ended',
                        default => 'Running',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Running' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                ToggleColumn::make('is_active')->label('On'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePromotions::route('/')];
    }
}
