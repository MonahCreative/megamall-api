<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One cashier's end-of-day count: what the system expected vs what was in the drawer and on the phones. */
class CashUp extends Model
{
    protected $fillable = [
        'user_id', 'business_date', 'expected', 'counted', 'expected_total', 'counted_total', 'difference', 'note', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'expected' => 'array',
            'counted' => 'array',
            'expected_total' => 'integer',
            'counted_total' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
