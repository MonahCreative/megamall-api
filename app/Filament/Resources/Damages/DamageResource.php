<?php

namespace App\Filament\Resources\Damages;

use App\Filament\Resources\Damages\Pages\ManageDamages;
use App\Filament\Support\ProductFields;
use App\Models\Damage;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Broken, expired, lost or stolen stock. Recording one removes it from stock. */
class DamageResource extends Resource
{
    protected static ?string $model = Damage::class;

    protected static ?string $navigationLabel = 'Damages';

    protected static ?string $modelLabel = 'damaged item';

    protected static string | UnitEnum | null $navigationGroup = 'Products & Stock';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ProductFields::product()->columnSpanFull(),
                ProductFields::variant(),
                TextInput::make('quantity')->numeric()->minValue(1)->default(1)->required(),
                Select::make('reason')->options(Damage::REASONS)->required()->default('broken'),
                DatePicker::make('damage_date')->label('Date')->default(now())->maxDate(now())->required(),
                Textarea::make('notes')->placeholder('Fell from the shelf while unpacking')->rows(2)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'variant', 'user']))
            ->defaultSort('damage_date', 'desc')
            ->columns([
                TextColumn::make('damage_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('product.name')->label('Product')->searchable()->wrap()
                    ->description(fn (Damage $record) => $record->variant?->name),
                TextColumn::make('quantity')->label('Qty'),
                TextColumn::make('reason')->badge()->color('danger')
                    ->formatStateUsing(fn (string $state) => Damage::REASONS[$state] ?? $state),
                TextColumn::make('loss')
                    ->label('Loss at cost')
                    ->state(fn (Damage $record) => $record->product?->cost_price ? $record->product->cost_price * $record->quantity : null)
                    ->formatStateUsing(fn ($state) => Money::tzs($state))
                    ->placeholder('No cost price'),
                TextColumn::make('user.name')->label('Recorded by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('reason')->options(Damage::REASONS),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDamages::route('/')];
    }
}
