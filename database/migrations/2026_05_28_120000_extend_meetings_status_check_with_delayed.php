<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE meetings DROP CONSTRAINT IF EXISTS meetings_status_check');
        DB::statement(
            'ALTER TABLE meetings ADD CONSTRAINT meetings_status_check '.
            "CHECK (status IN ('scheduled','bot_joining','recording','processing','ready','failed','cancelled','delayed'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE meetings DROP CONSTRAINT IF EXISTS meetings_status_check');
        DB::statement(
            'ALTER TABLE meetings ADD CONSTRAINT meetings_status_check '.
            "CHECK (status IN ('scheduled','bot_joining','recording','processing','ready','failed','cancelled'))"
        );
    }
};
