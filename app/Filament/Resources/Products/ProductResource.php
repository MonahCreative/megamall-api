<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | UnitEnum | null $navigationGroup = 'Catalogue';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product details')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Web address')
                            ->helperText('Used in the link, e.g. megamall.co.tz/product/this-part')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('brand')->maxLength(100),
                        TextInput::make('sku')
                            ->label('SKU / product code')
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Textarea::make('short_description')
                            ->label('Short description')
                            ->helperText('One or two sentences shown on the product card.')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        RichEditor::make('description')
                            ->label('Full description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Price and stock')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('price')
                            ->label('Selling price')
                            ->prefix('TZS')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('compare_at_price')
                            ->label('Old price (optional)')
                            ->helperText('Shown crossed out, with a discount badge.')
                            ->prefix('TZS')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('stock')
                            ->label('Stock')
                            ->helperText('If the product has sizes, set stock per size below.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Visible in shop')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('Featured on home page'),
                    ]),

                Section::make('Photos and video')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('images')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->grid(4)
                            ->addActionLabel('Add photo')
                            ->defaultItems(0)
                            ->schema([
                                FileUpload::make('path')
                                    ->label('Photo')
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('products')
                                    ->visibility('public')
                                    ->maxSize(4096)
                                    ->required(),
                            ])
                            ->helperText('The first photo is the main photo. Drag to reorder. Square photos (1:1) look best.'),
                        TextInput::make('video_url')
                            ->label('Product video link')
                            ->helperText('YouTube, TikTok or Instagram link showing the product working.')
                            ->url()
                            ->maxLength(255),
                    ]),

                Section::make('Sizes, colours and options')
                    ->description('Leave empty if the product has no options.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Repeater::make('variants')
                            ->relationship()
                            ->defaultItems(0)
                            ->addActionLabel('Add option')
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                Grid::make(4)->schema([
                                    TextInput::make('name')
                                        ->label('Option')
                                        ->placeholder('Size: XL')
                                        ->required(),
                                    TextInput::make('sku')->label('SKU'),
                                    TextInput::make('price')
                                        ->label('Price (if different)')
                                        ->prefix('TZS')
                                        ->numeric(),
                                    TextInput::make('stock')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->required(),
                                ]),
                                Toggle::make('is_active')->label('Available')->default(true),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['mainImage', 'category']))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('mainImage.path')
                    ->label('')
                    ->disk('public')
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (Product $record) => $record->brand),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => Money::tzs($state))
                    ->description(fn (Product $record) => $record->compare_at_price ? 'was '.Money::tzs($record->compare_at_price) : null)
                    ->sortable(),
                TextColumn::make('stock')
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state === 0 => 'danger',
                        $state < 5 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('sold_count')
                    ->label('Sold')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')->label('Visible'),
                ToggleColumn::make('is_featured')->label('Featured'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->preload(),
                TernaryFilter::make('is_active')->label('Visible in shop'),
                Filter::make('low_stock')
                    ->label('Low stock (under 5)')
                    ->query(fn (Builder $query) => $query->where('stock', '<', 5)),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'brand'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
