<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\SharedLinks;

use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a module can share (S-0987), for example a service report. The module
 * registers it with SharedLinkTargets; the core handles tokens, expiry,
 * passwords, limits, the access log and tenant isolation, the target only
 * finds and shows its object.
 */
interface SharedLinkTarget
{
    /** Stored in shared_links.target_type, for example "service_report". */
    public function type(): string;

    /** Key of the module; links answer only while it is enabled for the tenant. */
    public function module(): string;

    /**
     * Actions a link may grant, as action => true when it changes something
     * (only POST with a CSRF token reaches it) or false when it only reads
     * (GET). "view" must be one and must only read.
     *
     * @return array<string, bool>
     */
    public function actions(): array;

    /**
     * The object, or null when it is gone. It must be a tenant-owned model
     * (BelongsToTenant); the core refuses one of another tenant.
     */
    public function find(string $id): ?Model;

    /** May this user share the object? Checked on every new link. */
    public function canShare(User $user, Model $object): bool;

    /**
     * Answers one granted action. The response must not link into the rest
     * of the instance. Redirect only to SharedLinks::here($request), never
     * with back() or a validate() redirect: the session keeps no previous URL
     * for shared links, so those would lead to the sign-in page.
     */
    public function respond(SharedLink $link, Model $object, string $action, Request $request): Response;
}
