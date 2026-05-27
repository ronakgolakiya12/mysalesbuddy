<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coaching_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('prompt_version_id')->nullable()->constrained('coaching_prompt_versions')->nullOnDelete();
            $table->string('mode');
            $table->text('deal_context')->nullable();
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->unsignedSmallInteger('talk_time_rep')->nullable();
            $table->unsignedSmallInteger('talk_time_prospect')->nullable();
            $table->jsonb('output_json')->nullable();
            $table->string('triggered_by');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('meeting_id');
        });

        DB::statement('CREATE INDEX coaching_analyses_meeting_created_idx ON coaching_analyses (meeting_id, created_at DESC)');
        DB::statement("ALTER TABLE coaching_analyses ADD CONSTRAINT coaching_analyses_mode_check CHECK (mode IN ('transcript_only','discovery_aware'))");
        DB::statement("ALTER TABLE coaching_analyses ADD CONSTRAINT coaching_analyses_triggered_by_check CHECK (triggered_by IN ('auto','manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_analyses');
    }
};
