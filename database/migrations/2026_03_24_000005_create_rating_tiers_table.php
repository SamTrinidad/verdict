<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_config_id')->constrained('rating_configs')->cascadeOnDelete();
            $table->string('label');
            $table->string('color', 7)->comment('Hex color code, e.g. #FF0000');
            $table->tinyInteger('rank_order')->unsigned()->comment('Lower value = better rank (1 = best)');
            $table->timestamps();

            $table->index(['rating_config_id', 'rank_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_tiers');
    }
};
