<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_ride_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('ride_version');
            $table->enum('status', ['confirmed', 'tentative', 'declined'])->default('confirmed');
            $table->text('note')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['group_ride_id', 'user_id']);
            $table->index('group_ride_id');
            $table->index('user_id');
            $table->index('ride_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_attendees');
    }
};
