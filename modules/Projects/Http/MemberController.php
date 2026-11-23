<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Http;

use App\Audit\AuditLog;
use App\Authorization\Permissions;
use App\Authorization\RoleManager;
use App\Http\Controllers\Controller;
use App\Models\ProjectRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\ProjectMember;

class MemberController extends Controller
{
    public function store(Request $request, string $project, AuditLog $audit, Permissions $permissions, RoleManager $roles): RedirectResponse
    {
        $actor = $this->currentUser($request);
        $model = Project::query()->findOrFail($project);
        $request->validate(['user_id' => ['required', 'ulid'], 'project_role_id' => ['required', 'ulid']]);
        $user = User::query()->findOrFail($request->string('user_id')->toString());
        $role = ProjectRole::query()->findOrFail($request->string('project_role_id')->toString());
        $this->guard($actor, $model, $role, $permissions);

        if (ProjectMember::query()->where('project_id', $model->id)->where('user_id', $user->id)->where('project_role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['user_id' => __('projects:errors.member_exists')]);
        }
        DB::transaction(function () use ($model, $user, $role, $audit, $actor): void {
            $member = ProjectMember::query()->create(['project_id' => $model->id, 'user_id' => $user->id, 'project_role_id' => $role->id]);
            $audit->record('projects.member.added', $member, ['project' => $model->number, 'user' => $user->id, 'role' => $role->key], $actor);
        });
        $permissions->flush();
        // Project roles count for the user type (ADR-0003).
        $roles->refreshUserType($user);

        return to_route('projects.show', $project);
    }

    public function destroy(Request $request, string $project, string $member, AuditLog $audit, Permissions $permissions, RoleManager $roles): RedirectResponse
    {
        $actor = $this->currentUser($request);
        $model = Project::query()->findOrFail($project);
        $row = ProjectMember::query()->where('project_id', $model->id)->findOrFail($member);
        $this->guard($actor, $model, ProjectRole::query()->findOrFail($row->project_role_id), $permissions);
        DB::transaction(function () use ($row, $model, $audit, $actor): void {
            $audit->record('projects.member.removed', $row, ['project' => $model->number, 'user' => $row->user_id], $actor);
            $row->delete();
        });
        $permissions->flush();
        $roles->refreshUserType(User::query()->findOrFail($row->user_id));

        return to_route('projects.show', $project);
    }

    /** Nobody hands out or takes away more than they hold themselves in this project. */
    private function guard(User $actor, Project $project, ProjectRole $role, Permissions $permissions): void
    {
        abort_if(array_diff($role->permissionKeys(), $permissions->inProject($actor, $project->id)) !== [], 403);
    }
}
