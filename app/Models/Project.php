<?php

namespace App\Models;

use App\Collections\ProjectCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'budget',
        'launch_date',
        'status',
        'project_type_id',
        'client_id',
        'document_id',
        'current_lifecycle_step_id',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'float',
            'launch_date' => 'date',
        ];
    }

    // Explicitly define the primary key type for UUIDs
    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Register the Custom Collection for Pipeline logic.
     */
    public function newCollection(array $models = []): ProjectCollection
    {
        return new ProjectCollection($models);
    }

    /**
     * Helper for single-model loading (used in show).
     */
    public function loadFullPipeline(): ?self
    {
        return $this->newCollection([$this])->withFullPipeline()->first();
    }

    public function getOrganizationIdAttribute()
    {
        $orgId = $this->client?->organization_id;

        if (! $orgId) {
            \Log::warning('[ModelDebug] Project could not reach Organization ID', [
                'project_id' => $this->id,
                'has_client' => (bool) $this->client,
            ]);
        }

        return $orgId;
    }

    /**
     * Get the users assigned to this project.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /**
     * Get the client that owns the project.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the documents associated with the project.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'project_id');
    }

    /**
     * Get the type that this project belongs to.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    /**
     * Get the current lifecycle step for this project.
     */
    public function currentLifecycleStep(): BelongsTo
    {
        return $this->belongsTo(LifecycleStep::class, 'current_lifecycle_step_id');
    }

    /**
     * Get the tasks associated with the project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Multi-tenant visibility scope.
     */
    public function scopeVisibleTo($query, User $user)
    {
        // 1. Super-Admin bypass
        // Note: We check roles without the team context to ensure global admins aren't filtered out
        if ($user->hasRole('super-admin')) {
            return $query;
        }

        $currentOrgId = getPermissionsTeamId();

        // 2. Fail-safe: If no org context is set, return no projects.
        if (! $currentOrgId) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('org-admin')) {
            return $query->whereHas('client', function ($q) use ($currentOrgId) {
                $q->where('organization_id', $currentOrgId);
            });
        }

        // 3. Members / Consultants — direct project assignment or client-level fallback
        return $query->where(function ($q) use ($user, $currentOrgId) {
            $q->whereHas('users', function ($sub) use ($user) {
                $sub->where('users.id', $user->id);
            })->orWhereHas('client', function ($c) use ($user, $currentOrgId) {
                $c->where('organization_id', $currentOrgId)
                    ->whereHas('users', function ($sub) use ($user) {
                        $sub->where('users.id', $user->id);
                    });
            });
        });
    }

    /**
     * Get only the documentation documents (non-tasks).
     */
    public function getDocumentationPipe()
    {
        $keys = $this->type->getDocumentationKeys();

        return $this->documents
            ->whereIn('type', $keys)
            ->sortBy('type')
            ->values();
    }

    /**
     * Get documents grouped for Kanban (tasks).
     */
    public function getKanbanPipe()
    {
        $keys = $this->type->getTaskKeys();

        return $this->documents
            ->whereIn('type', $keys)
            ->groupBy('type');
    }
}
