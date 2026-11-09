<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Filament\Resources\Orders\OrderResource;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\LipaNamba;
use App\Support\Money;
use App\Support\PaymentChannels;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Sits on every admin page and checks for new orders every 20 seconds.
 * A new pay-on-delivery order, or an upcountry order that just got paid, shows a pop-up and plays a chime.
 */
class NewOrderAlert extends Component
{
    public string $since = '';

    /** @var array<int, int> orders already announced, so none is shown twice */
    public array $announced = [];

    /** @var array<int, int> Lipa Namba payments already announced */
    public array $announcedPayments = [];

    public function mount(): void
    {
        $this->since = now()->toIso8601String();
    }

    public function check(): void
    {
        if (! auth()->user()?->is_admin || ! Setting::getValue('staff_alert_sound', true)) {
            return;
        }

        $since = CarbonImmutable::parse($this->since)->subSeconds(2); // overlap a little: timestamps are whole seconds
        $this->since = now()->toIso8601String();

        $orders = Order::query()
            ->where('channel', 'online')
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereNotIn('id', $this->announced)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $cod) => $cod->where('payment_mode', PaymentMode::CashOnDelivery)->where('created_at', '>=', $since))
                ->orWhere(fn (Builder $paid) => $paid->where('payment_mode', PaymentMode::Prepaid)->where('paid_at', '>=', $since)))
            ->latest('id')
            ->get(['id', 'order_number', 'customer_name', 'total', 'payment_mode']);

        $payments = Payment::query()
            ->where('gateway', LipaNamba::GATEWAY)
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '>=', $since)
            ->whereNotIn('id', $this->announcedPayments)
            ->with('order:id,order_number,customer_name')
            ->latest('id')
            ->get();

        if ($payments->isNotEmpty()) {
            $this->announcedPayments = array_slice([...$this->announcedPayments, ...$payments->pluck('id')->all()], -100);

            foreach ($payments->take(3) as $payment) {
                Notification::make()
                    ->title('Payment to check · '.$payment->order->order_number)
                    ->body($payment->order->customer_name.' · '.Money::tzs($payment->amount).' · '.PaymentChannels::label($payment->channel).' ID '.$payment->external_id)
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->warning()
                    ->persistent()
                    ->actions([
                        Action::make('open')->label('Check payment')->button()->url(OrderResource::getUrl('view', ['record' => $payment->order])),
                    ])
                    ->send();
            }
        }

        if ($orders->isEmpty()) {
            if ($payments->isNotEmpty()) {
                $this->dispatch('mm-new-order');
            }

            return;
        }

        $this->announced = array_slice([...$this->announced, ...$orders->pluck('id')->all()], -100);

        foreach ($orders->take(3) as $order) {
            $cod = $order->payment_mode === PaymentMode::CashOnDelivery;
            Notification::make()
                ->title(($cod ? 'New order ' : 'Paid order ').$order->order_number)
                ->body($order->customer_name.' · '.Money::tzs($order->total).($cod ? ' · pay on delivery' : ' · paid, ready to send'))
                ->icon(Heroicon::OutlinedShoppingBag)
                ->success()
                ->persistent()
                ->actions([
                    Action::make('open')->label('Open order')->button()->url(OrderResource::getUrl('view', ['record' => $order])),
                ])
                ->send();
        }

        if ($orders->count() > 3) {
            Notification::make()->title('And '.($orders->count() - 3).' more new orders')->info()->send();
        }

        $this->dispatch('mm-new-order');
    }

    public function render(): View
    {
        return view('livewire.new-order-alert');
    }
}
