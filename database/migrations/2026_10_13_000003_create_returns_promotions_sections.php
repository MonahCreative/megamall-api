<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer asking to send items back.
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 30);      // damaged, wrong_item, not_as_described, changed_mind, other
            $table->text('details')->nullable();
            $table->string('status', 20)->default('requested'); // requested, approved, rejected, received, refunded
            $table->text('admin_note')->nullable();
            $table->boolean('restocked')->default(false);
            $table->unsignedInteger('refund_amount')->default(0); // value of returned items
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        // Money given back to a customer: for a return, or for a paid order that was cancelled.
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method', 30);           // cash, mpesa, mixx, airtel, halopesa, bank
            $table->string('reference')->nullable(); // transaction ID
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Sales and discounts: "20% off Fashion this weekend", "TZS 5,000 off these 3 products".
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('discount_type', 10); // percent | fixed
            $table->unsignedInteger('discount_value');
            $table->string('applies_to', 20)->default('products'); // all | categories | products
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->string('badge')->nullable(); // e.g. "Flash sale"
            $table->timestamps();
        });

        Schema::create('product_promotion', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'product_id']);
        });

        Schema::create('category_promotion', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'category_id']);
        });

        // Rows of products on the home page: "Flash deals", "New arrivals", "Kitchen favourites"...
        Schema::create('product_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('source', 20)->default('manual'); // manual, featured, newest, best_selling, discounted, category
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('product_limit')->default(10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_product_section', function (Blueprint $table) {
            $table->foreignId('product_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['product_section_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_product_section');
        Schema::dropIfExists('product_sections');
        Schema::dropIfExists('category_promotion');
        Schema::dropIfExists('product_promotion');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_requests');
    }
};
