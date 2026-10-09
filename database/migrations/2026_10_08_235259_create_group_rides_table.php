<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('ride_date');
            $table->decimal('meeting_point_lat', 10, 8)->nullable();
            $table->decimal('meeting_point_lng', 11, 8)->nullable();
            $table->string('meeting_point_name')->nullable();
            $table->integer('max_participants')->nullable();
            $table->enum('status', ['planned', 'confirmed', 'cancelled', 'completed'])->default('planned');
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->index('route_id');
            $table->index('organizer_id');
            $table->index('ride_date');
            $table->index('status');
            $table->index('version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_rides');
    }
};
