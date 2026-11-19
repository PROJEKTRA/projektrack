<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Api\V1;

use App\Api\ApiOperation;
use App\Http\Controllers\Controller;
use App\Settings\SettingDefinition;
use App\Settings\Settings;
use Illuminate\Http\JsonResponse;

/**
 * Settings of one component (S-0913, S-0912). Secrets are never returned.
 */
class SettingsController extends Controller
{
    #[ApiOperation(
        summary: 'The settings of one component (core or a module); secret settings only say whether they are set.',
        scope: 'core:admin',
        permission: 'core.settings.manage',
        response: [
            'type' => 'object',
            'required' => ['component', 'values'],
            'properties' => [
                'component' => ['type' => 'string'],
                'values' => ['type' => 'object', 'additionalProperties' => true],
            ],
        ],
    )]
    public function show(Settings $settings, string $component): JsonResponse
    {
        abort_unless(array_key_exists($component, $settings->definitions()), 404);

        $values = [];
        foreach ($settings->definitions()[$component] as $definition) {
            /** @var SettingDefinition $definition */
            $values[$definition->name] = $definition->secret
                ? ['set' => $settings->isSet($definition->key())]
                : $settings->get($definition->key());
        }

        return response()->json(['component' => $component, 'values' => $values]);
    }
}
