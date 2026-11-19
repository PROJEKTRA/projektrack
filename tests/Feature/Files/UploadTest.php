<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Files\FileStore;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * S-0909: safe uploads (security baseline section 4) and signed downloads.
 */

const MINIMAL_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

function uploader(): User
{
    $user = User::factory()->create();
    app(RoleManager::class)->assign($user, Role::query()->where('key', 'employee')->firstOrFail());

    return $user;
}

function pngBytes(): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function upload(User $user, UploadedFile $file, string $context = 'attachments'): TestResponse
{
    return test()->actingAs($user)->postJson('/files', ['file' => $file, 'context' => $context]);
}

beforeEach(fn () => Storage::fake('local'));

it('stores an allowed file privately under a random key and links it for a few minutes', function () {
    $user = uploader();

    $response = upload($user, UploadedFile::fake()->createWithContent('Invoice 2026.pdf', MINIMAL_PDF))->assertCreated();

    $file = StoredFile::query()->sole();
    expect($file->mime_type)->toBe('application/pdf')
        ->and($file->original_name)->toBe('Invoice 2026.pdf')
        ->and($file->storage_key)->toStartWith("tenants/{$this->tenant->id}/")
        ->and($file->storage_key)->not->toContain('.')
        ->and($file->sha256)->toBe(hash('sha256', MINIMAL_PDF))
        ->and(Storage::disk('local')->exists($file->storage_key))->toBeTrue()
        ->and(AuditEntry::query()->where('action', 'files.uploaded')->latest('seq')->first()?->contextData())
        ->toEqual(['context' => 'attachments', 'mime' => 'application/pdf', 'bytes' => strlen(MINIMAL_PDF)]);

    $download = $this->actingAs($user)->get($response->json('url'))->assertOk();
    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($download->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($download->headers->get('Cache-Control'))->toContain('no-store')
        ->and($download->streamedContent())->toBe(MINIMAL_PDF);
});

it('refuses what the bytes reveal as not allowed', function (string $name, string $contents) {
    upload(uploader(), UploadedFile::fake()->createWithContent($name, $contents))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(StoredFile::query()->count())->toBe(0);
})->with([
    'SVG with script' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'HTML' => ['page.html', '<!doctype html><html><body><script>alert(1)</script></body></html>'],
    'PHP script' => ['shell.php', '<?php echo "x";'],
    'PHP named as text' => ['notes.txt.php', 'plain words'],
    'double extension' => ['invoice.pdf.php', MINIMAL_PDF],
    'PDF named as image' => ['photo.png', MINIMAL_PDF],
    'image named as PDF' => ['invoice.pdf', pngBytes()],
    'empty file' => ['empty.txt', ''],
]);

it('re-encodes images, so code appended to a picture is gone', function () {
    $polyglot = pngBytes().'<?php system($_GET["c"]); ?>';

    upload(uploader(), UploadedFile::fake()->createWithContent('avatar.png', $polyglot), 'images')->assertCreated();

    $file = StoredFile::query()->sole();
    expect(Storage::disk('local')->get($file->storage_key))->not->toContain('<?php')
        ->and($file->mime_type)->toBe('image/png');
});

it('refuses files above the limit of the context', function () {
    config(['uploads.contexts.images.max_kb' => 1]);

    upload(uploader(), UploadedFile::fake()->createWithContent('big.png', pngBytes().str_repeat('x', 2048)), 'images')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('serves a download only to the user the link was made for, signed and before it expires', function () {
    $user = uploader();
    $url = upload($user, UploadedFile::fake()->createWithContent('a.txt', 'hello'))->json('url');

    // Someone else with the same link.
    $this->actingAs(uploader())->get($url)->assertForbidden();
    // Tampered link.
    $this->actingAs($user)->get($url.'&inline=1')->assertForbidden();
    // Expired link.
    $this->travel(6)->minutes();
    $this->actingAs($user)->get($url)->assertForbidden();
});

it('needs the upload permission and refuses guests', function () {
    $file = UploadedFile::fake()->createWithContent('a.txt', 'hello');

    $this->postJson('/files', ['file' => $file, 'context' => 'attachments'])->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson('/files', ['file' => $file, 'context' => 'attachments'])->assertForbidden();
});

it('keeps paths and control characters out of the stored name', function () {
    upload(uploader(), UploadedFile::fake()->createWithContent("..\\..\\evil\x07.txt", 'hello'))->assertCreated();

    expect(StoredFile::query()->sole()->original_name)->toBe('evil.txt');
});

describe('security review findings', function () {
    it('refuses images that declare too many pixels before decoding them', function () {
        $wide = imagecreatetruecolor(10_001, 1);
        ob_start();
        imagepng($wide);
        $bytes = (string) ob_get_clean();

        upload(uploader(), UploadedFile::fake()->createWithContent('wide.png', $bytes), 'images')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    });

    it('stops uploads beyond the daily quota of a user and the quota of the tenant', function (string $quota) {
        config([$quota => 0]);

        upload(uploader(), UploadedFile::fake()->createWithContent('a.txt', 'hello'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    })->with(['user per day' => ['uploads.quota_user_daily_mb'], 'tenant' => ['uploads.quota_tenant_mb']]);

    it('refuses a valid link to a file the user may not read', function () {
        $owner = uploader();
        upload($owner, UploadedFile::fake()->createWithContent('a.txt', 'hello'))->assertCreated();
        $other = uploader();

        // Correctly signed for the other user, who is not allowed to read it.
        $url = app(FileStore::class)->link(StoredFile::query()->sole(), $other);

        $this->actingAs($other)->get($url)->assertForbidden();
    });

    it('never serves a file of another tenant', function () {
        $theirs = app(TenantContext::class)->run(Tenant::factory()->create(), fn () => StoredFile::query()->create([
            'disk' => 'local', 'storage_key' => 'tenants/x/'.Str::ulid(), 'original_name' => 'a.txt', 'mime_type' => 'text/plain',
            'size_bytes' => 5, 'sha256' => hash('sha256', 'hello'), 'scan_status' => 'skipped', 'context' => 'attachments',
        ]));
        $mine = uploader();

        $url = URL::temporarySignedRoute('files.download', now()->addMinutes(5), ['file' => $theirs->id, 'for' => $mine->id]);

        $this->actingAs($mine)->get($url)->assertNotFound();
    });
    it('removes right-to-left override characters from names', function () {
        upload(uploader(), UploadedFile::fake()->createWithContent("photo\u{202E}gpj.txt", 'hello'))->assertCreated();

        expect(StoredFile::query()->sole()->original_name)->toBe('photogpj.txt');
    });
});
