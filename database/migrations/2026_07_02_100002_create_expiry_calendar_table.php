<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expiry_calendar', function (Blueprint $table) {
            $table->id();
            $table->string('underlying', 30);
            $table->date('expiry_date');
            $table->enum('expiry_type', ['weekly', 'monthly']);
            $table->timestamps();

            $table->unique(['underlying', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_calendar');
    }
};
