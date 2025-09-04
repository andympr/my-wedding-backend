<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->unique('guest_id');
        });
    }

    public function down(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->dropUnique(['guest_id']);
        });
    }
};
