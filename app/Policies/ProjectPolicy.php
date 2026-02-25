<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    use HandlesOrgPermissions;

    public function viewAny(User $user): bool
    {
        // Trait handles super-admin.
        // We return true and let Project::scopeVisibleTo handle the filtering.
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        $activeTeamId = getPermissionsTeamId();
        $orgId = $project->organization_id;

        // Project must belong to the active org context
        if ($orgId !== $activeTeamId) {
            return false;
        }

        // Org admins can view all projects in their org
        if ($this->isOrgAdmin($user, $project)) {
            return true;
        }

        // Direct project assignment (primary check)
        if ($project->users()->where('users.id', $user->id)->exists()) {
            return true;
        }

        // Client-level fallback (backwards compatibility)
        return (bool) $project->client?->users()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        // Checks if user is an admin for the current context (set by ProjectRequest)
        return $this->isOrgAdmin($user);
    }

    public function update(User $user, Project $project): bool
    {
        // Clean and simple: let the trait do the work
        return $this->isOrgAdmin($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->isOrgAdmin($user, $project);
    }
}
