<?php

namespace App\Filament\Resources\CashUps;

use App\Filament\Resources\CashUps\Pages\CreateCashUp;
use App\Filament\Resources\CashUps\Pages\ListCashUps;
use App\Filament\Resources\CashUps\Pages\ViewCashUp;
use App\Models\CashUp;
use App\Services\CashUpService;
use App\Support\Money;
use App\Support\PaymentChannels;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** End-of-day count for each cashier: expected money vs money actually there. */
class CashUpResource extends Resource
{
    protected static ?string $model = CashUp::class;

    protected static ?string $slug = 'cash-ups';

    protected static ?string $navigationLabel = 'Cash-up';

    protected static ?string $modelLabel = 'cash-up';

    protected static string | UnitEnum | null $navigationGroup = 'Orders & POS';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 4;

    public static function canEdit($record): bool
    {
        return false; // a cash-up is a record of what was counted; delete and redo it if it was wrong
    }

    /** Expected amounts for the cashier and date chosen in the form (worked out once per request). */
    public static function expectedFor(Get $get): array
    {
        static $cache = [];
        $userId = (int) $get('user_id');
        $date = $get('business_date');
        if (! $userId || blank($date)) {
            return [];
        }

        return $cache["{$userId}|{$date}"] ??= app(CashUpService::class)->expected($userId, CarbonImmutable::parse($date));
    }

    public static function differenceLabel(int $difference): string
    {
        return match (true) {
            $difference < 0 => 'Short '.Money::tzs(abs($difference)),
            $difference > 0 => 'Over '.Money::tzs($difference),
            default => 'Balanced',
        };
    }

