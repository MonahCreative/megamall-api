<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What each item cost us when it was sold, so reports can show profit even after cost prices change.
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_cost')->nullable()->after('unit_price');
        });

        // Orders made before this update: use the product's current cost price (best estimate available).
        DB::table('order_items')
            ->whereNull('unit_cost')
            ->whereNotNull('product_id')
            ->update(['unit_cost' => DB::raw('(select cost_price from products where products.id = order_items.product_id)')]);

        // End-of-day cash-up: what the system expected a cashier to hold vs what they counted.
        Schema::create('cash_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // the cashier
            $table->date('business_date');
            $table->json('expected');  // {"cash": 120000, "mpesa": 45000, ...}
            $table->json('counted');
            $table->integer('expected_total');
            $table->integer('counted_total');
            $table->integer('difference'); // counted - expected: below 0 = short, above 0 = over
            $table->text('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'business_date']);
        });

        // Every SMS the shop sends (or would send, while no SMS provider is connected).
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->string('to', 20);
            $table->text('body');
            $table->string('driver', 20);
            $table->string('status', 20); // logged (not really sent), sent, failed
            $table->string('provider_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'event']);
        });

        // Terms, Privacy, Return policy, About, Contact... written in the admin, shown on the shop.
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('cash_ups');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
    }
};
