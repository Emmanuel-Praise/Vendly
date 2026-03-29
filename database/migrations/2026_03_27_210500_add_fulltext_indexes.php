<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add robust high-speed Full-Text indexes to prevent massive table locks
        DB::statement('ALTER TABLE posts ADD FULLTEXT search_index(content_text, location)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE posts DROP INDEX search_index');
    }
};
