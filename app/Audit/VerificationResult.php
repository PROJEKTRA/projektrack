<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Audit;

final readonly class VerificationResult
{
    /** An entry is missing or out of order. */
    public const string GAP = 'gap';

    /** An entry does not point to the hash of the entry before it. */
    public const string LINK = 'link';

    /** An entry's content no longer matches its hash. */
    public const string CHANGED = 'changed';

    /** An entry was hashed with a key this installation does not know. */
    public const string UNKNOWN_KEY = 'unknown_key';

    /** The last entries were removed (the chain head expects more). */
    public const string HEAD = 'head';

    public function __construct(
        public bool $intact,
        public int $checked,
        public ?int $brokenAtSeq,
        public ?string $problem,
    ) {}
}
