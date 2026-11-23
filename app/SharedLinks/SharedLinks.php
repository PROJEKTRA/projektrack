<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\SharedLinks;

use App\Audit\AuditLog;
use App\Models\SharedLink;
use App\Models\SharedLinkAccess;
use App\Models\User;
use App\Modules\TenantModules;
use App\Settings\Settings;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Shared links (S-0987, security baseline section 7): a token of 256 bits
 * from a CSPRNG, shown once, stored as SHA-256; 30 days by default, capped
 * by the tenant's limit; revocable at once; an optional password (Argon2id);
 * ten wrong passwords lock the link for an hour. Every use is logged with
 * the link, never the token. The object must belong to the current tenant.
 */
final class SharedLinks
{
    public const string PREFIX = 'shl_';

    /** Token as it appears in a URL: prefix and 43 base64url characters. */
    public const string PATTERN = 'shl_[A-Za-z0-9_-]{43}';

    public const int DEFAULT_DAYS = 30;

    public const int MAX_FAILED_PASSWORDS = 10;

    public const int LOCK_MINUTES = 60;

    public const int PASSWORD_MIN = 8;

    public const int PASSWORD_MAX = 200;

    public function __construct(
        private readonly SharedLinkTargets $targets,
        private readonly TenantModules $modules,
        private readonly TenantContext $tenants,
        private readonly Settings $settings,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param  list<string>  $actions  granted actions; "view" is always granted
     * @return array{link: SharedLink, token: string} the token is never available again
     *
     * @throws AuthorizationException when the object is not there or the user may not share it
     */
    public function create(User $user, string $type, string $id, array $actions = ['view'], int $days = self::DEFAULT_DAYS, ?string $password = null): array
    {
        $target = $this->targets->get($type);
        $object = $this->modules->isEnabled($target->module()) ? $this->objectOf($target, $id) : null;

        if ($object === null || ! $target->canShare($user, $object)) {
            throw new AuthorizationException;
        }

        $actions = array_values(array_unique(['view', ...$actions]));
        $unknown = array_diff($actions, array_keys($target->actions()));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown shared link actions: '.implode(', ', $unknown));
        }

        $password = $password === '' ? null : $password;
        if ($password !== null && (mb_strlen($password) < self::PASSWORD_MIN || strlen($password) > self::PASSWORD_MAX)) {
            throw new InvalidArgumentException('A shared link password has '.self::PASSWORD_MIN.' to '.self::PASSWORD_MAX.' characters.');
        }

        $days = max(1, min($this->maxDays(), $days));
        $token = self::PREFIX.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $link = SharedLink::query()->create([
            'created_by' => $user->id,
            'target_type' => $type,
            'target_id' => $id,
            'actions' => $actions,
            'token' => self::hash($token),
            'password' => $password !== null ? Hash::make($password) : null,
            'expires_at' => now()->addDays($days),
        ]);

        $this->audit->record('shared_links.created', $link, [
            'target_type' => $type,
            'target_id' => $id,
            'actions' => $actions,
            'days' => $days,
            'password' => $password !== null,
        ], $user);

        return ['link' => $link, 'token' => $token];
    }

    /**
     * The link of a token in the current tenant, active or not, with its
     * object; null when there is none, the module is off or the object is
     * gone or belongs elsewhere.
     */
    public function resolve(string $token): ?ResolvedLink
    {
        if (preg_match('/^'.self::PATTERN.'$/', $token) !== 1) {
            return null;
        }

        $link = SharedLink::query()->where('token', self::hash($token))->first();
        $target = $link !== null ? $this->targets->find($link->target_type) : null;

        if ($link === null || $target === null || ! $this->modules->isEnabled($target->module())) {
            return null;
        }

        $object = $this->objectOf($target, $link->target_id);

        return $object !== null ? new ResolvedLink($link, $target, $object) : null;
    }

    /**
     * Checks a password. Each attempt is counted before the hash is checked,
     * in one statement, so parallel attempts cannot share a count: ten wrong
     * ones lock the link for an hour, and while it is locked nothing is
     * checked.
     */
    public function checkPassword(SharedLink $link, string $password): bool
    {
        if ($link->password === null) {
            return true;
        }

        $counted = $this->unlocked(SharedLink::query()->whereKey($link->id))->increment('failed_attempts');
        if ($counted === 0) {
            return false;
        }

        // Count and lock in one read: an attempt counted just before a lock must
        // not be checked after it. Drivers return the count as int or numeric
        // string; anything else fails closed.
        $state = SharedLink::query()->whereKey($link->id)->first(['failed_attempts', 'locked_until']);
        if ($state === null || $state->isLocked()) {
            return false;
        }
        $count = $state->getRawOriginal('failed_attempts');
        $attempt = is_int($count) || (is_string($count) && ctype_digit($count)) ? (int) $count : PHP_INT_MAX;
        if ($attempt <= self::MAX_FAILED_PASSWORDS && strlen($password) <= self::PASSWORD_MAX && Hash::check($password, $link->password)) {
            SharedLink::query()->whereKey($link->id)->update(['failed_attempts' => 0]);

            return true;
        }

        if ($attempt >= self::MAX_FAILED_PASSWORDS) {
            $locked = $this->unlocked(SharedLink::query()->whereKey($link->id))
                ->update(['failed_attempts' => 0, 'locked_until' => now()->addMinutes(self::LOCK_MINUTES)]);

            if ($locked > 0) {
                $this->audit->record('shared_links.locked', $link, ['minutes' => self::LOCK_MINUTES]);
            }
        }

        return false;
    }

