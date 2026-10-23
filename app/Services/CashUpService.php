<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CashUp;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Support\PaymentChannels;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * End-of-day cash-up for the shop counter.
 *
 * Expected = money from that cashier's POS sales that day, per payment method,
 * minus refunds that cashier paid out that day. The cashier enters what they actually have
 * (cash in the drawer, M-Pesa on the till phone...) and the difference shows any shortage.
 */
class CashUpService
{
    /** @return array<string, int> method => amount, only methods with money */
    public function expected(int $userId, CarbonInterface $date): array
    {
        [$start, $end] = $this->day($date);

        $received = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->whereHas('order', fn (Builder $query) => $query
                ->where('channel', 'pos')
                ->where('served_by', $userId)
                ->where('status', '!=', OrderStatus::Cancelled))
            ->get(['channel', 'amount'])
            ->groupBy('channel')
            ->map(fn ($payments) => (int) $payments->sum('amount'));

        $paidOut = Refund::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['method', 'amount'])
            ->groupBy('method')
            ->map(fn ($refunds) => (int) $refunds->sum('amount'));

        $expected = [];
        foreach ($this->orderedMethods([...$received->keys(), ...$paidOut->keys()]) as $method) {
            $amount = ($received[$method] ?? 0) - ($paidOut[$method] ?? 0);
            if ($amount !== 0) {
                $expected[$method] = $amount;
            }
        }

        return $expected;
    }

    /** Number of counter sales the cashier made that day. */
    public function salesCount(int $userId, CarbonInterface $date): int
    {
        [$start, $end] = $this->day($date);

        return Order::query()
            ->where('channel', 'pos')
            ->where('served_by', $userId)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('paid_at', [$start, $end])
            ->count();
    }

    /** Staff who can be cashed up: anyone with an admin login. */
    public function cashiers(): array
    {
        return User::query()->where('is_admin', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function alreadyClosed(int $userId, CarbonInterface $date): bool
    {
        return CashUp::where('user_id', $userId)->whereDate('business_date', CarbonImmutable::instance($date)->toDateString())->exists();
    }

    /**
     * Save the count. Methods left empty count as 0.
     *
     * @param  array<string, int|string|null>  $counted  method => amount the cashier has
     */
    public function close(int $userId, CarbonInterface $date, array $counted, ?string $note = null): CashUp
    {
        $date = CarbonImmutable::instance($date)->startOfDay();

        if ($date->isFuture()) {
            throw ValidationException::withMessages(['business_date' => 'You cannot cash up a day that has not happened yet.']);
        }
        if ($this->alreadyClosed($userId, $date)) {
            throw ValidationException::withMessages(['business_date' => 'This cashier is already cashed up for that day. Delete the old cash-up first to redo it.']);
        }

        $expected = $this->expected($userId, $date);
        $counted = collect($counted)
            ->map(fn ($amount) => (int) preg_replace('/[^0-9-]/', '', (string) $amount))
            ->filter(fn (int $amount) => $amount !== 0)
            ->all();

        $methods = $this->orderedMethods([...array_keys($expected), ...array_keys($counted)]);
        $expected = array_replace(array_fill_keys($methods, 0), $expected);
        $counted = array_replace(array_fill_keys($methods, 0), $counted);

        return CashUp::create([
            'user_id' => $userId,
            'business_date' => $date->toDateString(),
            'expected' => $expected,
            'counted' => $counted,
            'expected_total' => array_sum($expected),
            'counted_total' => array_sum($counted),
            'difference' => array_sum($counted) - array_sum($expected),
            'note' => $note,
            'closed_by' => auth()->id(),
        ]);
    }

    /** @return array<int, string> methods in the usual order: cash first, then mobile money, card, bank, others */
    private function orderedMethods(array $methods): array
    {
        $known = array_keys(PaymentChannels::all());
        $methods = array_unique(array_filter(array_map('strval', $methods)));
        usort($methods, fn (string $a, string $b) => (array_search($a, $known, true) === false ? 99 : array_search($a, $known, true))
            <=> (array_search($b, $known, true) === false ? 99 : array_search($b, $known, true)));

        return $methods;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function day(CarbonInterface $date): array
    {
        $date = CarbonImmutable::instance($date);

        return [$date->startOfDay(), $date->endOfDay()];
    }
}
