<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Auth\TwoFactor\RecoveryCodeUsed;
use App\Authorization\UserType;
use App\I18n\Strings;
use App\Notifications\ResetPasswordLink;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * A person's account in one tenant (ADR-0006, decision 5).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserType $user_type
 * @property string|null $locale
 * @property string|null $timezone
 * @property string|null $format_locale
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property int $session_version
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'locale', 'format_locale', 'timezone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasUlids, Notifiable, TwoFactorAuthenticatable;

    /**
     * Internal users start as Light; the type follows their permissions.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['user_type' => 'light', 'session_version' => 1];

    /**
     * Global roles. Assign and revoke through RoleManager only, which keeps the
     * user type and the permission cache in step.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Language for mails and notifications to this user (S-0916): their own
     * language, else the tenant language, else bs.
     */
    public function preferredLocale(): string
    {
        $tenants = app(TenantContext::class);
        $tenantLocale = $tenants->has() ? $tenants->current()->default_locale : null;

        return app(Strings::class)->chain($this->locale ?? $tenantLocale)[0];
    }

    /**
     * Reset links go out from the queue (S-0903), so the request takes as long
     * for a known address as for an unknown one.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    /**
     * Hashes of the unused recovery codes (S-0904). Plain codes are never stored.
     *
     * @return list<string>
     */
    public function recoveryCodes(): array
    {
        $decoded = is_string($this->two_factor_recovery_codes) ? json_decode($this->two_factor_recovery_codes, true) : null;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * Called by Fortify after a successful recovery-code sign-in with the
     * matching hash: the code is used up, not replaced.
     *
     * @param  string  $code
     */
    public function replaceRecoveryCode($code): void
    {
        $remaining = array_values(array_filter($this->recoveryCodes(), fn (string $hash): bool => ! hash_equals($hash, $code)));

        $this->forceFill(['two_factor_recovery_codes' => json_encode($remaining, JSON_THROW_ON_ERROR)])->save();

        event(new RecoveryCodeUsed($this->tenant_id, $this->id, count($remaining)));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_type' => UserType::class,
            'two_factor_confirmed_at' => 'datetime',
            'session_version' => 'integer',
        ];
    }
}
