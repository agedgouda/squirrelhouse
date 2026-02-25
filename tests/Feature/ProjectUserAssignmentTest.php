<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    setPermissionsTeamId(null);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'org-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'project-lead', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'team-member', 'guard_name' => 'web']);

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->projectType = ProjectType::create(['name' => 'General', 'document_schema' => []]);
    $this->client = Client::create([
        'organization_id' => $this->org->id,
        'company_name' => 'Test Client',
        'contact_name' => 'Jane Doe',
        'contact_phone' => '555-1234',
    ]);
    $this->project = Project::create([
        'name' => 'Test Project',
        'client_id' => $this->client->id,
        'project_type_id' => $this->projectType->id,
    ]);

    // Org admin
    $this->admin = User::factory()->create();
    setPermissionsTeamId($this->org->id);
    $this->admin->organizations()->syncWithoutDetaching([$this->org->id]);
    $this->admin->assignRole('org-admin');

    // Project lead (in org, not yet assigned to project)
    $this->projectLead = User::factory()->create();
    $this->projectLead->organizations()->syncWithoutDetaching([$this->org->id]);
    $this->projectLead->assignRole('project-lead');

    // Team member (in org, not yet assigned to project)
    $this->teamMember = User::factory()->create();
    $this->teamMember->organizations()->syncWithoutDetaching([$this->org->id]);
    $this->teamMember->assignRole('team-member');

    // Super admin
    setPermissionsTeamId(null);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super-admin');
    setPermissionsTeamId($this->org->id);
});

// --- ASSIGN USER TO PROJECT ---

it('allows an org-admin to assign a user to a project with a role', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.users.store', $this->project), [
            'user_id' => $this->projectLead->id,
            'role' => 'project-lead',
        ])
        ->assertRedirect();

    expect($this->project->users()->where('users.id', $this->projectLead->id)->exists())->toBeTrue();
    expect($this->project->users()->where('users.id', $this->projectLead->id)->first()->pivot->role)->toBe('project-lead');
});

it('allows a super-admin to assign a user to a project', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('projects.users.store', $this->project), [
            'user_id' => $this->teamMember->id,
            'role' => 'team-member',
        ])
        ->assertRedirect();

    expect($this->project->users()->where('users.id', $this->teamMember->id)->exists())->toBeTrue();
});

it('blocks a project-lead from assigning users to a project', function () {
    $this->project->users()->syncWithoutDetaching([
        $this->projectLead->id => ['role' => 'project-lead'],
    ]);

    $this->actingAs($this->projectLead)
        ->post(route('projects.users.store', $this->project), [
            'user_id' => $this->teamMember->id,
            'role' => 'team-member',
        ])
        ->assertNotFound();

    expect($this->project->users()->where('users.id', $this->teamMember->id)->exists())->toBeFalse();
});

it('blocks a team-member from assigning users to a project', function () {
    $this->project->users()->syncWithoutDetaching([
        $this->teamMember->id => ['role' => 'team-member'],
    ]);

    $otherUser = User::factory()->create();
    $otherUser->organizations()->syncWithoutDetaching([$this->org->id]);

    $this->actingAs($this->teamMember)
        ->post(route('projects.users.store', $this->project), [
            'user_id' => $otherUser->id,
            'role' => 'team-member',
        ])
        ->assertNotFound();

    expect($this->project->users()->where('users.id', $otherUser->id)->exists())->toBeFalse();
});

it('cannot assign a user from a different organization', function () {
    $otherOrg = Organization::create(['name' => 'Other Org']);
    $outsider = User::factory()->create();
    setPermissionsTeamId($otherOrg->id);
    $outsider->organizations()->syncWithoutDetaching([$otherOrg->id]);
    setPermissionsTeamId($this->org->id);

    $this->actingAs($this->admin)
        ->post(route('projects.users.store', $this->project), [
            'user_id' => $outsider->id,
            'role' => 'team-member',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('user_id');

    expect($this->project->users()->where('users.id', $outsider->id)->exists())->toBeFalse();
});

// --- REMOVE USER FROM PROJECT ---

it('allows an org-admin to remove a user from a project', function () {
    $this->project->users()->syncWithoutDetaching([
        $this->projectLead->id => ['role' => 'project-lead'],
    ]);

    $this->actingAs($this->admin)
        ->delete(route('projects.users.destroy', [$this->project, $this->projectLead]))
        ->assertRedirect();

    expect($this->project->users()->where('users.id', $this->projectLead->id)->exists())->toBeFalse();
});

// --- PROJECT VISIBILITY ---

it('allows a user assigned as project-lead to view the project', function () {
    $this->project->users()->syncWithoutDetaching([
        $this->projectLead->id => ['role' => 'project-lead'],
    ]);

    $this->actingAs($this->projectLead)
        ->get(route('projects.show', $this->project))
        ->assertOk();
});

it('allows a user assigned as team-member to view the project', function () {
    $this->project->users()->syncWithoutDetaching([
        $this->teamMember->id => ['role' => 'team-member'],
    ]);

    $this->actingAs($this->teamMember)
        ->get(route('projects.show', $this->project))
        ->assertOk();
});

it('blocks a user not assigned to the project from viewing it', function () {
    $this->actingAs($this->projectLead)
        ->get(route('projects.show', $this->project))
        ->assertNotFound();
});
