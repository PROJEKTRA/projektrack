<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Random identity of this installation, to which licenses are bound (ADR-0003).
 * Kept in the database and, outside the web root, in a file; both travel with
 * a backup. The database value counts.
 */
final readonly class InstanceId
{
    public function __construct(private string $file) {}

    public function get(): string
    {
        $stored = DB::table('instance_state')->where('id', 1)->value('instance_id');

        if (! is_string($stored)) {
            $kept = is_file($this->file) ? trim((string) file_get_contents($this->file)) : '';
            DB::table('instance_state')->insertOrIgnore([
                'id' => 1,
                'instance_id' => self::isValid($kept) ? $kept : 'inst_'.Str::ulid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Another process may have been first.
            $stored = DB::table('instance_state')->where('id', 1)->value('instance_id');
        }

        if (! is_string($stored) || ! self::isValid($stored)) {
            throw new RuntimeException('The instance id is not readable.');
        }
        if (! is_file($this->file)) {
            @file_put_contents($this->file, $stored."\n", LOCK_EX);
        }

        return $stored;
    }

    private static function isValid(string $id): bool
    {
        return preg_match('/^inst_[0-9A-HJKMNP-TV-Z]{26}$/', $id) === 1;
    }
}
