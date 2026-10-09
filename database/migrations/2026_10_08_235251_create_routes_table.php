<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('gpx_data')->default('{}');
            $table->geometry('geometry', subtype: 'linestring', srid: 4326)->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->integer('elevation_gain_m')->nullable();
            $table->integer('estimated_time_min')->nullable();
            $table->enum('difficulty', ['easy', 'moderate', 'hard', 'expert'])->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('gpx_file_path')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_public');
            $table->index('difficulty');
            $table->index('distance_km');
            $table->index('elevation_gain_m');
            $table->index('created_at');
        });

        // Spatial index requires raw SQL
        DB::statement('CREATE INDEX idx_routes_geometry ON routes USING GIST (geometry)');
        DB::statement("CREATE INDEX idx_routes_search ON routes USING GIN (to_tsvector('english', coalesce(name, '') || ' ' || coalesce(description, '')))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_routes_geometry');
        DB::statement('DROP INDEX IF EXISTS idx_routes_search');
        Schema::dropIfExists('routes');
    }
};
