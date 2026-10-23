<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\JsonResponse;

class RegionController extends Controller
{
    /** GET /api/regions — every region with its payment rule and delivery time, for the checkout form. */
    public function index(): JsonResponse
    {
        $regions = Region::active()
            ->with(['districts' => fn ($q) => $q->where('is_active', true)])
            ->orderByRaw("CASE WHEN payment_mode = 'cod' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(fn (Region $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'payment_mode' => $r->payment_mode->value,
                'payment_mode_label' => $r->payment_mode->label(),
                'delivery_days' => [$r->delivery_days_min, $r->delivery_days_max],
                'districts' => $r->districts->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                ]),
            ]);

        return response()->json(['data' => $regions]);
    }
}
