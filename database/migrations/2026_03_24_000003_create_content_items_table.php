<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_set_id')->constrained('content_sets')->cascadeOnDelete();
            $table->string('display_value');
            $table->json('meta')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['content_set_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
