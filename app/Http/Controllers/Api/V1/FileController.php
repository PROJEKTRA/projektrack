<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Api\V1;

use App\Api\ApiOperation;
use App\Files\FileStore;
use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload and download files through the API (S-0913) with the same checks
 * as the UI (S-0909): type from the bytes, limits, quotas, read rules.
 */
class FileController extends Controller
{
    #[ApiOperation(
        summary: 'Upload a file (multipart). The type is detected from the content and must be allowed in the context.',
        scope: 'core:write',
        permission: 'core.files.upload',
        status: 201,
        request: [
            'type' => 'object',
            'required' => ['file', 'context'],
            'properties' => [
                'file' => ['type' => 'string', 'contentMediaType' => 'application/octet-stream'],
                'context' => ['type' => 'string', 'enum' => ['attachments', 'images']],
            ],
        ],
        requestType: 'multipart/form-data',
        response: [
            'type' => 'object',
            'required' => ['id', 'name', 'size', 'mime_type'],
            'properties' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'size' => ['type' => 'integer'],
                'mime_type' => ['type' => 'string'],
            ],
        ],
    )]
    public function store(Request $request, FileStore $files): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'context' => ['required', 'string', Rule::in(['attachments', 'images'])],
        ]);

        $upload = $request->file('file');
        abort_unless($upload instanceof UploadedFile, 422);

        $file = $files->store($upload, $request->string('context')->toString(), $this->currentUser($request));

        return response()->json([
            'id' => $file->id,
            'name' => $file->original_name,
            'size' => $file->size_bytes,
            'mime_type' => $file->mime_type,
        ], 201);
    }

    #[ApiOperation(
        summary: 'Download a file the token user may read, as an attachment.',
        scope: 'core:read',
        response: ['type' => 'string', 'contentMediaType' => 'application/octet-stream'],
    )]
    public function show(Request $request, FileStore $files, string $file): StreamedResponse
    {
        $stored = StoredFile::query()->findOrFail($file);
        abort_unless($files->canRead($stored, $this->currentUser($request)), 403);

        return $files->download($stored, inline: false);
    }
}
