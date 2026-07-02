<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('underlying', 30);
            $table->string('strategy_name', 100)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            // set once when the trade is closed; open-trade P&L is always computed live
            $table->decimal('realized_pnl', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
