<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Http;

use Illuminate\Database\Eloquent\Builder;
use Modules\Crm\Domain\Company;

final class CompanySearch
{
    /** @return Builder<Company> */
    public static function query(string $search, string $status): Builder
    {
        $query = Company::query()->where('status', $status)->orderBy('name')->orderBy('id');
        if ($search !== '') {
            // Lower case on both sides: PostgreSQL compares with case, the others without.
            $like = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';
            $query->where(fn (Builder $q) => $q->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(number) like ?', [$like]));
        }

        return $query;
    }
}
