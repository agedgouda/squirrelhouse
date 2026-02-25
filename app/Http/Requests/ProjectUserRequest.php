<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return Gate::check('update', $project);
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $orgId = $project->organization_id;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereIn('id', function ($query) use ($orgId) {
                    $query->select('users.id')
                        ->from('users')
                        ->join('organization_user', 'users.id', '=', 'organization_user.user_id')
                        ->where('organization_user.organization_id', $orgId);
                }),
            ],
            'role' => ['required', 'string', Rule::in(['project-lead', 'team-member'])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'The selected user must belong to this organization.',
            'role.in' => 'The role must be either project-lead or team-member.',
        ];
    }
}
