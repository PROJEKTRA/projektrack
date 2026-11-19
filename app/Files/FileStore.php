<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Files;

use App\Audit\AuditLog;
use App\Models\StoredFile;
use App\Models\User;
use App\Tenancy\TenantContext;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Safe uploads and downloads (S-0909, security baseline section 4):
 * - the type comes from the bytes (fileinfo), must be allowed in the
 *   context (config/uploads.php) and match the file name ending;
 * - size limit per context; empty files are refused;
 * - stored on the private disk under a random key without file name ending,
 *   so nothing uploaded is ever served by the web server or executed;
 * - images are re-encoded, which drops metadata and anything appended;
 * - downloads only through signed links that expire and are bound to one user.
 */
final class FileStore
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function store(UploadedFile $upload, string $context, User $uploader, string $field = 'file'): StoredFile
    {
        $rules = config('uploads.contexts.'.$context);

        if (! is_array($rules)) {
            throw new InvalidArgumentException("Unknown upload context {$context}.");
        }

        $path = $upload->getRealPath();
        $size = $path !== false ? (int) filesize($path) : 0;
        $maxBytes = (is_int($rules['max_kb'] ?? null) ? $rules['max_kb'] : 0) * 1024;

        if ($path === false || ! $upload->isValid() || $size === 0) {
            throw ValidationException::withMessages([$field => __('core:files.error_empty')]);
        }

        if ($size > $maxBytes) {
            throw ValidationException::withMessages([$field => __('core:files.error_size', ['mb' => intdiv($maxBytes, 1024 * 1024)])]);
        }

        $this->assertQuota($uploader, $size, $field);

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        /** @var array<string, list<string>> $types */
        $types = $rules['types'];

        if (! isset($types[$mime])) {
            throw ValidationException::withMessages([$field => __('core:files.error_type')]);
        }

        // The last ending counts: "invoice.pdf.php" is a php file.
        $extension = Str::lower(pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION));

        if (! in_array($extension, $types[$mime], true)) {
            throw ValidationException::withMessages([$field => __('core:files.error_extension')]);
        }

        $contents = (string) file_get_contents($path);
        $contents = $this->clean($mime, $contents, $field);

        $id = (string) Str::ulid();
        $key = 'tenants/'.$this->tenants->id().'/'.now()->format('Y/m').'/'.$id;
        $disk = config()->string('uploads.disk', 'local');

        Storage::disk($disk)->put($key, $contents, ['visibility' => 'private']);

        $file = StoredFile::query()->create([
            'disk' => $disk,
            'storage_key' => $key,
            'original_name' => self::safeName($upload->getClientOriginalName()),
            'mime_type' => $mime,
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'scan_status' => 'skipped',
            'context' => $context,
            'uploaded_by' => $uploader->id,
        ]);

        // Type and size only; never the file name, which may be personal.
        $this->audit->record('files.uploaded', $file, ['context' => $context, 'mime' => $mime, 'bytes' => $file->size_bytes], $uploader);

        return $file;
    }

    /** A signed link for this user, valid for config uploads.link_minutes. */
    public function link(StoredFile $file, User $user, bool $inline = false): string
    {
        return URL::temporarySignedRoute(
            'files.download',
            now()->addMinutes(config()->integer('uploads.link_minutes', 5)),
            ['file' => $file->id, 'for' => $user->id, ...($inline && $file->isImage() ? ['inline' => 1] : [])],
        );
    }

    /** Who may read a file. 0.9: its uploader; attachments add their own rules. */
    public function canRead(StoredFile $file, User $user): bool
    {
        return $file->uploaded_by !== null && $file->uploaded_by === $user->id;
    }

    public function download(StoredFile $file, bool $inline): StreamedResponse
    {
        $inline = $inline && $file->isImage();
        $response = Storage::disk($file->disk)->response(
            $file->storage_key,
            $file->original_name,
            [
                'Content-Type' => $file->mime_type,
                // A file can never run script or load anything, even if a browser misreads it.
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'none'; sandbox",
                'Cache-Control' => 'private, no-store',
            ],
            $inline ? 'inline' : 'attachment',
        );

        return $response;
    }

    /** Re-encode images, so EXIF data and anything appended to them is gone. */
    private function clean(string $mime, string $contents, string $field): string
    {
        if (! function_exists('imagecreatefromstring') || ! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return $contents;
        }

        // Read the declared size from the header before decoding: a few KB can
        // declare billions of pixels and exhaust memory (decompression bomb).
        $size = @getimagesizefromstring($contents);
        $width = is_array($size) ? (int) $size[0] : 0;
        $height = is_array($size) ? (int) $size[1] : 0;
        $maxSide = config()->integer('uploads.max_side', 10_000);

        if ($width < 1 || $height < 1 || $width > $maxSide || $height > $maxSide || $width * $height > config()->integer('uploads.max_pixels', 40_000_000)) {
            throw ValidationException::withMessages([$field => __('core:files.error_dimensions')]);
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw ValidationException::withMessages([$field => __('core:files.error_type')]);
        }

        if ($mime !== 'image/jpeg') {
            imagesavealpha($image, true);
        }

        ob_start();
        match ($mime) {
            'image/png' => imagepng($image),
            'image/jpeg' => imagejpeg($image, null, 90),
            default => imagewebp($image, null, 90),
        };
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * @throws ValidationException
     */
    private function assertQuota(User $uploader, int $size, string $field): void
    {
        $mb = 1024 * 1024;
        $tenantBytes = (int) StoredFile::query()->sum('size_bytes');
        $todayBytes = (int) StoredFile::query()
            ->where('uploaded_by', $uploader->id)
            ->where('created_at', '>=', now()->subDay())
            ->sum('size_bytes');

        if ($tenantBytes + $size > config()->integer('uploads.quota_tenant_mb', 10_240) * $mb
            || $todayBytes + $size > config()->integer('uploads.quota_user_daily_mb', 500) * $mb) {
            throw ValidationException::withMessages([$field => __('core:files.error_quota')]);
        }
    }

    /** The name shown and offered for download, without paths or control characters. */
    public static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        // Control and format characters (U+202E would show photo<RLO>gpj.pdf as photofdp.jpg).
        $name = (string) preg_replace('/[\x00-\x1F\x7F\p{Cf}]+/u', '', $name);

        return Str::limit($name !== '' ? $name : 'file', 200, '');
    }
}
