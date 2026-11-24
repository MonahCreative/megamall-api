<?php

use App\Http\Controllers\PrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Printable invoice and delivery labels. Staff only.
Route::get('/print/orders/{order}/invoice', [PrintController::class, 'invoice'])->name('print.invoice');
Route::get('/print/orders/{order}/packing-slip', [PrintController::class, 'packingSlip'])->name('print.packing-slip');
Route::get('/print/packing-slips', [PrintController::class, 'packingSlips'])->name('print.packing-slips');

// Customer's own invoice, from the private link on their order page.
Route::get('/invoice/{number}', [PrintController::class, 'customerInvoice'])->name('invoice.customer');
