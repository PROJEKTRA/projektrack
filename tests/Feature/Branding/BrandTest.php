<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Branding\Brand;
use App\I18n\Strings;
use App\Models\AuditEntry;
use App\Models\BrandProfile;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TwoFactorWasReset;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/*
 * S-0911: one brand per tenant, the instance brand as fallback, and the
 * licensor stored once.
 */

function brandAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());

    return $admin;
}

function acme(): void
{
    BrandProfile::query()->create(['product_name' => 'Acme Projects', 'primary_color' => '#1d4ed8', 'email_sender_name' => 'Acme Team']);
    app()->forgetScopedInstances();
}

function onePixelPng(): string
{
    $image = imagecreatetruecolor(2, 2);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

it('fills {brand} with the tenant brand and other tenants keep theirs', function () {
    acme();

    expect(app(Strings::class)->get('core:two_factor.reset_mail.salutation', [], 'en'))->toBe('Your Acme Projects team')
        ->and(app(Strings::class)->bundle(['en'])['reserved']['brand'])->toBe('Acme Projects');

    app(TenantContext::class)->run(Tenant::factory()->create(), function () {
        app()->forgetScopedInstances();
        expect(app(Strings::class)->get('core:two_factor.reset_mail.salutation', [], 'en'))
            ->toBe('Your '.config('brand.name').' team');
    });
});

it('changes the string bundle version when the brand changes', function () {
    $before = app(Strings::class)->bundleVersion(['en']);
    acme();

    expect(app(Strings::class)->bundleVersion(['en']))->not->toBe($before);
});

it('shows the brand in the page title, shared props and theme color', function () {
    acme();

    $response = $this->actingAs(User::factory()->create())->get('/dashboard');

    expect($response->getContent())
        ->toMatch('/<title[^>]*>Acme Projects<\/title>/')
        ->toMatch('/<style nonce="[^"]+">:root\{--primary:#1d4ed8;--primary-foreground:#ffffff;/');
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('name', 'Acme Projects')
        ->where('brand.name', 'Acme Projects'));
});

it('lets an admin set name, color and sender, validated and audited', function () {
    $admin = brandAdmin();

    $this->actingAs($admin)->post('/admin/brand', ['product_name' => '<b>Acme</b>', 'primary_color' => 'red;}body{x'])
        ->assertSessionHasErrors(['product_name', 'primary_color']);

    $this->actingAs($admin)->post('/admin/brand', ['product_name' => 'Acme Projects', 'primary_color' => '#fde047', 'email_sender_name' => ''])
        ->assertSessionHasNoErrors();

    app()->forgetScopedInstances();
    $brand = app(Brand::class);
    expect($brand->name())->toBe('Acme Projects')
        // Light color: black text on it.
        ->and($brand->themeCss())->toContain('--primary-foreground:#000000')
        ->and($brand->current()['senderName'])->toBe('Acme Projects')
        ->and(AuditEntry::query()->where('action', 'branding.changed')->latest('seq')->first()?->contextData()['fields'])
        ->toEqualCanonicalizing(['product_name', 'primary_color']);

    // Emptied values fall back to the instance brand.
    $this->actingAs($admin)->post('/admin/brand', ['product_name' => '', 'primary_color' => '']);
    app()->forgetScopedInstances();
    expect(app(Brand::class)->name())->toBe(config('brand.name'))
        ->and(app(Brand::class)->themeCss())->toBeNull();
});

it('serves the logo publicly, only the current one, and refuses SVG', function () {
    Storage::fake('local');
    $admin = brandAdmin();

    $this->actingAs($admin)->post('/admin/brand', ['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')])
        ->assertSessionHasErrors('logo');

    $this->actingAs($admin)->post('/admin/brand', ['logo' => UploadedFile::fake()->createWithContent('logo.png', onePixelPng())])
        ->assertSessionHasNoErrors();
    app()->forgetScopedInstances();
    $url = app(Brand::class)->logoUrl();
    expect($url)->not->toBeNull();

    auth()->logout();
    $logo = $this->get($url)->assertOk();
    expect($logo->headers->get('Content-Type'))->toBe('image/png')
        ->and($logo->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($logo->headers->get('Content-Security-Policy'))->toContain('sandbox');

    // Any other file id, even an existing one, is not served here.
    $this->get('/brand/logo/'.Str::ulid())->assertNotFound();
});

it('is only for users allowed to manage the brand', function () {
    $employee = User::factory()->create();
    app(RoleManager::class)->assign($employee, Role::query()->where('key', 'employee')->firstOrFail());

    $this->actingAs($employee)->get('/admin/brand')->assertForbidden();
    $this->actingAs($employee)->post('/admin/brand', ['product_name' => 'Mine'])->assertForbidden();
});

