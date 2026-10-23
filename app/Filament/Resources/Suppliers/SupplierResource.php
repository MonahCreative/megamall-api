<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Supplier;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string | UnitEnum | null $navigationGroup = 'Products & Stock';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(static::fields());
    }

    /** Also used for "add supplier" inside the purchase form. */
    public static function fields(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(150),
            TextInput::make('phone')->tel()->maxLength(20),
            TextInput::make('email')->email(),
            TextInput::make('address')->placeholder('Kariakoo, Dar es Salaam'),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('purchases')->withSum('purchases', 'total'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')->description(fn (Supplier $record) => $record->address),
                TextColumn::make('phone')->searchable()->copyable(),
                TextColumn::make('purchases_count')->label('Purchases'),
                TextColumn::make('purchases_sum_total')->label('Bought in total')->formatStateUsing(fn ($state) => Money::tzs($state)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Supplier $record) => $record->purchases_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSuppliers::route('/')];
    }
}
