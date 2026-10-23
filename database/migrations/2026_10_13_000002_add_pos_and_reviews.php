<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // In-shop (POS) sales are orders too, with no delivery address.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel', 20)->default('online')->after('access_token'); // online | pos
            $table->unsignedInteger('discount')->default(0)->after('delivery_fee');
            $table->foreignId('served_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable()->after('paid_at'); // starts the return window
            $table->index('channel');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->foreignId('region_id')->nullable()->change();
            $table->string('area')->nullable()->change();
            $table->string('address')->nullable()->change();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('phone', 20)->nullable();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('reply')->nullable(); // shop's public reply
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->unique(['product_id', 'order_id']); // one review per product per order
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('served_by');
            $table->dropIndex(['channel']);
            $table->dropColumn(['channel', 'discount', 'delivered_at']);
        });
    }
};
