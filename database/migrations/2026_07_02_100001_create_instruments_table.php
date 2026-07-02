<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('exchange', 10)->default('NFO');
            $table->unsignedBigInteger('broker_token')->nullable();
            $table->string('tradingsymbol', 60);
            $table->string('underlying', 30);
            $table->enum('instrument_type', ['FUT', 'CE', 'PE', 'EQ']);
            $table->date('expiry_date')->nullable();
            $table->decimal('strike', 12, 2)->nullable();
            $table->unsignedInteger('lot_size');
            $table->decimal('tick_size', 8, 2)->default(0.05);
            $table->timestamps();

            $table->unique(['exchange', 'tradingsymbol']);
            $table->index(['underlying', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
