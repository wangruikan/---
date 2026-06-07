<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_seals') && !Schema::hasColumn('user_seals', 'account_set_id')) {
            Schema::table('user_seals', function (Blueprint $table) {
                $table->unsignedBigInteger('account_set_id')->nullable()->after('user_id')->comment('账套ID');
                $table->index(['user_id', 'account_set_id'], 'user_seals_user_account_set_index');
            });

            DB::statement("
                UPDATE user_seals us
                LEFT JOIN users u ON u.id = us.user_id
                SET us.account_set_id = COALESCE(
                    u.current_account_set_id,
                    u.account_set_id,
                    (
                        SELECT asu.account_set_id
                        FROM account_set_users asu
                        WHERE asu.user_id = us.user_id
                        ORDER BY asu.is_default DESC, asu.id ASC
                        LIMIT 1
                    )
                )
                WHERE us.account_set_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_seals') && Schema::hasColumn('user_seals', 'account_set_id')) {
            Schema::table('user_seals', function (Blueprint $table) {
                $table->dropIndex('user_seals_user_account_set_index');
                $table->dropColumn('account_set_id');
            });
        }
    }
};
