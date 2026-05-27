<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coaching_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coaching_analysis_id')->constrained()->cascadeOnDelete();
            $table->string('section_key', 50);
            $table->string('rating');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['coaching_analysis_id', 'section_key']);
        });

        DB::statement("ALTER TABLE coaching_ratings ADD CONSTRAINT coaching_ratings_rating_check CHECK (rating IN ('useful','not_useful'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_ratings');
    }
};
