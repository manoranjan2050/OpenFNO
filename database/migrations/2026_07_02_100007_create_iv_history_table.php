<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Populated by the nightly collector once broker integration (Phase 5) is live.
        // Created in Phase 1 because IV rank/percentile needs history that cannot be backfilled.
        Schema::create('iv_history', function (Blueprint $table) {
            $table->id();
            $table->string('underlying', 30);
            $table->date('date');
            $table->decimal('atm_iv', 8, 4);
            $table->decimal('spot', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['underlying', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iv_history');
    }
};
