<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('recall_bot_id')->nullable()->unique();
            $table->string('external_meeting_url');
            $table->string('title')->nullable();
            $table->string('provider');
            $table->string('status');
            $table->string('scope')->default('private');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'scheduled_at']);
            $table->index(['user_id', 'deleted_at']);
            $table->index('recall_bot_id');
        });

        DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_provider_check CHECK (provider IN ('google_meet','teams','zoom'))");
        DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_status_check CHECK (status IN ('scheduled','bot_joining','recording','processing','ready','failed','cancelled'))");
        DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_scope_check CHECK (scope IN ('private','team'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
