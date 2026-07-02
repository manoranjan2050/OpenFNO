<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategies', function (Blueprint $table) {
            $table->id();
            // null user_id = built-in template shipped with the app
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            // relative leg rules, e.g. [{"type":"CE","side":"SELL","strike_rule":"ATM+200","lots":1}, ...]
            $table->json('legs');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategies');
    }
};
