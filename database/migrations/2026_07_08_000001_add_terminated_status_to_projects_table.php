<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('active','inactive','completed','terminated') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("UPDATE projects SET status = 'inactive' WHERE status = 'terminated'");
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('active','inactive','completed') NOT NULL DEFAULT 'active'");
    }
};
