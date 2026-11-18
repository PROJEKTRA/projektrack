<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Audit;

use App\Auth\TwoFactor\RecoveryCodeUsed;
use App\Auth\TwoFactor\TwoFactorReset;
use App\Authorization\Events\PermissionsChanged;
use App\Authorization\Events\UserRolesChanged;
use App\Models\User;
use App\Modules\ModuleDisabled;
use App\Modules\ModuleEnabled;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Turns security and administration events into audit entries (security
 * baseline 8.1). Modules add their own listeners for financial records,
 * exports and settings as they arrive.
 */
final class AuditSubscriber
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** Resolved per use: the writer is scoped to one request or job. */
    private function audit(): AuditLog
    {
        return app(AuditLog::class);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Failed::class => 'loginFailed',
            Logout::class => 'logout',
            Lockout::class => 'lockout',
            PasswordReset::class => 'passwordReset',
            Verified::class => 'emailVerified',
            TwoFactorAuthenticationEnabled::class => 'twoFactorEnabled',
            TwoFactorAuthenticationConfirmed::class => 'twoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'twoFactorDisabled',
            TwoFactorAuthenticationFailed::class => 'twoFactorFailed',
            RecoveryCodesGenerated::class => 'recoveryCodesGenerated',
            RecoveryCodeUsed::class => 'recoveryCodeUsed',
            TwoFactorReset::class => 'twoFactorReset',
            UserRolesChanged::class => 'rolesChanged',
            PermissionsChanged::class => 'permissionsChanged',
            ModuleEnabled::class => 'moduleEnabled',
            ModuleDisabled::class => 'moduleDisabled',
        ];
    }

    public function login(Login $event): void
    {
        $this->forUser($event->user, 'auth.login', ['remember' => $event->remember]);
    }

    public function loginFailed(Failed $event): void
    {
        // Never the submitted credentials; the subject only when the account exists.
        if ($this->tenant->has()) {
            $user = $event->user instanceof User ? $event->user : null;
            $email = $event->credentials['email'] ?? null;

            // The sign-in pipeline reports failures without the user (S-0903).
            if ($user === null && is_string($email) && $email !== '') {
                $found = User::query()->where('email', Str::lower(trim($email)))->first();
                $user = $found !== null && hash_equals(Str::lower($found->email), Str::lower(trim($email))) ? $found : null;
            }

            $this->audit()->record('auth.login_failed', $user, [], null);
        }
    }

    public function logout(Logout $event): void
    {
        $this->forUser($event->user, 'auth.logout');
    }

    public function lockout(Lockout $event): void
    {
        if ($this->tenant->has()) {
            $this->audit()->record('auth.lockout');
        }
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->forUser($event->user, 'auth.password_reset');
    }

    public function emailVerified(Verified $event): void
    {
        $this->forUser($event->user, 'auth.email_verified');
    }

    public function twoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->forUser($event->user, 'auth.two_factor_enabled');
    }

    public function twoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->forUser($event->user, 'auth.two_factor_confirmed');
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->forUser($event->user, 'auth.two_factor_disabled');
    }

    public function twoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->forUser($event->user, 'auth.two_factor_failed', [], asActor: false);
    }

    public function recoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->forUser($event->user, 'auth.recovery_codes_generated');
    }

    public function recoveryCodeUsed(RecoveryCodeUsed $event): void
    {
        $this->audit()->record('auth.recovery_code_used', ['users', $event->userId], ['remaining' => $event->remaining], tenantId: $event->tenantId);
    }

    public function twoFactorReset(TwoFactorReset $event): void
    {
        $this->audit()->record('auth.two_factor_reset', ['users', $event->userId], [], $this->user($event->actorId), $event->tenantId);
    }

    public function rolesChanged(UserRolesChanged $event): void
    {
        $this->audit()->record(
            $event->assigned ? 'roles.assigned' : 'roles.revoked',
            ['users', $event->userId],
            ['role_id' => $event->roleId],
            $this->user($event->actorId),
            $event->tenantId,
        );
    }

    public function permissionsChanged(PermissionsChanged $event): void
    {
        $this->audit()->record(
            'permissions.changed',
            [$event->kind === 'project_role' ? 'project_roles' : 'roles', $event->roleId],
            ['kind' => $event->kind, 'granted' => $event->granted, 'revoked' => $event->revoked],
            $this->user($event->actorId),
            $event->tenantId,
        );
    }

    public function moduleEnabled(ModuleEnabled $event): void
    {
        $this->audit()->record('modules.enabled', ['modules', $event->module], ['first_time' => $event->firstTime], $this->user($event->actorId), $event->tenantId);
    }

    public function moduleDisabled(ModuleDisabled $event): void
    {
        $this->audit()->record('modules.disabled', ['modules', $event->module], [], $this->user($event->actorId), $event->tenantId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function forUser(mixed $user, string $action, array $context = [], bool $asActor = true): void
    {
        if (! $user instanceof User) {
            return;
        }

        $this->audit()->record($action, $user, $context, $asActor ? $user : null, $user->tenant_id);
    }

    private function user(?string $id): ?User
    {
        return $id === null ? null : User::query()->find($id);
    }
}
