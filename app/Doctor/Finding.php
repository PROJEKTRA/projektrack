<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Doctor;

/**
 * One result of the doctor (S-0988). Title and fix are string identifiers
 * (core:doctor.<check>.title|fix), so CLI and admin page show them in the
 * reader's language; `detail` holds technical facts only (a URL path, a
 * header name), never secrets.
 */
final readonly class Finding
{
    public const string CRITICAL = 'critical';

    public const string WARNING = 'warning';

    public function __construct(
        public string $check,
        public string $severity,
        public bool $ok,
        public ?string $detail = null,
    ) {}

    public function title(): string
    {
        return "core:doctor.{$this->check}.title";
    }

    public function fix(): string
    {
        return "core:doctor.{$this->check}.fix";
    }

    /** @return array{check: string, severity: string, ok: bool, detail: string|null} */
    public function toArray(): array
    {
        return ['check' => $this->check, 'severity' => $this->severity, 'ok' => $this->ok, 'detail' => $this->detail];
    }
}
