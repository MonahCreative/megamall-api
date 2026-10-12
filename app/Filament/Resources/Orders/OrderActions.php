<?php

namespace App\Filament\Resources\Orders;

use App\Enums\PaymentMode;
use App\Models\Order;
use App\Services\OrderWorkflow;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * The buttons staff use to move an order along. Each one calls OrderWorkflow,
 * which holds the rules, and is only shown when that step is allowed.
 */
class OrderActions
{
    /** @return array<Action> */
    public static function all(): array
    {
        return [
            static::confirm(),
            static::pack(),
            static::ship(),
            static::deliver(),
            static::recordPayment(),
            static::whatsapp(),
            static::cancel(),
        ];
    }

    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('Confirm')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('info')
            ->visible(fn (Order $record) => app(OrderWorkflow::class)->canConfirm($record))
            ->requiresConfirmation()
            ->modalHeading('Confirm this order?')
            ->modalDescription(fn (Order $record) => "Call {$record->customer_name} first to make sure the order is real.")
            ->action(fn (Order $record) => static::run(fn () => app(OrderWorkflow::class)->confirm($record), 'Order confirmed'));
    }

    public static function pack(): Action
    {
        return Action::make('pack')
            ->label('Start packing')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->visible(fn (Order $record) => app(OrderWorkflow::class)->canPack($record))
            ->action(fn (Order $record) => static::run(fn () => app(OrderWorkflow::class)->pack($record), 'Marked as being packed'));
    }

    public static function ship(): Action
    {
        return Action::make('ship')
            ->label('Send out')
            ->icon(Heroicon::OutlinedTruck)
            ->color('primary')
            ->visible(fn (Order $record) => app(OrderWorkflow::class)->canShip($record)
                && ($record->payment_mode === PaymentMode::CashOnDelivery || $record->isPaid()))
            ->modalHeading('Send this order out')
            ->schema([
                Textarea::make('note')
                    ->label('Delivery details for the customer')
                    ->placeholder('Rider: Juma 0712 345 678  /  Bus: Shabiby, parcel no. 4471, arrives Mwanza Thursday')
                    ->rows(2),
            ])
            ->action(fn (Order $record, array $data) => static::run(fn () => app(OrderWorkflow::class)->ship($record, $data['note'] ?? null), 'Order is on the way'));
    }

    public static function deliver(): Action
    {
        return Action::make('deliver')
            ->label('Delivered')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Order $record) => app(OrderWorkflow::class)->canDeliver($record))
            ->modalHeading('Mark as delivered')
            ->schema(fn (Order $record) => $record->isPaid() ? [] : [
                Toggle::make('collected')
                    ->label('Rider collected '.Money::tzs($record->total))
                    ->default(true),
                Select::make('channel')
                    ->label('Paid with')
                    ->options(['cash' => 'Cash', ...config('payments.channels')])
                    ->default('cash'),
                TextInput::make('reference')
                    ->label('Mobile money transaction ID (if not cash)'),
            ])
            ->action(fn (Order $record, array $data) => static::run(
                fn () => app(OrderWorkflow::class)->deliver(
                    $record,
                    (bool) ($data['collected'] ?? false),
                    $data['channel'] ?? 'cash',
                    $data['reference'] ?? null,
                ),
                'Order delivered',
            ));
    }

    public static function recordPayment(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Order $record) => app(OrderWorkflow::class)->canRecordPayment($record))
            ->modalHeading(fn (Order $record) => 'Record payment of '.Money::tzs($record->total))
            ->modalDescription('Use this when the customer paid outside the automatic checkout, for example to your Lipa number or in cash.')
            ->schema([
                Select::make('channel')
                    ->label('Paid with')
                    ->options(['cash' => 'Cash', 'bank' => 'Bank transfer', ...config('payments.channels')])
                    ->required(),
                TextInput::make('reference')->label('Transaction ID'),
            ])
            ->action(fn (Order $record, array $data) => static::run(
                fn () => app(OrderWorkflow::class)->recordPayment($record, $data['channel'], $data['reference'] ?? null),
                'Payment recorded',
            ));
    }

    public static function whatsapp(): Action
    {
        return Action::make('whatsapp')
            ->label('WhatsApp customer')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->url(fn (Order $record) => 'https://wa.me/'.$record->phone.'?text='.rawurlencode(
                "Habari {$record->customer_name}, this is Mega Mall Tanzania about your order {$record->order_number}."
            ))
            ->openUrlInNewTab();
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel order')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Order $record) => app(OrderWorkflow::class)->canCancel($record))
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record) => $record->isPaid()
                ? 'This order is PAID. Cancelling puts the items back in stock, but you must refund the customer yourself.'
                : 'The items go back into stock.')
            ->schema([
                TextInput::make('reason')->label('Reason')->required()->placeholder('Customer changed their mind'),
            ])
            ->action(fn (Order $record, array $data) => static::run(fn () => app(OrderWorkflow::class)->cancel($record, $data['reason']), 'Order cancelled'));
    }

    private static function run(callable $step, string $successMessage): void
    {
        try {
            $step();
            Notification::make()->success()->title($successMessage)->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Not allowed')->body(collect($exception->errors())->flatten()->first())->send();
        }
    }
}
