<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('
            CREATE INDEX IF NOT EXISTS transcript_segments_body_gin
            ON transcript_segments
            USING gin (body gin_trgm_ops)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transcript_segments_body_gin');
    }
};
