<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('companions', function (Blueprint $table) {
            $table->id();
            $table->uuid('guest_id');
            $table->string('name');
            $table->string('lastname')->nullable();
            $table->timestamps();

            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('companions');
    }
};