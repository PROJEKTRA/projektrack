<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * File uploads (S-0909, security baseline section 4). Each context allows
 * content types detected from the bytes, the file name endings that belong
 * to them and a size limit. Anything else (SVG, HTML, scripts, archives,
 * executables) is refused.
 */
return [

    // Private disk outside the web root (config/filesystems.php "local").
    'disk' => 'local',

    // Images are decoded to re-encode them: refuse sizes that would exhaust memory.
    'max_pixels' => (int) env('UPLOAD_MAX_PIXELS', 40_000_000),
    'max_side' => (int) env('UPLOAD_MAX_SIDE', 10_000),

    // Storage quotas, so one user or tenant cannot fill the disk.
    'quota_tenant_mb' => (int) env('UPLOAD_QUOTA_TENANT_MB', 10_240),
    'quota_user_daily_mb' => (int) env('UPLOAD_QUOTA_USER_DAILY_MB', 500),

    // Signed download links expire after this many minutes.
    'link_minutes' => (int) env('UPLOAD_LINK_MINUTES', 5),

    'contexts' => [
        'attachments' => [
            'max_kb' => 25 * 1024,
            'types' => [
                'application/pdf' => ['pdf'],
                'image/png' => ['png'],
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/webp' => ['webp'],
                'text/plain' => ['txt', 'csv'],
                'text/csv' => ['csv'],
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            ],
        ],
        // Brand logo (S-0911): small raster images; SVG is never accepted.
        'logos' => [
            'max_kb' => 1024,
            // Public before sign-in: only re-encoded images, so no metadata (EXIF) is published.
            'require_reencode' => true,
            'types' => [
                'image/png' => ['png'],
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/webp' => ['webp'],
            ],
        ],
        'images' => [
            'max_kb' => 5 * 1024,
            'types' => [
                'image/png' => ['png'],
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/webp' => ['webp'],
            ],
        ],
    ],

];
