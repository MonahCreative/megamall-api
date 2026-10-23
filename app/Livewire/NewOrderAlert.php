<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Money;
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

        if ($orders->isEmpty()) {
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
