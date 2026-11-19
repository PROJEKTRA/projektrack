<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers;

use App\Files\FileStore;
use App\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload a file and download it through a signed link (S-0909). Later
 * stories attach files to their records; this is the shared entry point.
 */
class FileController extends Controller
{
    public function store(Request $request, FileStore $files): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'context' => ['required', 'string', Rule::in(array_keys(config()->array('uploads.contexts')))],
        ]);

        $user = $this->currentUser($request);
        $upload = $request->file('file');
        abort_unless($upload instanceof UploadedFile, 422);

        $file = $files->store($upload, $request->string('context')->toString(), $user);

        return response()->json([
            'id' => $file->id,
            'name' => $file->original_name,
            'size' => $file->size_bytes,
            'url' => $files->link($file, $user),
        ], 201);
    }

    /** Signed, expiring and bound to the user it was made for. */
    public function download(Request $request, FileStore $files, string $file): StreamedResponse
    {
        $user = $this->currentUser($request);
        abort_unless($request->query('for') === $user->id, 403);

        $stored = StoredFile::query()->findOrFail($file);
        abort_unless($files->canRead($stored, $user), 403);

        return $files->download($stored, $request->boolean('inline'));
    }
}
