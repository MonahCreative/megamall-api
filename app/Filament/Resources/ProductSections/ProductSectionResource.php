<?php

namespace App\Filament\Resources\ProductSections;

use App\Filament\Resources\ProductSections\Pages\ManageProductSections;
use App\Models\ProductSection;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

/** The rows of products on the home page, in the order you drag them. */
class ProductSectionResource extends Resource
{
    protected static ?string $model = ProductSection::class;

    protected static ?string $navigationLabel = 'Product sections';

    protected static ?string $modelLabel = 'home page section';

    protected static string | UnitEnum | null $navigationGroup = 'Promo';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->placeholder('Flash deals')->required()->maxLength(80),
                TextInput::make('subtitle')->placeholder('Prices drop every Friday')->maxLength(150),
                Select::make('source')
                    ->label('Which products')
                    ->options(ProductSection::SOURCES)
                    ->default('manual')
                    ->live()
                    ->required(),
                TextInput::make('product_limit')->label('How many to show')->numeric()->minValue(2)->maxValue(30)->default(10)->required(),
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload()
                    ->visible(fn (Get $get) => $get('source') === 'category')
                    ->required(fn (Get $get) => $get('source') === 'category'),
                Select::make('products')
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => $get('source') === 'manual')
                    ->required(fn (Get $get) => $get('source') === 'manual')
                    ->columnSpanFull(),
                Toggle::make('is_active')->label('Show on home page')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->weight('bold')->description(fn (ProductSection $record) => $record->subtitle),
                TextColumn::make('source')
                    ->label('Products')
                    ->formatStateUsing(fn (ProductSection $record) => match ($record->source) {
                        'manual' => $record->products()->count().' chosen',
                        'category' => 'From '.($record->category?->name ?? '?'),
                        default => ProductSection::SOURCES[$record->source] ?? $record->source,
                    }),
                TextColumn::make('product_limit')->label('Shows up to'),
                ToggleColumn::make('is_active')->label('Showing'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProductSections::route('/')];
    }
}
