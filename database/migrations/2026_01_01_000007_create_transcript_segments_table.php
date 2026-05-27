<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('speaker_label', 100);
            $table->text('body');
            $table->unsignedInteger('start_ms');
            $table->unsignedInteger('end_ms');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['meeting_id', 'start_ms']);
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX transcript_segments_body_trgm_idx ON transcript_segments USING gin (body gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transcript_segments_body_trgm_idx');
        Schema::dropIfExists('transcript_segments');
    }
};
