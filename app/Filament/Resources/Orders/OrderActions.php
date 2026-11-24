<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Filament\Support\Attempt;
use App\Models\Order;
use App\Services\OrderWorkflow;
use App\Services\Payments\PaymentService;
use App\Support\Money;
use App\Support\PaymentChannels;
use App\Support\Phone;
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
            static::confirmLipaPayment(),
            static::rejectLipaPayment(),
            static::confirm(),
            static::pack(),
            static::ship(),
            static::deliver(),
            static::recordPayment(),
            static::whatsapp(),
            static::printInvoice(),
            static::printPackingSlip(),
            static::refund(),
            static::cancel(),
        ];
    }

    /** A4 invoice (or receipt once paid), to print or save as PDF. */
    public static function printInvoice(): Action
    {
        return Action::make('printInvoice')
            ->label('Invoice')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->url(fn (Order $record) => route('print.invoice', $record))
            ->openUrlInNewTab();
    }

    /** Delivery label to stick on the parcel: customer, phone, address, amount to collect, items. */
    public static function printPackingSlip(): Action
    {
        return Action::make('printPackingSlip')
            ->label('Packing slip')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (Order $record) => $record->status !== OrderStatus::Cancelled)
            ->url(fn (Order $record) => route('print.packing-slip', $record))
            ->openUrlInNewTab();
    }

    /**
     * Give money back: for a paid order that was cancelled, or a delivered order the customer returned
     * (put the returned items back with Stock > Add stock).
     */
    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Refund customer')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('danger')
            ->visible(fn (Order $record) => app(OrderWorkflow::class)->canRefund($record))
            ->fillForm(fn (Order $record) => [
                'amount' => $record->total - $record->refundedAmount(),
                'method' => 'mpesa',
                'note' => $record->status === OrderStatus::Cancelled ? 'Order cancelled' : 'Items returned',
            ])
            ->schema([
                TextInput::make('amount')->prefix('TZS')->numeric()->minValue(1)->required(),
                Select::make('method')->label('Paid back with')->options(PaymentChannels::refundMethods())->required(),
                TextInput::make('reference')->label('Transaction ID'),
                TextInput::make('note')->label('Reason')->required()->maxLength(200),
            ])
            ->action(function (Order $record, array $data, Action $action) {
                Attempt::run($action, function () use ($record, $data) {
                    app(OrderWorkflow::class)->refund($record, (int) $data['amount'], $data['method'], $data['reference'] ?? null, $data['note']);
                    Notification::make()->success()->title('Refund recorded')->send();
                });
            });
    }

    /** The customer paid to the Lipa Namba: staff found the money in their mobile money messages. */
    public static function confirmLipaPayment(): Action
    {
        return Action::make('confirmLipaPayment')
            ->label('Confirm payment')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Order $record) => $record->paymentToCheck() !== null)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalHeading(fn (Order $record) => 'Did you receive '.Money::tzs($record->paymentToCheck()?->amount ?? $record->total).'?')
            ->modalDescription(fn (Order $record) => static::paymentToCheckText($record).' Only confirm if you can see this exact transaction and amount in your mobile money messages.')
            ->modalSubmitActionLabel('Yes, money received')
            ->action(function (Order $record) {
                static::run(function () use ($record) {
                    $payment = $record->paymentToCheck();
                    throw_unless($payment, ValidationException::withMessages(['payment' => 'This payment was already checked.']));
                    app(PaymentService::class)->confirmLipaNamba($payment, auth()->id());
                }, 'Payment confirmed. Call the customer to confirm their order.');
            });
    }

    /** Staff could not find the money: staff tell the customer, who can send the right transaction ID. */
    public static function rejectLipaPayment(): Action
    {
        return Action::make('rejectLipaPayment')
            ->label('Payment not found')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (Order $record) => $record->paymentToCheck() !== null)
            ->modalHeading('Payment not found')
            ->modalDescription(fn (Order $record) => static::paymentToCheckText($record).' Call or WhatsApp the customer: they can send the correct transaction ID on the order page. The order is cancelled if they do not pay in time.')
            ->schema([
                Select::make('reason')
                    ->label('What is wrong?')
                    ->options([
                        'no payment with this transaction ID' => 'No payment with this transaction ID',
                        'the amount paid is less than the order total' => 'Amount is less than the order total',
                        'the payment was sent to a different number' => 'Paid to a different number',
                    ])
                    ->required(),
            ])
            ->modalSubmitActionLabel('Tell the customer')
            ->action(function (Order $record, array $data) {
                static::run(function () use ($record, $data) {
                    $payment = $record->paymentToCheck();
                    throw_unless($payment, ValidationException::withMessages(['payment' => 'This payment was already checked.']));
                    app(PaymentService::class)->rejectLipaNamba($payment, $data['reason'], auth()->id());
                }, 'Customer told that the payment was not found');
            });
    }

    private static function paymentToCheckText(Order $record): string
    {
        $payment = $record->paymentToCheck();
        if (! $payment) {
            return '';
        }

        return PaymentChannels::label($payment->channel).' transaction '.$payment->external_id
            .' from '.($payment->phone ? Phone::display($payment->phone) : 'unknown number')
            .', for '.Money::tzs($payment->amount).'.';
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
            // While a Lipa Namba payment waits for checking, use "Confirm payment" instead.
            ->visible(fn (Order $record) => app(OrderWorkflow::class)->canRecordPayment($record) && $record->paymentToCheck() === null)
            ->modalHeading(fn (Order $record) => 'Record payment of '.Money::tzs($record->total))
            ->modalDescription('Use this once you can see the money on the business phone (Lipa Namba), or when it was paid in cash. Then call the customer to confirm.')
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
                ? 'This order is PAID. Cancelling puts the items back in stock. Then use "Refund customer" to record the money you send back.'
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
