<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\SharedLinks;

use App\Models\SharedLink;
use Illuminate\Database\Eloquent\Model;

/** A shared link with its target and its object of the current tenant (S-0987). */
final readonly class ResolvedLink
{
    public function __construct(
        public SharedLink $link,
        public SharedLinkTarget $target,
        public Model $object,
    ) {}

    /** Granted on the link and still offered by the module. */
    public function grants(string $action): bool
    {
        return $this->link->allows($action) && array_key_exists($action, $this->target->actions());
    }

    /** True when the action changes something (POST only). */
    public function mutates(string $action): bool
    {
        return $this->target->actions()[$action] ?? true;
    }
}
