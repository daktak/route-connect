<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->timestamps();

            $table->unique(['route_id', 'user_id']);
            $table->index('route_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_ratings');
    }
};
