<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Notify me when back in stock": one row per customer phone per product (or size).
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id', 'phone']);
            $table->index(['product_id', 'notified_at']);
        });

        // Reorder point per product. Empty = use the shop-wide level from Store settings.
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reorder_level')->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('reorder_level'));
        Schema::dropIfExists('stock_alerts');
    }
};
