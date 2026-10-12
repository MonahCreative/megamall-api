<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class District extends Model
{
    protected $fillable = ['region_id', 'name', 'delivery_fee', 'is_active'];

    protected function casts(): array
    {
        return ['delivery_fee' => 'integer', 'is_active' => 'boolean'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
