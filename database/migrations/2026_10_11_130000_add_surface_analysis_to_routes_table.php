<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->jsonb('surface_breakdown')->nullable();
            $table->decimal('gravel_pct', 5, 2)->nullable();
            $table->timestamp('surface_analyzed_at')->nullable();
            $table->string('surface_source')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn([
                'surface_breakdown',
                'gravel_pct',
                'surface_analyzed_at',
                'surface_source',
            ]);
        });
    }
};
