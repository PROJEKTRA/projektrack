<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The tree of work packages of one project: order, nesting and numbering. The
 * ids never change, so everything booked on a package keeps its link.
 */
final class WorkBreakdown
{
    /** @param array<string, mixed> $values */
    public function add(Project $project, array $values, ?string $parentId): WorkPackage
    {
        return DB::transaction(function () use ($project, $values, $parentId): WorkPackage {
            $this->lock($project);
            $last = WorkPackage::query()->where('project_id', $project->id)->where('parent_id', $parentId)->max('sort_order');
            $package = WorkPackage::query()->create([...$values, 'project_id' => $project->id, 'parent_id' => $parentId, 'wbs_code' => '', 'path' => '', 'sort_order' => (is_numeric($last) ? (int) $last : 0) + 1, 'status' => 'open']);
            $this->renumber($project);

            return $package->refresh();
        }, 3);
    }

    /**
     * Move a package under a parent (null: top level) at a place among its
     * siblings, counted from 0.
     */
    public function move(Project $project, WorkPackage $package, ?string $parentId, int $place): void
    {
        DB::transaction(function () use ($project, $package, $parentId, $place): void {
            $this->lock($project);
            for ($id = $parentId; $id !== null; $id = WorkPackage::query()->findOrFail($id)->parent_id) {
                if ($id === $package->id) {
                    throw ValidationException::withMessages(['parent_id' => __('projects:errors.wbs_cycle')]);
                }
            }

            $siblings = WorkPackage::query()->where('project_id', $project->id)->where('parent_id', $parentId)->whereKeyNot($package->id)->orderBy('sort_order')->orderBy('id')->get()->all();
            array_splice($siblings, max(0, min($place, count($siblings))), 0, [$package]);
            $package->parent_id = $parentId;
            foreach ($siblings as $i => $sibling) {
                $sibling->sort_order = $i + 1;
                $sibling->save();
            }
            $this->renumber($project);
        }, 3);
    }

    /** One change of the tree at a time per project. */
    private function lock(Project $project): void
    {
        Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
    }

    private function renumber(Project $project): void
    {
        /** @var array<string, list<WorkPackage>> $children */
        $children = [];
        foreach (WorkPackage::query()->where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get() as $package) {
            $children[$package->parent_id ?? ''][] = $package;
        }
        $this->number($children, '', '', '', 1);
    }

    /** @param array<string, list<WorkPackage>> $children */
    private function number(array $children, string $parent, string $code, string $path, int $depth): void
    {
        if ($depth > WorkPackage::MAX_DEPTH && isset($children[$parent])) {
            throw ValidationException::withMessages(['parent_id' => __('projects:errors.wbs_depth')]);
        }
        foreach ($children[$parent] ?? [] as $i => $package) {
            $package->wbs_code = $code.($i + 1);
            $package->path = $path.sprintf('%04d', $i + 1);
            $package->save();
            $this->number($children, $package->id, $package->wbs_code.'.', $package->path.'/', $depth + 1);
        }
    }
}
