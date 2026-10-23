<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosReceiptController extends Controller
{
    public function __invoke(Order $order): View|RedirectResponse
    {
        if (! auth()->user()?->is_admin) {
            return redirect('/admin/login');
        }

        return view('pos.receipt', [
            'order' => $order->load(['items', 'payments', 'servedBy']),
            'store' => Setting::allValues(),
        ]);
    }
}
