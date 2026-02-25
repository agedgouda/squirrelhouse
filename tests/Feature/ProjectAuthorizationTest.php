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
    $this->client->users()->attach($this->admin->id);

    // Project lead (assigned to client)
    $this->projectLead = User::factory()->create();
    $this->projectLead->organizations()->syncWithoutDetaching([$this->org->id]);
    $this->projectLead->assignRole('project-lead');
    $this->client->users()->attach($this->projectLead->id);

    // Team member (assigned to client)
    $this->teamMember = User::factory()->create();
    $this->teamMember->organizations()->syncWithoutDetaching([$this->org->id]);
    $this->teamMember->assignRole('team-member');
    $this->client->users()->attach($this->teamMember->id);

    // Super admin
    setPermissionsTeamId(null);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super-admin');
    setPermissionsTeamId($this->org->id);
});

// --- CREATE ---

it('allows an org-admin to create a project', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), [
            'name' => 'New Project',
            'client_id' => $this->client->id,
            'project_type_id' => $this->projectType->id,
        ])
        ->assertRedirect();

    expect(Project::where('name', 'New Project')->exists())->toBeTrue();
});

it('blocks a project-lead from creating a project', function () {
    $this->actingAs($this->projectLead)
        ->post(route('projects.store'), [
            'name' => 'Sneaky Project',
            'client_id' => $this->client->id,
            'project_type_id' => $this->projectType->id,
        ])
        ->assertNotFound();

    expect(Project::where('name', 'Sneaky Project')->exists())->toBeFalse();
});

it('blocks a team-member from creating a project', function () {
    $this->actingAs($this->teamMember)
        ->post(route('projects.store'), [
            'name' => 'Sneaky Project',
            'client_id' => $this->client->id,
            'project_type_id' => $this->projectType->id,
        ])
        ->assertNotFound();

    expect(Project::where('name', 'Sneaky Project')->exists())->toBeFalse();
});

it('allows a super-admin to create a project', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('projects.store'), [
            'name' => 'Super Admin Project',
            'client_id' => $this->client->id,
            'project_type_id' => $this->projectType->id,
        ])
        ->assertRedirect();

    expect(Project::where('name', 'Super Admin Project')->exists())->toBeTrue();
});

// --- UPDATE ---

it('allows an org-admin to update a project', function () {
    $this->actingAs($this->admin)
        ->put(route('projects.update', $this->project), [
            'name' => 'Updated Name',
            'client_id' => $this->client->id,
            'project_type_id' => $this->projectType->id,
        ])
        ->assertRedirect();

    expect($this->project->fresh()->name)->toBe('Updated Name');
});

it('blocks a project-lead from updating a project', function () {
    $this->actingAs($this->projectLead)
        ->put(route('projects.update', $this->project), [
            'name' => 'Hacked Name',
            'client_id' => $this->client->id,
            'project_type_id' => $this->projectType->id,
        ])
        ->assertNotFound();

    expect($this->project->fresh()->name)->toBe('Test Project');
});

it('blocks a team-member from updating a project', function () {
    $this->actingAs($this->teamMember)
        ->put(route('projects.update', $this->project), [
            'name' => 'Hacked Name',
            'client_id' => $this->client->id,
            'project_type_id' => $this->projectType->id,
        ])
        ->assertNotFound();

    expect($this->project->fresh()->name)->toBe('Test Project');
});

// --- DELETE ---

it('allows an org-admin to delete a project', function () {
    $this->actingAs($this->admin)
        ->delete(route('projects.destroy', $this->project))
        ->assertRedirect();

    expect(Project::find($this->project->id))->toBeNull();
});

it('blocks a project-lead from deleting a project', function () {
    $this->actingAs($this->projectLead)
        ->delete(route('projects.destroy', $this->project))
        ->assertNotFound();

    expect(Project::find($this->project->id))->not->toBeNull();
});

it('blocks a team-member from deleting a project', function () {
    $this->actingAs($this->teamMember)
        ->delete(route('projects.destroy', $this->project))
        ->assertNotFound();

    expect(Project::find($this->project->id))->not->toBeNull();
});

it('allows a super-admin to delete a project', function () {
    $this->actingAs($this->superAdmin)
        ->delete(route('projects.destroy', $this->project))
        ->assertRedirect();

    expect(Project::find($this->project->id))->toBeNull();
});

// --- VIEW ---

it('allows a project-lead assigned to the client to view the project', function () {
    $this->actingAs($this->projectLead)
        ->get(route('projects.show', $this->project))
        ->assertOk();
});

it('allows a team-member assigned to the client to view the project', function () {
    $this->actingAs($this->teamMember)
        ->get(route('projects.show', $this->project))
        ->assertOk();
});

it('blocks a project-lead not assigned to the client from viewing the project', function () {
    $unassignedProjectLead = User::factory()->create();
    setPermissionsTeamId($this->org->id);
    $unassignedProjectLead->organizations()->syncWithoutDetaching([$this->org->id]);
    $unassignedProjectLead->assignRole('project-lead');

    $this->actingAs($unassignedProjectLead)
        ->get(route('projects.show', $this->project))
        ->assertNotFound();
});
