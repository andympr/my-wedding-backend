<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('lastname');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();

            $table->boolean('enable_companion')->default(false);
            $table->enum('confirm', ['pending','yes','no'])->default('pending');

            $table->string('token')->unique();
            $table->text('notes')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('declined_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
