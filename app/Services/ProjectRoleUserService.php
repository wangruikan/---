<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectRoleUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProjectRoleUserService
{
    public const ROLE_INSURANCE = 'insurance';
    public const ROLE_SALARY = 'salary';
    public const ROLE_DELIVERY = 'delivery';
    public const ROLE_ROLE_MANAGER = 'role_manager';

    public static function roleLabels(): array
    {
        return [
            self::ROLE_INSURANCE => '保险负责人',
            self::ROLE_SALARY => '薪资员',
            self::ROLE_DELIVERY => '交付员',
            self::ROLE_ROLE_MANAGER => '负责人设置人',
        ];
    }

    public static function validRoleTypes(): array
    {
        return array_keys(self::roleLabels());
    }

    public function isValidRoleType(?string $roleType): bool
    {
        return in_array($roleType, self::validRoleTypes(), true);
    }

    public function normalizeUserIds(array $userIds): array
    {
        return collect($userIds)
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function normalizeProjectIds(array $projectIds): array
    {
        return collect($projectIds)
            ->map(fn ($projectId) => (int) $projectId)
            ->filter(fn (int $projectId) => $projectId > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function getProjectRoleUsers(Project $project, string $roleType): Collection
    {
        if (!$this->isValidRoleType($roleType)) {
            return collect();
        }

        return ProjectRoleUser::with('user')
            ->where('account_set_id', $project->account_set_id)
            ->where('project_id', $project->id)
            ->where('role_type', $roleType)
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectRoleUser $assignment) => $assignment->user)
            ->filter()
            ->values();
    }

    public function getProjectRoleUserIds(int $accountSetId, int $projectId, string $roleType): array
    {
        if (!$this->isValidRoleType($roleType)) {
            return [];
        }

        return ProjectRoleUser::where('account_set_id', $accountSetId)
            ->where('project_id', $projectId)
            ->where('role_type', $roleType)
            ->pluck('user_id')
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values()
            ->all();
    }

    public function getProjectRoleUsersForProjects(int $accountSetId, array $projectIds, string $roleType): Collection
    {
        if (!$this->isValidRoleType($roleType)) {
            return collect();
        }

        $projectIds = collect($projectIds)
            ->map(fn ($projectId) => (int) $projectId)
            ->filter(fn (int $projectId) => $projectId > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($projectIds)) {
            return collect();
        }

        return User::query()
            ->join('project_role_users', 'project_role_users.user_id', '=', 'users.id')
            ->where('project_role_users.account_set_id', $accountSetId)
            ->whereIn('project_role_users.project_id', $projectIds)
            ->where('project_role_users.role_type', $roleType)
            ->where('users.is_active', true)
            ->select('users.*')
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }

    public function getManagedProjectIds(int $accountSetId, int $userId, string $roleType): array
    {
        if (!$this->isValidRoleType($roleType)) {
            return [];
        }

        return ProjectRoleUser::where('account_set_id', $accountSetId)
            ->where('user_id', $userId)
            ->where('role_type', $roleType)
            ->pluck('project_id')
            ->map(fn ($projectId) => (int) $projectId)
            ->unique()
            ->values()
            ->all();
    }

    public function shouldRestrictToManagedProjects(?User $user, string $roleType): bool
    {
        if (!$user || !$this->isValidRoleType($roleType)) {
            return false;
        }

        return !in_array($user->role, ['admin', 'super_admin'], true);
    }

    public function applyManagedProjectFilter(
        Builder $query,
        string $column,
        int $accountSetId,
        ?User $user,
        string $roleType,
        bool $strict = false
    ): array {
        if (!$this->shouldRestrictToManagedProjects($user, $roleType)) {
            return [];
        }

        $projectIds = $this->getManagedProjectIds($accountSetId, (int) $user->id, $roleType);
        if (empty($projectIds)) {
            if ($strict) {
                $query->whereRaw('1 = 0');
            }

            return [];
        }

        $query->whereIn($column, $projectIds);

        return $projectIds;
    }

    public function userCanAccessProject(?User $user, int $accountSetId, int $projectId, string $roleType): bool
    {
        if (!$this->shouldRestrictToManagedProjects($user, $roleType)) {
            return true;
        }

        $projectIds = $this->getManagedProjectIds($accountSetId, (int) $user->id, $roleType);

        return in_array($projectId, $projectIds, true);
    }

    public function userCanAccessAnyProject(?User $user, int $accountSetId, array $projectIds, string $roleType): bool
    {
        if (!$this->shouldRestrictToManagedProjects($user, $roleType)) {
            return true;
        }

        $projectIds = $this->normalizeProjectIds($projectIds);
        if (empty($projectIds)) {
            return false;
        }

        $managedProjectIds = $this->getManagedProjectIds($accountSetId, (int) $user->id, $roleType);
        if (empty($managedProjectIds)) {
            return false;
        }

        return !empty(array_intersect($managedProjectIds, $projectIds));
    }

    public function userCanAccessAllProjects(?User $user, int $accountSetId, array $projectIds, string $roleType): bool
    {
        if (!$this->shouldRestrictToManagedProjects($user, $roleType)) {
            return true;
        }

        $projectIds = $this->normalizeProjectIds($projectIds);
        if (empty($projectIds)) {
            return false;
        }

        $managedProjectIds = $this->getManagedProjectIds($accountSetId, (int) $user->id, $roleType);
        if (empty($managedProjectIds)) {
            return false;
        }

        return empty(array_diff($projectIds, $managedProjectIds));
    }

    public function syncProjectRoleUsers(Project $project, string $roleType, array $userIds): void
    {
        if (!$this->isValidRoleType($roleType)) {
            return;
        }

        $userIds = $this->normalizeUserIds($userIds);

        ProjectRoleUser::where('account_set_id', $project->account_set_id)
            ->where('project_id', $project->id)
            ->where('role_type', $roleType)
            ->whereNotIn('user_id', empty($userIds) ? [0] : $userIds)
            ->delete();

        foreach ($userIds as $userId) {
            ProjectRoleUser::updateOrCreate(
                [
                    'account_set_id' => $project->account_set_id,
                    'project_id' => $project->id,
                    'role_type' => $roleType,
                    'user_id' => $userId,
                ],
                []
            );
        }
    }
}
