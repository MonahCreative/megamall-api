<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // 'cod' = pay on delivery, 'prepaid' = pay before dispatch.
            // This column IS the business rule: Dar es Salaam = cod, every other region = prepaid.
            $table->string('payment_mode', 20)->default('prepaid');
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->unsignedTinyInteger('delivery_days_min')->default(1);
            $table->unsignedTinyInteger('delivery_days_max')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('delivery_fee')->nullable(); // null = use region fee
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regions');
    }
};
