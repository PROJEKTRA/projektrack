<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Support;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;

/**
 * Log tap (security baseline 4 and 7): shared link tokens and API token
 * secrets never reach a log file, whatever logs them (an exception message
 * with a URL, a debug line). Added to every channel in config/logging.php.
 */
final class RedactSecrets
{
    /** Shared link tokens (S-0987) and API token secrets (S-0913). */
    private const array PATTERNS = [
        '/shl_[A-Za-z0-9_-]{20,}/' => 'shl_[redacted]',
        '/ptk_[A-Za-z0-9_-]{20,}/' => 'ptk_[redacted]',
    ];

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
                message: self::redact($record->message),
                context: self::redactArray($record->context),
                extra: self::redactArray($record->extra),
            ));
        }
    }

    public static function redact(string $text): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                is_string($value) => self::redact($value),
                is_array($value) => self::redactArray($value),
                $value instanceof \Throwable, $value instanceof \Stringable => self::redact((string) $value),
                $value instanceof \JsonSerializable => self::redact((string) json_encode($value)),
                default => $value,
            };
        }

        return $values;
    }
}
