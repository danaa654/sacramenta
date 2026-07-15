<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('contact_name')->after('type');
            $table->string('contact_mobile')->after('contact_name');
            $table->string('contact_address')->nullable()->after('contact_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_mobile', 'contact_address']);
        });
    }
};