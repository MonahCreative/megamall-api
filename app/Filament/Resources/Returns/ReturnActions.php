<?php

namespace App\Filament\Resources\Returns;

use App\Enums\ReturnStatus;
use App\Filament\Support\Attempt;
use App\Models\ReturnRequest;
use App\Services\ReturnService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Approve -> Receive items -> Refund (or Reject). */
class ReturnActions
{
    public static function refundMethods(): array
    {
        return ['cash' => 'Cash', 'bank' => 'Bank transfer', ...config('payments.channels')];
    }

    /** @return array<Action> */
    public static function all(): array
    {
        return [static::approve(), static::receive(), static::refund(), static::reject()];
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('info')
            ->visible(fn (ReturnRequest $record) => $record->status === ReturnStatus::Requested)
            ->modalDescription('Tell the customer how to send the items back, or arrange collection.')
            ->schema([Textarea::make('note')->label('Note (optional)')->rows(2)])
            ->action(fn (ReturnRequest $record, array $data, Action $action) => Attempt::run($action, function () use ($record, $data) {
                app(ReturnService::class)->approve($record, $data['note'] ?? null);
                Notification::make()->success()->title('Return approved')->send();
            }));
    }

    public static function receive(): Action
    {
        return Action::make('receive')
            ->label('Items received')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('primary')
            ->visible(fn (ReturnRequest $record) => in_array($record->status, [ReturnStatus::Requested, ReturnStatus::Approved], true))
            ->schema([
                Toggle::make('restock')
                    ->label('Items are in good condition: put them back in stock')
                    ->default(true),
            ])
            ->action(fn (ReturnRequest $record, array $data, Action $action) => Attempt::run($action, function () use ($record, $data) {
                app(ReturnService::class)->receive($record, (bool) $data['restock']);
                Notification::make()->success()->title($data['restock'] ? 'Received and put back in stock' : 'Received (not restocked)')->send();
            }));
    }

    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (ReturnRequest $record) => $record->status === ReturnStatus::Received)
            ->modalHeading(fn (ReturnRequest $record) => 'Refund for '.$record->return_number)
            ->fillForm(fn (ReturnRequest $record) => ['amount' => $record->refund_amount, 'method' => 'mpesa'])
            ->schema(fn (ReturnRequest $record) => [
                TextInput::make('amount')
                    ->prefix('TZS')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText('Value of returned items: '.Money::tzs($record->refund_amount).'.'),
                Select::make('method')->label('Paid back with')->options(static::refundMethods())->required(),
                TextInput::make('reference')->label('Transaction ID'),
                TextInput::make('note'),
            ])
            ->action(fn (ReturnRequest $record, array $data, Action $action) => Attempt::run($action, function () use ($record, $data) {
                app(ReturnService::class)->refundReturn($record, (int) $data['amount'], $data['method'], $data['reference'] ?? null, $data['note'] ?? null);
                Notification::make()->success()->title('Refund recorded')->send();
            }));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (ReturnRequest $record) => in_array($record->status, [ReturnStatus::Requested, ReturnStatus::Approved], true))
            ->requiresConfirmation()
            ->schema([TextInput::make('note')->label('Reason (shared with the customer)')->required()])
            ->action(fn (ReturnRequest $record, array $data, Action $action) => Attempt::run($action, function () use ($record, $data) {
                app(ReturnService::class)->reject($record, $data['note']);
                Notification::make()->success()->title('Return rejected')->send();
            }));
    }
}