it('sends e-mail under the brand sender name with the brand in the header', function () {
    acme();
    $sent = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent) {
        $sent = $event->message;
    });

    User::factory()->create()->notify(new TwoFactorWasReset);

    expect($sent)->toBeInstanceOf(Email::class);
    /** @var Email $sent */
    $from = $sent->getFrom()[0];
    expect($from)->toBeInstanceOf(Address::class)
        ->and($from->getName())->toBe('Acme Team')
        ->and((string) $sent->getHtmlBody())->toContain('Acme Projects')
        ->not->toContain('>'.config('app.name').'<');
});

it('shows the licensor once, from one place, on the About screen', function () {
    config(['licensor.jib' => null]);
    $this->get('/about')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get('/about')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('About')
            ->where('licensor.name', config('licensor.name'))
            ->missing('licensor.jib')
            ->where('poweredBy.licensor', config('licensor.short_name')));
});

describe('security review findings', function () {
    it('never shows the shipped product name before sign-in', function () {
        $response = $this->get('/login')->assertOk();
        $html = (string) $response->getContent();

        expect($html)->not->toContain((string) config('brand.name'));
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('name', '')->where('brand.name', ''));

        $bundle = app(Strings::class)->bundle(['bs', 'en']);
        expect($bundle['reserved']['brand'])->toBe('');

        // A tenant's own name may be shown, and the signed-in user sees the full brand.
        acme();
        expect((string) $this->get('/login')->getContent())->toContain('Acme Projects');
    });

    it('refuses Markdown, markup and control characters in names that go into e-mails', function (array $input) {
        $this->actingAs(brandAdmin())->post('/admin/brand', $input)->assertSessionHasErrors(array_keys($input)[0]);
        expect(BrandProfile::query()->count())->toBe(0);
    })->with([
        'link in product name' => [['product_name' => '[Verify account](https://evil.example)']],
        'emphasis' => [['product_name' => 'Acme *urgent*']],
        'right-to-left override' => [['product_name' => "Acme\u{202E}lapyap"]],
        'newline in sender' => [['email_sender_name' => "Acme\r\nBcc: x@example.test"]],
        'address in sender' => [['email_sender_name' => 'security@bank.example']],
        // Input is trimmed, so a trailing newline is harmless; CSS after the color is not.
        'CSS after the color' => [['primary_color' => '#aabbcc;}body{display:none']],
    ]);

    it('accepts upper-case colors and stores them lower-case', function () {
        $this->actingAs(brandAdmin())->post('/admin/brand', ['primary_color' => '#AABBCC'])->assertSessionHasNoErrors();

        expect(BrandProfile::query()->value('primary_color'))->toBe('#aabbcc');
    });

    it('ignores a broken color that reached the database another way', function () {
        DB::table('brand_profiles')->insert(['id' => (string) Str::ulid(), 'tenant_id' => $this->tenant->id, 'primary_color' => '#zz}{x;', 'show_powered_by' => true]);
        app()->forgetScopedInstances();

        expect(app(Brand::class)->themeCss())->toBeNull();
    });

    it('deletes a replaced logo and serves no other file, with a generic name', function () {
        Storage::fake('local');
        $admin = brandAdmin();
        $this->actingAs($admin)->post('/admin/brand', ['logo' => UploadedFile::fake()->createWithContent('first-logo.png', onePixelPng())]);
        app()->forgetScopedInstances();
        $first = app(Brand::class)->current()['logoFileId'];
        $firstKey = StoredFile::query()->findOrFail($first)->storage_key;

        $this->actingAs($admin)->post('/admin/brand', ['logo' => UploadedFile::fake()->createWithContent('second.png', onePixelPng())]);
        app()->forgetScopedInstances();
        $second = app(Brand::class)->current()['logoFileId'];

        expect(StoredFile::query()->find($first))->toBeNull()
            ->and(Storage::disk('local')->exists($firstKey))->toBeFalse();

        auth()->logout();
        $this->get("/brand/logo/{$first}")->assertNotFound();
        $logo = $this->get("/brand/logo/{$second}")->assertOk();
        expect($logo->headers->get('Content-Disposition'))->not->toContain('second')
            ->and($logo->headers->get('Cache-Control'))->toContain('max-age=86400');

        // Another existing file of the tenant is never served as a logo.
        $other = StoredFile::query()->create([
            'disk' => 'local', 'storage_key' => 'tenants/'.$this->tenant->id.'/x/'.Str::ulid(), 'original_name' => 'secret.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('0', 64), 'scan_status' => 'skipped', 'context' => 'attachments',
        ]);
        $this->get("/brand/logo/{$other->id}")->assertNotFound();

        // Removing the logo deletes it too.
        $this->actingAs($admin)->post('/admin/brand', ['remove_logo' => true]);
        expect(StoredFile::query()->find($second))->toBeNull();
    });

    it('records no audit entry when nothing changed', function () {
        $admin = brandAdmin();
        $this->actingAs($admin)->post('/admin/brand', ['product_name' => 'Acme Projects']);
        $count = AuditEntry::query()->where('action', 'branding.changed')->count();

        $this->actingAs($admin)->post('/admin/brand', ['product_name' => 'Acme Projects']);

        expect(AuditEntry::query()->where('action', 'branding.changed')->count())->toBe($count);
    });
});
