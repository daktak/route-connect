<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('oauth_provider')->nullable()->after('theme');
            $table->string('oauth_id')->nullable()->after('oauth_provider');
            $table->string('avatar_url')->nullable()->after('oauth_id');
            $table->unique(['oauth_provider', 'oauth_id']);
        });

        // password becomes nullable for OAuth-only accounts
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['oauth_provider', 'oauth_id']);
            $table->dropColumn(['oauth_provider', 'oauth_id', 'avatar_url']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
