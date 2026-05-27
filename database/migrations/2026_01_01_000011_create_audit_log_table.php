<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('entity_type', 100);
            $table->uuid('entity_id')->nullable();
            $table->jsonb('metadata_json')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'event_type']);
            $table->index(['entity_type', 'entity_id']);
        });

        DB::statement('CREATE INDEX audit_log_created_idx ON audit_log (created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
