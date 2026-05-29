<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('coaching_analyses', function (Blueprint $table): void {
            // 'openai' | 'gemini'. Nullable so historical rows created before
            // this migration don't need backfill.
            $table->string('provider_used', 20)->nullable()->after('triggered_by');
        });
    }

    public function down(): void
    {
        Schema::table('coaching_analyses', function (Blueprint $table): void {
            $table->dropColumn('provider_used');
        });
    }
};
