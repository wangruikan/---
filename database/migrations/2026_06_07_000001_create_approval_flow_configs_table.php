<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approval_flow_configs')) {
            Schema::create('approval_flow_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_set_id')->constrained('account_sets')->onDelete('cascade');
                $table->string('business_type', 100);
                $table->text('enabled_levels');
                $table->timestamps();

                $table->unique(['account_set_id', 'business_type'], 'approval_flow_configs_unique');
            });
        }

        if (Schema::hasTable('permissions')) {
            $permissions = [
                ['module' => 'approval_flow_configs', 'action' => 'view', 'name' => '审批流程配置-查看', 'sort_order' => 706],
                ['module' => 'approval_flow_configs', 'action' => 'update', 'name' => '审批流程配置-编辑', 'sort_order' => 707],
            ];

            foreach ($permissions as $permission) {
                DB::table('permissions')->updateOrInsert(
                    [
                        'module' => $permission['module'],
                        'action' => $permission['action'],
                    ],
                    [
                        'name' => $permission['name'],
                        'sort_order' => $permission['sort_order'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('module', 'approval_flow_configs')->delete();
        }

        Schema::dropIfExists('approval_flow_configs');
    }
};
