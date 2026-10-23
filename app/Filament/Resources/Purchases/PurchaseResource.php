<?php

namespace App\Filament\Resources\Purchases;

use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Pages\ViewPurchase;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Support\ProductFields;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Goods bought from suppliers. Saving a purchase adds the items to stock. */
class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string | UnitEnum | null $navigationGroup = 'Products & Stock';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(fn () => Supplier::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->createOptionForm(SupplierResource::fields())
                            ->createOptionUsing(fn (array $data) => Supplier::create($data)->id),
                        DatePicker::make('purchase_date')->label('Date received')->default(now())->maxDate(now())->required(),
                        TextInput::make('invoice_number')->label('Supplier invoice / receipt no.'),
                        Textarea::make('notes')->rows(2)->columnSpanFull(),
                    ]),
                Section::make('Items received')
                    ->description('Saving adds these quantities to stock and updates each product\'s cost price.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add another product')
                            ->live()
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 4])->schema([
                                    ProductFields::product(afterChosen: function (Set $set, Get $get, ?Product $product) {
                                        if ($product?->cost_price) {
                                            $set('unit_cost', $product->cost_price);
                                        }
                                    })->columnSpan(['md' => 2]),
                                    TextInput::make('quantity')->numeric()->minValue(1)->default(1)->required()->live(onBlur: true),
                                    TextInput::make('unit_cost')->label('Cost each')->prefix('TZS')->numeric()->minValue(0)->required()->live(onBlur: true),
                                    ProductFields::variant()->columnSpan(['md' => 2]),
                                ]),
                            ]),
                        TextEntry::make('total_preview')
                            ->label('Total')
                            ->state(fn (Get $get) => Money::tzs(collect($get('items') ?? [])->sum(fn ($item) => (int) ($item['quantity'] ?? 0) * (int) ($item['unit_cost'] ?? 0))))
                            ->weight('bold'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('reference')->copyable()->weight('bold'),
                        TextEntry::make('purchase_date')->label('Date received')->date('d M Y'),
                        TextEntry::make('supplier.name')->label('Supplier')->placeholder('-'),
                        TextEntry::make('invoice_number')->label('Invoice no.')->placeholder('-'),
                        TextEntry::make('total')->formatStateUsing(fn ($state) => Money::tzs($state))->weight('bold'),
                        TextEntry::make('user.name')->label('Recorded by')->placeholder('-'),
                        TextEntry::make('notes')->placeholder('-')->columnSpan(2),
                    ]),
                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('product.name')->label('Product')->helperText(fn ($record) => $record->variant?->name),
                                    TextEntry::make('quantity')->label('Qty'),
                                    TextEntry::make('unit_cost')->label('Cost each')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                    TextEntry::make('line_total')->label('Total')->formatStateUsing(fn ($state) => Money::tzs($state)),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('supplier')->withCount('items'))
            ->defaultSort('purchase_date', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable()->weight('bold'),
                TextColumn::make('purchase_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('supplier.name')->label('Supplier')->placeholder('-')->searchable(),
                TextColumn::make('items_count')->label('Products'),
                TextColumn::make('total')->formatStateUsing(fn ($state) => Money::tzs($state))->sortable(),
            ])
            ->filters([
                SelectFilter::make('supplier')->relationship('supplier', 'name')->preload(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'view' => ViewPurchase::route('/{record}'),
        ];
    }
}
