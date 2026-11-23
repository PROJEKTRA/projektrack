<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Branding;

use App\Audit\AuditLog;
use App\Models\BrandProfile;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * The brand a user sees (S-0911, ADR-0006 section 8): the tenant's brand
 * profile, then the instance brand (config/brand.php). Strings use it for
 * {brand}; the page title, logo, theme color and e-mail sender read it here.
 * Cached per tenant, because jobs may switch tenants.
 */
final class Brand
{
    /** @var array<string, array{name: string, primaryColor: string|null, logoFileId: string|null, senderName: string, poweredBy: bool}> */
    private array $resolved = [];

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditLog $audit,
    ) {}

    /** @return array{name: string, primaryColor: string|null, logoFileId: string|null, senderName: string, poweredBy: bool} */
    public function current(): array
    {
        $key = $this->tenants->has() ? $this->tenants->id() : '';

        return $this->resolved[$key] ??= $this->resolve();
    }

    public function name(): string
    {
        return $this->current()['name'];
    }

    /**
     * The name a visitor may see before sign-in: the tenant's own product name,
     * else nothing. The shipped product name is never shown to anonymous
     * visitors (invisible by default: no fingerprint before sign-in).
     */
    public function publicName(): string
    {
        $title = config('brand.login_title');

        return $this->profile()->product_name ?? (is_string($title) ? $title : '');
    }

    /** The name for the current request: full when signed in, public otherwise. */
    public function nameForRequest(): string
    {
        return auth()->check() ? $this->name() : $this->publicName();
    }

    /** Relative URL of the logo, or null for the shipped mark. */
    public function logoUrl(): ?string
    {
        $file = $this->current()['logoFileId'];

        return $file !== null ? route('brand.logo', ['version' => $file], false) : null;
    }

    /**
     * Whether the licensor's mark stands for the brand: only after sign-in, and
     * only while neither the tenant nor the instance has a brand of its own.
     */
    private function licensorMark(): bool
    {
        return auth()->check() && $this->current()['logoFileId'] === null && $this->name() === config('brand.shipped_name');
    }

    /**
     * Shared Inertia prop `brand`.
     *
     * @return array{name: string, logoUrl: string|null, licensorMark: bool, poweredBy: bool}
     */
    public function toArray(): array
    {
        return ['name' => $this->nameForRequest(), 'logoUrl' => $this->logoUrl(), 'licensorMark' => $this->licensorMark(), 'poweredBy' => $this->current()['poweredBy']];
    }

    /**
     * CSS for the page head: the primary color and a readable text color on
     * it. Only validated #rrggbb values reach the stylesheet.
     */
    public function themeCss(): ?string
    {
        $color = $this->current()['primaryColor'];

        if ($color === null) {
            return null;
        }

        $foreground = self::readableOn($color);

        return ":root{--primary:{$color};--primary-foreground:{$foreground};--sidebar-primary:{$color};--sidebar-primary-foreground:{$foreground};}";
    }

    public function profile(): ?BrandProfile
    {
        return $this->tenants->has() ? BrandProfile::query()->first() : null;
    }

    /**
     * Store the tenant's brand; empty values fall back to the instance brand.
     *
     * @param  array{product_name?: string|null, primary_color?: string|null, email_sender_name?: string|null, logo_file_id?: string|null}  $values
     */
    public function save(array $values, User $actor): BrandProfile
    {
        $profile = BrandProfile::query()->firstOrNew();
        $profile->fill([
            ...array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $values),
            'updated_by' => $actor->id,
        ]);
        // A new profile counts only the values it sets, not the empty ones.
        $changed = array_keys(array_filter($profile->getDirty(), fn (mixed $value): bool => $profile->exists || $value !== null));
        $changed = array_values(array_diff($changed, ['updated_by', 'tenant_id', 'id']));

        if ($changed === []) {
            return $profile;
        }
        $profile->save();

        $this->audit->record('branding.changed', $profile, ['fields' => $changed], $actor);
        unset($this->resolved[$this->tenants->id()]);

        return $profile;
    }

    public static function isColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}\z/', $value) === 1;
    }

    /** Black or white, whichever reads better on the color (WCAG relative luminance). */
    public static function readableOn(string $color): string
    {
        $channel = function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = array_map(fn (string $hex): float => $channel((int) hexdec($hex)), str_split(substr($color, 1), 2));
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // Contrast with white (1.05 / (L + 0.05)) against contrast with black ((L + 0.05) / 0.05).
        return 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.05 ? '#ffffff' : '#000000';
    }

    /** @return array{name: string, primaryColor: string|null, logoFileId: string|null, senderName: string, poweredBy: bool} */
    private function resolve(): array
    {
        $profile = $this->profile();
        $name = $profile?->product_name ?: config()->string('brand.name');
        $color = $profile?->primary_color ?: config('brand.primary_color');
        $color = is_string($color) ? strtolower($color) : null;
        $sender = $profile?->email_sender_name ?: config('brand.email_sender_name');

        return [
            'name' => $name,
            'primaryColor' => is_string($color) && self::isColor($color) ? $color : null,
            'logoFileId' => $profile?->logo_file_id,
            'senderName' => is_string($sender) && $sender !== '' ? $sender : $name,
            // White label removal needs an entitlement (ADR-0006); licensing is not built yet.
            'poweredBy' => true,
        ];
    }
}
