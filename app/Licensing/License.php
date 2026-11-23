<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use App\Modules\Edition;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Content of a license token (S-0974, ADR-0003 section 3). Built only from a
 * verified payload; every field is checked here, so nothing later has to
 * trust an unchecked value.
 */
final readonly class License
{
    private const int MAX_GRACE_DAYS = 90;

    /**
     * @param  list<string>  $domains
     * @param  list<string>  $features
     * @param  list<string>  $addons
     */
    private function __construct(
        public string $id,
        public int $serial,
        public string $kid,
        public string $customer,
        public Edition $edition,
        public string $plan,
        public int $fullSeats,
        public int $lightSeats,
        public string $instanceId,
        public array $domains,
        public array $features,
        public array $addons,
        public string $declaredStatus,
        public ?CarbonImmutable $statusUntil,
        public CarbonImmutable $issuedAt,
        public CarbonImmutable $notBefore,
        public CarbonImmutable $expiresAt,
        public int $expiryGraceDays,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidLicense
     */
    public static function fromPayload(array $data): self
    {
        if (($data['v'] ?? null) !== 1 || ($data['iss'] ?? null) !== 'projektra-licensing') {
            throw new InvalidLicense('This is not a license of a known version.');
        }

        $binding = self::map($data, 'binding');
        $seats = self::map($data, 'seats');
        $grace = self::map($data, 'grace');
        $status = self::string($data, 'status');
        $until = $data['status_until'] ?? null;
        $edition = Edition::tryFrom(self::string($data, 'edition'));

        if ($edition === null || $edition === Edition::Community) {
            throw new InvalidLicense('The edition of the license is unknown.');
        }
        if (! in_array($status, ['active', 'pending_payment', 'trial'], true)) {
            throw new InvalidLicense('The status of the license is unknown.');
        }
        if ($status !== 'active' && ! is_string($until)) {
            throw new InvalidLicense('The license does not say until when its status holds.');
        }

        $license = new self(
            id: self::string($data, 'license_id'),
            serial: self::integer($data, 'serial', 1, PHP_INT_MAX),
            kid: self::string($data, 'kid'),
            customer: self::string(self::map($data, 'customer'), 'name'),
            edition: $edition,
            plan: self::string($data, 'plan'),
            fullSeats: self::integer($seats, 'full', 0, 1_000_000),
            lightSeats: self::integer($seats, 'light', 0, 1_000_000),
            instanceId: self::string($binding, 'instance_id'),
            domains: self::strings($binding, 'domains'),
            features: self::strings($data, 'features'),
            addons: self::strings($data, 'addons'),
            declaredStatus: $status,
            statusUntil: is_string($until) ? self::time($until) : null,
            issuedAt: self::time(self::string($data, 'issued_at')),
            notBefore: self::time(self::string($data, 'not_before')),
            expiresAt: self::time(self::string($data, 'expires_at')),
            expiryGraceDays: self::integer($grace, 'expiry_days', 0, self::MAX_GRACE_DAYS),
        );

        if ($license->expiresAt <= $license->notBefore) {
            throw new InvalidLicense('The license ends before it starts.');
        }

        return $license;
    }

    public function status(CarbonImmutable $now): LicenseStatus
    {
        return match (true) {
            $now < $this->notBefore => LicenseStatus::NotYetValid,
            $now >= $this->expiresAt->addDays($this->expiryGraceDays) => LicenseStatus::ReadOnly,
            $this->declaredStatus !== 'active' && $this->statusUntil !== null && $now > $this->statusUntil => LicenseStatus::ReadOnly,
            $now >= $this->expiresAt => LicenseStatus::Grace,
            $this->declaredStatus === 'pending_payment' => LicenseStatus::PendingPayment,
            default => LicenseStatus::Active,
        };
    }

    /** A host outside the binding is a warning, never a stop: domains change during migrations. */
    public function coversDomain(string $host): bool
    {
        return in_array(strtolower($host), array_map(strtolower(...), $this->domains), true);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : throw new InvalidLicense("The license has no valid \"{$key}\".");
    }

    /** @param  array<array-key, mixed>  $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' && strlen($value) <= 255
            ? $value
            : throw new InvalidLicense("The license has no valid \"{$key}\".");
    }

    /** @param  array<array-key, mixed>  $data */
    private static function integer(array $data, string $key, int $min, int $max): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) && $value >= $min && $value <= $max
            ? $value
            : throw new InvalidLicense("The license has no valid \"{$key}\".");
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        $strings = is_array($value) ? array_values(array_filter($value, is_string(...))) : null;

        return $strings !== null && $strings === $value
            ? $strings
            : throw new InvalidLicense("The license has no valid \"{$key}\".");
    }

    private static function time(string $value): CarbonImmutable
    {
        try {
            $time = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC');
        } catch (Throwable) {
            $time = null;
        }

        // Read back, so 30 February is not taken for 2 March.
        return $time instanceof CarbonImmutable && $time->format('Y-m-d\TH:i:s\Z') === $value
            ? $time
            : throw new InvalidLicense('The license has an invalid time.');
    }
}
