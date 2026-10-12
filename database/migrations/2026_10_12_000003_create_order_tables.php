<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('access_token', 64); // lets the buyer's browser pay / poll without logging in
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('customer_name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('area');
            $table->string('address');
            $table->text('notes')->nullable();

            $table->string('payment_mode', 20);              // copied from region at order time
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('status', 30)->default('pending');

            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('delivery_fee');
            $table->unsignedInteger('total');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();     // prepaid orders not paid in time get cancelled
            $table->string('tracking_note')->nullable();     // e.g. "Sent with Kilimanjaro Express, parcel no. 12345"
            $table->timestamps();

            $table->index(['status', 'payment_status']);
            $table->index('phone');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots, so old orders stay correct after you edit a product
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('line_total');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 30);          // fake, azampay, selcom, clickpesa, cod
            $table->string('channel', 30)->nullable(); // mpesa, mixx, airtel, halopesa, cash
            $table->string('phone', 20)->nullable();
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending'); // pending, paid, failed
            $table->string('reference')->nullable()->unique(); // our reference sent to the gateway
            $table->string('external_id')->nullable();          // gateway / mobile network transaction id
            $table->string('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
