<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tradingsymbol', 60)->nullable();
            $table->enum('instrument_type', ['FUT', 'CE', 'PE']);
            $table->date('expiry_date');
            $table->decimal('strike', 12, 2)->nullable();
            $table->enum('side', ['BUY', 'SELL']);
            $table->unsignedInteger('lots');
            // snapshot at entry — NSE lot-size revisions must never rewrite historical P&L
            $table->unsignedInteger('lot_size');
            $table->decimal('entry_price', 12, 2);
            $table->dateTime('entry_at');
            $table->decimal('exit_price', 12, 2)->nullable();
            $table->dateTime('exit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_legs');
    }
};
