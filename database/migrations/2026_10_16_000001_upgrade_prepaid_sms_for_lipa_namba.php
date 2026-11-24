<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Used to reword a customer SMS. Customer SMS were removed on 2026-11-24, so this now does nothing.
 * The file stays so databases that already ran it keep a matching migration history.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
