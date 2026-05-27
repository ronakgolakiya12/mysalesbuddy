<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notetaker_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 100);
            $table->string('avatar_path')->nullable();
            $table->text('intro_message')->nullable();
            $table->string('default_scope')->default('private');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE notetaker_configs ADD CONSTRAINT notetaker_configs_default_scope_check CHECK (default_scope IN ('private','team'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notetaker_configs');
    }
};
