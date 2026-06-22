<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyTypes = ['travel_application', '差旅申请'];

        if (Schema::hasTable('approval_flow_configs')) {
            DB::table('approval_flow_configs')
                ->whereIn('business_type', $legacyTypes)
                ->delete();
        }

        if (Schema::hasTable('approval_instances')) {
            $instanceIds = DB::table('approval_instances')
                ->whereIn('business_type', $legacyTypes)
                ->pluck('id')
                ->all();

            if (!empty($instanceIds)) {
                if (Schema::hasTable('approval_attachments')) {
                    DB::table('approval_attachments')->whereIn('instance_id', $instanceIds)->delete();
                }

                if (Schema::hasTable('approval_cc_users')) {
                    DB::table('approval_cc_users')->whereIn('instance_id', $instanceIds)->delete();
                }

                if (Schema::hasTable('approval_records')) {
                    DB::table('approval_records')->whereIn('instance_id', $instanceIds)->delete();
                }

                if (Schema::hasTable('pending_tasks')) {
                    DB::table('pending_tasks')
                        ->where(function ($query) use ($instanceIds) {
                            $query->where(function ($q) use ($instanceIds) {
                                $q->where('related_type', 'ApprovalInstance')
                                    ->whereIn('related_id', $instanceIds);
                            })->orWhereIn('task_type', ['travel_application', 'travel_request']);
                        })
                        ->delete();
                }

                DB::table('approval_instances')->whereIn('id', $instanceIds)->delete();
            }
        }

        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('module', ['travel', 'travel_application'])
                ->pluck('id')
                ->all();

            if (!empty($permissionIds)) {
                if (Schema::hasTable('role_permissions')) {
                    DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                if (Schema::hasTable('user_permissions')) {
                    DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        }

        Schema::dropIfExists('travel_application_attachments');
        Schema::dropIfExists('travel_applications');
    }

    public function down(): void
    {
        // Legacy travel application business has been intentionally removed.
    }
};
