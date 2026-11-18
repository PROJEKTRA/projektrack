<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

/**
 * Argument names of an ICU message, including those inside plural and select
 * branches (S-0921). A translation may only use arguments its en source
 * declares: the browser formatter refuses a message with an argument it is
 * not given.
 *
 * The message must already be valid ICU (checked with MessageFormatter); this
 * reader only walks it, honoring apostrophe quoting.
 */
final class MessageArguments
{
    private const array COMPLEX = ['plural', 'selectordinal', 'select'];

    private string $message;

    private int $position = 0;

    private int $length;

    /** @var array<string, true> */
    private array $names = [];

    private function __construct(string $message)
    {
        $this->message = $message;
        $this->length = strlen($message);
    }

    /**
     * @return list<string>
     */
    public static function of(string $message): array
    {
        $reader = new self($message);
        $reader->text(false);

        $names = array_keys($reader->names);
        sort($names);

        return $names;
    }

    /** Read literal text until the end, or until the "}" closing a branch. */
    private function text(bool $inBranch): void
    {
        while ($this->position < $this->length) {
            $char = $this->message[$this->position];

            if ($char === "'") {
                $this->quote($inBranch);

                continue;
            }

            if ($char === '{') {
                $this->position++;
                $this->argument();

                continue;
            }

            if ($char === '}' && $inBranch) {
                $this->position++;

                return;
            }

            $this->position++;
        }
    }

    /** '' is one apostrophe; an apostrophe before syntax starts quoted text. */
    private function quote(bool $inBranch): void
    {
        $next = $this->message[$this->position + 1] ?? '';

        if ($next === "'") {
            $this->position += 2;

            return;
        }

        if ($next === '{' || $next === '}' || ($inBranch && $next === '#') || $next === '|') {
            $end = strpos($this->message, "'", $this->position + 1);
            $this->position = $end === false ? $this->length : $end + 1;

            return;
        }

        $this->position++;
    }

    private function argument(): void
    {
        $this->whitespace();
        $name = $this->token();
        if ($name !== '') {
            $this->names[$name] = true;
        }
        $this->whitespace();

        if (($this->message[$this->position] ?? '') !== ',') {
            $this->position++; // "}"

            return;
        }

        $this->position++;
        $this->whitespace();
        $type = $this->token();
        $this->whitespace();

        if (! in_array($type, self::COMPLEX, true)) {
            $this->skipStyle();

            return;
        }

        if (($this->message[$this->position] ?? '') === ',') {
            $this->position++;
        }

        while ($this->position < $this->length) {
            $this->whitespace();
            $char = $this->message[$this->position] ?? '';

            if ($char === '}') {
                $this->position++;

                return;
            }

            if ($char === '{') {
                $this->position++;
                $this->text(true);

                continue;
            }

            // A selector (one, other, =0) or "offset:1".
            if ($this->token() === '') {
                $this->position++;
            }
        }
    }

    /** Skip ", number, ::currency/EUR" and similar up to the closing "}". */
    private function skipStyle(): void
    {
        $depth = 0;

        while ($this->position < $this->length) {
            $char = $this->message[$this->position++];

            if ($char === "'") {
                $end = strpos($this->message, "'", $this->position);
                $this->position = $end === false ? $this->length : $end + 1;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                if ($depth === 0) {
                    return;
                }
                $depth--;
            }
        }
    }

    private function token(): string
    {
        $start = $this->position;

        while ($this->position < $this->length && ! str_contains(" \t\r\n,{}", $this->message[$this->position])) {
            $this->position++;
        }

        return substr($this->message, $start, $this->position - $start);
    }

    private function whitespace(): void
    {
        while ($this->position < $this->length && str_contains(" \t\r\n", $this->message[$this->position])) {
            $this->position++;
        }
    }
}
