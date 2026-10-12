<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Turns the dashboard's period filter into a start and end date. */
class DateRange
{
    public const OPTIONS = [
        'today' => 'Today',
        '7_days' => 'Last 7 days',
        '30_days' => 'Last 30 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'custom' => 'Choose dates',
    ];

    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $label,
    ) {}

    public static function fromFilters(?array $filters): self
    {
        $now = CarbonImmutable::now();
        $period = $filters['period'] ?? 'this_month';

        [$start, $end] = match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '30_days' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->startOfYear(), $now->endOfDay()],
            'custom' => [
                filled($filters['startDate'] ?? null) ? CarbonImmutable::parse($filters['startDate'])->startOfDay() : $now->startOfMonth(),
                filled($filters['endDate'] ?? null) ? CarbonImmutable::parse($filters['endDate'])->endOfDay() : $now->endOfDay(),
            ],
            default => [$now->startOfMonth(), $now->endOfDay()],
        };

        if ($end->lessThan($start)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $label = $period === 'custom'
            ? $start->format('d M Y').' - '.$end->format('d M Y')
            : self::OPTIONS[$period] ?? self::OPTIONS['this_month'];

        return new self($start, $end, $label);
    }

    /** Number of calendar days in the range (at least 1). */
    public function days(): int
    {
        return max(1, (int) $this->start->diffInDays($this->end->startOfDay()) + 1);
    }
}
