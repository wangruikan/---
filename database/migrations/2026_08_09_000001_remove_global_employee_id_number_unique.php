<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        foreach ($this->getSingleColumnUniqueIndexNames() as $indexName) {
            Schema::table('employees', function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        $hasDuplicateIdNumbers = DB::table('employees')
            ->whereNotNull('id_number')
            ->select('id_number')
            ->groupBy('id_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateIdNumbers) {
            throw new \RuntimeException('employees.id_number 存在重复值，无法恢复全局唯一索引');
        }

        $hasIndex = !empty($this->getSingleColumnUniqueIndexNames());

        if (!$hasIndex) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unique('id_number', 'employees_id_number_unique');
            });
        }
    }

    private function getSingleColumnUniqueIndexNames(): array
    {
        return collect(DB::select(
            "SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'employees'
               AND NON_UNIQUE = 0
               AND INDEX_NAME <> 'PRIMARY'
             GROUP BY INDEX_NAME
             HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = 'id_number'"
        ))
            ->pluck('INDEX_NAME')
            ->filter()
            ->values()
            ->all();
    }
};