    /**
     * What an unlocked session keeps: bound to the link and its password, so
     * a new password needs a new unlock.
     */
    public function grant(SharedLink $link): string
    {
        return hash('sha256', $link->id.'|'.$link->password);
    }

    public function revoke(SharedLink $link, User $user): void
    {
        if ($link->revoked_at !== null) {
            return;
        }

        $link->forceFill(['revoked_at' => now(), 'revoked_by' => $user->id])->save();
        $this->audit->record('shared_links.revoked', $link, [], $user);
    }

    /**
     * Revokes every link to an object, for example when it is deleted or
     * replaced (revoking the parent revokes its links).
     */
    public function revokeAllFor(string $type, string $id, ?User $user = null): int
    {
        $count = SharedLink::query()
            ->where('target_type', $type)
            ->where('target_id', $id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_by' => $user?->id]);

        if ($count > 0) {
            $this->audit->record('shared_links.revoked_all', [$type, $id], ['count' => $count], $user);
        }

        return $count;
    }

    /** @return list<SharedLink> links to an object, newest first */
    public function of(string $type, string $id): array
    {
        return array_values(SharedLink::query()
            ->where('target_type', $type)
            ->where('target_id', $id)
            ->latest('created_at')
            ->get()
            ->all());
    }

    /** @return list<SharedLinkAccess> uses of a link, newest first */
    public function accesses(SharedLink $link, int $limit = 200): array
    {
        return array_values(SharedLinkAccess::query()
            ->where('shared_link_id', $link->id)
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->all());
    }

    /** Records one use: a shortened address and the browser family only. */
    public function logAccess(SharedLink $link, string $action, string $outcome, ?string $ip, ?string $userAgent): void
    {
        SharedLinkAccess::query()->create([
            'shared_link_id' => $link->id,
            'action' => $action,
            'outcome' => $outcome,
            'ip_prefix' => self::shortenIp($ip),
            'browser' => self::browserFamily($userAgent),
            'occurred_at' => now(),
        ]);

        if ($outcome === 'ok') {
            $link->forceFill(['last_accessed_at' => now()])->save();
        }
    }

    /** The tenant's longest validity in days (setting core.shared_link_max_days). */
    public function maxDays(): int
    {
        $days = $this->settings->get('core.shared_link_max_days');

        return is_int($days) ? max(1, min(365, $days)) : 90;
    }

    /**
     * The address of the shared link being answered, for redirects inside a
     * target. Never use back() there: the session keeps no previous URL for
     * shared links, because it would hold the token.
     */
    public static function here(Request $request): string
    {
        return $request->getBaseUrl().$request->getPathInfo();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** IPv4 to /24, IPv6 to /48: enough to tell places apart, not people. */
    public static function shortenIp(?string $ip): ?string
    {
        $packed = self::packIp($ip);

        if ($packed === null) {
            return null;
        }

        return strlen($packed) === 4
            ? (string) inet_ntop(substr($packed, 0, 3)."\0")
            : (string) inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10));
    }

    /** Key for rate limits: IPv6 by /64, the block one subscriber usually has. */
    public static function limitKey(?string $ip): string
    {
        $packed = self::packIp($ip);

        if ($packed === null || strlen($packed) === 4) {
            return (string) $ip;
        }

        return (string) inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8));
    }

    public static function browserFamily(?string $userAgent): string
    {
        $agent = $userAgent ?? '';

        return match (true) {
            preg_match('/bot|crawl|spider|preview|fetch|scan|curl|wget|python|http/i', $agent) === 1 => 'bot',
            str_contains($agent, 'Edg/') => 'edge',
            str_contains($agent, 'Firefox/') => 'firefox',
            str_contains($agent, 'Chrome/') => 'chrome',
            str_contains($agent, 'Safari/') => 'safari',
            default => 'other',
        };
    }

    /** IPv4 as 4 bytes (also when mapped into IPv6), IPv6 as 16, else null. */
    private static function packIp(?string $ip): ?string
    {
        $packed = $ip !== null ? @inet_pton($ip) : false;

        if ($packed === false) {
            return null;
        }

        // ::ffff:a.b.c.d is an IPv4 address.
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return substr($packed, 12);
        }

        return $packed;
    }

    /** The object of a link, only when it is a tenant-owned model of this tenant. */
    private function objectOf(SharedLinkTarget $target, string $id): ?Model
    {
        $object = $target->find($id);

        if ($object === null
            || ! in_array(BelongsToTenant::class, class_uses_recursive($object), true)
            || $object->getAttribute('tenant_id') !== $this->tenants->id()) {
            return null;
        }

        return $object;
    }

    /**
     * @param  Builder<SharedLink>  $query
     * @return Builder<SharedLink>
     */
    private function unlocked(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('locked_until')->orWhere('locked_until', '<=', now()));
    }
}
