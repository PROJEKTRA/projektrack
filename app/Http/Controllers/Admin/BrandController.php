<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Admin;

use App\Branding\Brand;
use App\Files\FileStore;
use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > Brand (S-0911): product name, logo, primary color and e-mail
 * sender name of the tenant. Empty values use the instance brand.
 */
class BrandController extends Controller
{
    public function edit(Brand $brand): Response
    {
        $profile = $brand->profile();

        return Inertia::render('admin/Brand', [
            'profile' => [
                'product_name' => $profile?->product_name,
                'primary_color' => $profile?->primary_color,
                'email_sender_name' => $profile?->email_sender_name,
            ],
            'instanceName' => config()->string('brand.name'),
            'logoUrl' => $brand->logoUrl(),
        ]);
    }

    public function update(Request $request, Brand $brand, FileStore $files): RedirectResponse
    {
        /** @var array{product_name?: string|null, primary_color?: string|null, email_sender_name?: string|null} $valid */
        $valid = $request->validate([
            // No markup, Markdown or control and direction characters: names go into e-mails.
            'product_name' => ['nullable', 'string', 'max:60', 'not_regex:/[<>{}\[\]()*_`\\\\#|~!]|\p{C}/u'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}\z/'],
            'email_sender_name' => ['nullable', 'string', 'max:80', 'not_regex:/[<>{}\[\]()*_`\\\\#|~!"@]|\p{C}/u'],
            'logo' => ['nullable', 'file'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $user = $this->currentUser($request);
        $values = [
            'product_name' => is_string($valid['product_name'] ?? null) ? trim($valid['product_name']) : null,
            'primary_color' => isset($valid['primary_color']) ? strtolower($valid['primary_color']) : null,
            'email_sender_name' => is_string($valid['email_sender_name'] ?? null) ? trim($valid['email_sender_name']) : null,
        ];

        $logo = $request->file('logo');
        if ($logo instanceof UploadedFile) {
            $values['logo_file_id'] = $files->store($logo, 'logos', $user, 'logo')->id;
        } elseif ($request->boolean('remove_logo')) {
            $values['logo_file_id'] = null;
        }

        $previous = $brand->profile()?->logo_file_id;
        $brand->save($values, $user);

        // A replaced or removed logo is deleted, content and record.
        if ($previous !== null && array_key_exists('logo_file_id', $values) && $values['logo_file_id'] !== $previous) {
            $old = StoredFile::query()->find($previous);
            if ($old !== null) {
                $files->delete($old, $user);
            }
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:flash.brand_saved')]);

        return back();
    }
}