    public static function form(Schema $schema): Schema
    {
        $countFields = collect(PaymentChannels::all())->map(fn (string $label, string $method) => TextInput::make("counted.{$method}")
            ->label($method === 'cash' ? 'Cash in the drawer' : "{$label} received")
            ->numeric()
            ->minValue(0)
            ->prefix('TZS')
            ->placeholder('0')
            ->live(onBlur: true)
            ->visible(fn (Get $get) => $method === 'cash' || isset(static::expectedFor($get)[$method]))
            ->helperText(function (Get $get) use ($method) {
                $expected = static::expectedFor($get)[$method] ?? 0;
                $counted = $get("counted.{$method}");

                return 'Expected '.Money::tzs($expected)
                    .(filled($counted) ? ' · '.static::differenceLabel((int) $counted - $expected) : '');
            }))->values()->all();

        return $schema
            ->components([
                Section::make('Who and when')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Cashier')
                            ->options(fn () => app(CashUpService::class)->cashiers())
                            ->default(fn () => auth()->id())
                            ->required()
                            ->live(),
                        DatePicker::make('business_date')
                            ->label('Day')
                            ->native(false)
                            ->displayFormat('D d M Y')
                            ->default(now()->toDateString())
                            ->maxDate(now())
                            ->required()
                            ->live(),
                    ]),
                Section::make('What the system expects')
                    ->description('Money from this cashier\'s counter sales that day, minus refunds they paid out.')
                    ->schema([
                        TextEntry::make('expected_summary')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Get $get) {
                                $expected = static::expectedFor($get);
                                if (! $get('user_id') || ! $get('business_date')) {
                                    return 'Choose the cashier and the day.';
                                }
                                $sales = app(CashUpService::class)->salesCount((int) $get('user_id'), CarbonImmutable::parse($get('business_date')));
                                if ($expected === []) {
                                    return "No counter sales by this cashier that day ({$sales} sales).";
                                }

                                $rows = collect($expected)->map(fn (int $amount, string $method) => '<tr><td style="padding:2px 16px 2px 0">'.e(PaymentChannels::label($method)).'</td><td style="text-align:right">'.e(Money::tzs($amount)).'</td></tr>')->implode('');

                                return new HtmlString("<p style=\"margin-bottom:6px\">{$sales} sale(s).</p><table>{$rows}<tr><td style=\"padding:6px 16px 0 0\"><strong>Total</strong></td><td style=\"text-align:right; padding-top:6px\"><strong>".e(Money::tzs(array_sum($expected))).'</strong></td></tr></table>');
                            }),
                    ]),
                Section::make('What the cashier counted')
                    ->description('Count the cash in the drawer and check each mobile money till. Leave a method empty if nothing came in.')
                    ->columns(2)
                    ->schema([
                        ...$countFields,
                        Textarea::make('note')
                            ->label('Note (explain any difference)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('user.name')->label('Cashier'),
                            TextEntry::make('business_date')->label('Day')->date('D d M Y'),
                            TextEntry::make('difference')
                                ->label('Result')
                                ->badge()
                                ->formatStateUsing(fn (int $state) => static::differenceLabel($state))
                                ->color(fn (int $state) => $state < 0 ? 'danger' : ($state > 0 ? 'warning' : 'success')),
                            TextEntry::make('closedBy.name')->label('Saved by')->placeholder('-'),
                        ]),
                        TextEntry::make('breakdown')
                            ->label('Breakdown')
                            ->html()
                            ->state(function (CashUp $record) {
                                $rows = collect($record->expected)->keys()->merge(array_keys($record->counted))->unique()
                                    ->map(function (string $method) use ($record) {
                                        $expected = (int) ($record->expected[$method] ?? 0);
                                        $counted = (int) ($record->counted[$method] ?? 0);
                                        $difference = $counted - $expected;
                                        $colour = $difference < 0 ? '#dc2626' : ($difference > 0 ? '#ca8a04' : 'inherit');

                                        return '<tr><td style="padding:4px 20px 4px 0">'.e(PaymentChannels::label($method)).'</td>'
                                            .'<td style="text-align:right; padding-right:20px">'.e(Money::tzs($expected)).'</td>'
                                            .'<td style="text-align:right; padding-right:20px">'.e(Money::tzs($counted)).'</td>'
                                            ."<td style=\"text-align:right; color:{$colour}\">".e(static::differenceLabel($difference)).'</td></tr>';
                                    })->implode('');

                                return new HtmlString('<table><thead><tr><th style="text-align:left; padding-right:20px">Method</th><th style="text-align:right; padding-right:20px">Expected</th><th style="text-align:right; padding-right:20px">Counted</th><th style="text-align:right">Difference</th></tr></thead><tbody>'
                                    .$rows
                                    .'<tr><td style="padding-top:8px"><strong>Total</strong></td><td style="text-align:right; padding:8px 20px 0 0"><strong>'.e(Money::tzs($record->expected_total)).'</strong></td><td style="text-align:right; padding:8px 20px 0 0"><strong>'.e(Money::tzs($record->counted_total)).'</strong></td><td style="text-align:right; padding-top:8px"><strong>'.e(static::differenceLabel($record->difference)).'</strong></td></tr>'
                                    .'</tbody></table>');
                            }),
                        TextEntry::make('note')->placeholder('No note'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'closedBy']))
            ->defaultSort('business_date', 'desc')
            ->recordUrl(fn (CashUp $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('business_date')->label('Day')->date('D d M Y')->sortable(),
                TextColumn::make('user.name')->label('Cashier')->searchable(),
                TextColumn::make('expected_total')->label('Expected')->formatStateUsing(fn ($state) => Money::tzs($state)),
                TextColumn::make('counted_total')->label('Counted')->formatStateUsing(fn ($state) => Money::tzs($state)),
                TextColumn::make('difference')
                    ->label('Result')
                    ->badge()
                    ->formatStateUsing(fn (int $state) => static::differenceLabel($state))
                    ->color(fn (int $state) => $state < 0 ? 'danger' : ($state > 0 ? 'warning' : 'success'))
                    ->sortable(),
                TextColumn::make('closedBy.name')->label('Saved by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user')->label('Cashier')->relationship('user', 'name'),
                Filter::make('short')->label('Only short days')->query(fn (Builder $query) => $query->where('difference', '<', 0)),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashUps::route('/'),
            'create' => CreateCashUp::route('/create'),
            'view' => ViewCashUp::route('/{record}'),
        ];
    }
}
