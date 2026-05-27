<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('oauth_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->default('[]');
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });

        DB::statement("ALTER TABLE oauth_connections ADD CONSTRAINT oauth_connections_provider_check CHECK (provider IN ('google','microsoft'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_connections');
    }
};
