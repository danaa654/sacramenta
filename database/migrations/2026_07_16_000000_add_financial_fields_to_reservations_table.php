<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // payment_status: unpaid, partial, paid, waived
            $table->string('payment_status')->default('unpaid')->after('offering_amount');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('payment_status');
            $table->string('receipt_number')->nullable()->after('amount_paid');
            $table->date('payment_date')->nullable()->after('receipt_number');
            $table->string('payment_note')->nullable()->after('payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'amount_paid',
                'receipt_number',
                'payment_date',
                'payment_note',
            ]);
        });
    }
};