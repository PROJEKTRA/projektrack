<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Domain;

use App\Support\Lists;
use Modules\Crm\Contracts\CompanyDirectory;

final class Directory implements CompanyDirectory
{
    public function choices(): array
    {
        return array_values(Company::query()->where('status', 'active')->orderBy('name')->get()
            ->map(fn (Company $c): array => ['id' => $c->id, 'number' => $c->number, 'name' => $c->name])->all());
    }

    public function isActive(string $id): bool
    {
        return Company::query()->whereKey($id)->where('status', 'active')->exists();
    }

    public function names(array $ids): array
    {
        $names = [];
        foreach (Company::query()->whereIn('id', Lists::strings($ids))->get() as $company) {
            $names[$company->id] = $company->name;
        }

        return $names;
    }
}
