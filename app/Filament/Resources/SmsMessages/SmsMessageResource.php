<?php

namespace App\Filament\Resources\SmsMessages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SmsMessages\Pages\ListSmsMessages;
use App\Models\SmsMessage;
use App\Support\Phone;
use App\Support\SmsTemplates;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Every SMS sent to customers (or, before an SMS account is connected, every SMS that would have been sent). */
class SmsMessageResource extends Resource
{
    protected static ?string $model = SmsMessage::class;

    protected static ?string $slug = 'sms-outbox';

    protected static ?string $navigationLabel = 'SMS outbox';

    protected static ?string $modelLabel = 'SMS';

    protected static ?string $pluralModelLabel = 'SMS outbox';

    protected static string | UnitEnum | null $navigationGroup = 'Store';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?int $navigationSort = 3;

    public const STATUSES = [
        'logged' => 'Saved only (no SMS account yet)',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'pending' => 'Sending',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('to')->label('To')->formatStateUsing(fn (string $state) => Phone::display($state))->searchable(),
                TextColumn::make('event')->label('Message')->formatStateUsing(fn (string $state) => SmsTemplates::EVENTS[$state] ?? $state)
                    ->description(fn (SmsMessage $record) => $record->body)
                    ->wrap(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->url(fn (SmsMessage $record) => $record->order_id ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (SmsMessage $record) => $record->error),
            ])
            ->filters([
                SelectFilter::make('event')->label('Message')->options(SmsTemplates::EVENTS),
                SelectFilter::make('status')->options(self::STATUSES),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSmsMessages::route('/')];
    }
}
