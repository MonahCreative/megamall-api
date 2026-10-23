<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable documents. They open as web pages sized for printing; "Print" or "Save as PDF"
 * in the browser's print window produces the paper copy or the PDF.
 */
class PrintController extends Controller
{
    /** Staff: A4 invoice / receipt for any order. */
    public function invoice(Order $order): View|RedirectResponse
    {
        if (! auth()->user()?->is_admin) {
            return redirect('/admin/login');
        }

        return $this->invoiceView($order);
    }

    /** Customer: the same invoice, opened from the private link on their order page. */
    public function customerInvoice(Request $request, string $number): View
    {
        $order = Order::where('order_number', $number)->where('channel', 'online')->firstOrFail();
        abort_unless(hash_equals($order->access_token, (string) $request->query('token')), 403, 'This link is not valid for this order.');

        return $this->invoiceView($order);
    }

    /** Staff: delivery label for one order. */
    public function packingSlip(Order $order): View|RedirectResponse
    {
        return $this->labels([$order->id]);
    }

    /** Staff: delivery labels for several orders (?orders=1,2,3), e.g. everything going out today. */
    public function packingSlips(Request $request): View|RedirectResponse
    {
        return $this->labels(array_filter(array_map('intval', explode(',', (string) $request->query('orders')))));
    }

    private function labels(array $ids): View|RedirectResponse
    {
        if (! auth()->user()?->is_admin) {
            return redirect('/admin/login');
        }

        $orders = Order::query()
            ->whereIn('id', $ids)
            ->where('channel', 'online')
            ->with(['items', 'region', 'district'])
            ->orderBy('id')
            ->get();
        abort_if($orders->isEmpty(), 404, 'No online orders selected.');

        return view('print.packing-slips', ['orders' => $orders, 'store' => Setting::allValues()]);
    }

    private function invoiceView(Order $order): View
    {
        return view('print.invoice', [
            'order' => $order->load(['items', 'payments', 'region', 'district', 'refunds', 'servedBy']),
            'store' => Setting::allValues(),
        ]);
    }
}
