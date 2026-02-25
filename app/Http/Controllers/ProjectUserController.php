<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectUserRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProjectUserController extends Controller
{
    public function store(ProjectUserRequest $request, Project $project): RedirectResponse
    {
        setPermissionsTeamId($project->organization_id);

        Gate::authorize('update', $project);

        $validated = $request->validated();

        $project->users()->syncWithoutDetaching([
            $validated['user_id'] => ['role' => $validated['role']],
        ]);

        return back()->with('success', 'User assigned to project successfully.');
    }

    public function destroy(Project $project, User $user): RedirectResponse
    {
        setPermissionsTeamId($project->organization_id);

        Gate::authorize('update', $project);

        $project->users()->detach($user->id);

        return back()->with('success', 'User removed from project successfully.');
    }
}
