<?php

namespace App\Filament\Resources\ContentPages;

use App\Filament\Resources\ContentPages\Pages\CreateContentPage;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Filament\Resources\ContentPages\Pages\ListContentPages;
use App\Models\ContentPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Terms, Privacy, Return policy, About, Contact... shown on the shop and linked in its footer. */
class ContentPageResource extends Resource
{
    protected static ?string $model = ContentPage::class;

    protected static ?string $slug = 'shop-pages';

    protected static ?string $navigationLabel = 'Shop pages';

    protected static ?string $modelLabel = 'shop page';

    protected static string | UnitEnum | null $navigationGroup = 'Store';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function shopUrl(ContentPage $page): string
    {
        return rtrim(config('megamall.frontend_url'), '/').'/pages/'.$page->slug;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Web address')
                            ->prefix('/pages/')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->maxLength(120),
                        RichEditor::make('body')
                            ->label('Text')
                            ->helperText('These are replaced with your current shop details: {store_name} {phone} {whatsapp} {email} {return_window_days}')
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('is_published')->label('Show on the shop')->default(true),
                        Toggle::make('show_in_footer')->label('Link in the footer')->default(true),
                        TextInput::make('sort_order')->label('Footer order')->numeric()->default(0)->minValue(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->searchable()->weight('bold')->description(fn (ContentPage $record) => '/pages/'.$record->slug),
                ToggleColumn::make('is_published')->label('On the shop'),
                ToggleColumn::make('show_in_footer')->label('In footer'),
                TextColumn::make('updated_at')->label('Last changed')->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (ContentPage $record) => static::shopUrl($record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentPages::route('/'),
            'create' => CreateContentPage::route('/create'),
            'edit' => EditContentPage::route('/{record}/edit'),
        ];
    }
}
