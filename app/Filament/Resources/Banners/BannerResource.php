<?php

namespace App\Filament\Resources\Banners;

use App\Filament\Resources\Banners\Pages\ManageBanners;
use App\Models\Banner;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $modelLabel = 'home page slide';

    protected static ?string $navigationLabel = 'Home page slider';

    protected static string | UnitEnum | null $navigationGroup = 'Catalogue';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('product_id')
                    ->label('Product to promote')
                    ->helperText('Fills in the title, price and link for you. Leave empty for a general slide.')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                        if (! $state || ! ($product = Product::find($state))) {
                            return;
                        }
                        if (blank($get('title'))) {
                            $set('title', $product->name);
                        }
                        if (blank($get('subtitle'))) {
                            $set('subtitle', $product->short_description);
                        }
                        $set('link', '/product/'.$product->slug);
                    })
                    ->columnSpanFull(),
                TextInput::make('title')->required()->maxLength(120),
                TextInput::make('badge')
                    ->placeholder('Video demo, Best seller, New arrival')
                    ->maxLength(30),
                TextInput::make('subtitle')->maxLength(200)->columnSpanFull(),
                TextInput::make('button_text')->default('Buy now')->maxLength(30),
                TextInput::make('link')
                    ->helperText('Where the button goes, e.g. /product/... or /category/fashion')
                    ->maxLength(255),
                FileUpload::make('image')
                    ->label('Slide image (optional)')
                    ->helperText('If empty, the product\'s main photo is used.')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('banners')
                    ->visibility('public')
                    ->maxSize(4096)
                    ->columnSpanFull(),
                TextInput::make('sort_order')->label('Position')->numeric()->default(0),
                Toggle::make('is_active')->label('Show on home page')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->disk('public'),
                TextColumn::make('title')->wrap()->description(fn (Banner $record) => $record->badge),
                TextColumn::make('product.name')->label('Product')->placeholder('None'),
                ToggleColumn::make('is_active')->label('Showing'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBanners::route('/'),
        ];
    }
}
