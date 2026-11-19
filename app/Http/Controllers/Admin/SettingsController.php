<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\I18n\Formats;
use App\I18n\TenantLanguages;
use App\Modules\ModuleRegistry;
use App\Settings\SettingDefinition;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > Settings (S-0912): one form per component, rendered from the
 * settings schemas of the core and the enabled modules.
 */
class SettingsController extends Controller
{
    public function index(Settings $settings, ModuleRegistry $registry): Response
    {
        $groups = [];

        foreach ($settings->definitions() as $component => $definitions) {
            $groups[] = [
                'component' => $component,
                'title' => $component === 'core'
                    ? __('core:admin_settings.core_title')
                    : __($registry->get($component)->nameString),
                'fields' => array_map(fn (SettingDefinition $definition): array => $this->field($definition, $settings), $definitions),
            ];
        }

        return Inertia::render('admin/Settings', ['groups' => $groups]);
    }

    public function update(Request $request, Settings $settings, string $component): RedirectResponse
    {
        abort_unless(array_key_exists($component, $settings->definitions()), 404);

        /** @var array<string, mixed> $input */
        $input = $request->except(['_token', '_method']);
        $settings->save($component, $input, $this->currentUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:flash.settings_saved')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function field(SettingDefinition $definition, Settings $settings): array
    {
        return [
            'name' => $definition->name,
            'type' => $definition->type,
            'label' => __($definition->label),
            'help' => $definition->help !== null ? __($definition->help) : null,
            'nullable' => $definition->nullable,
            'nullLabel' => $definition->nullLabel !== null ? __($definition->nullLabel) : null,
            'value' => $settings->get($definition->key()),
            'minimum' => $definition->minimum,
            'maximum' => $definition->maximum,
            'maxLength' => $definition->maxLength,
            'options' => $this->options($definition),
        ];
    }

    /**
     * @return list<array{value: string|int|float|bool, label: string}>|null
     */
    private function options(SettingDefinition $definition): ?array
    {
        return match ($definition->options) {
            'languages' => app(TenantLanguages::class)->options(),
            'formats' => array_map(
                fn (array $format): array => ['value' => $format['value'], 'label' => __('core:settings.profile.format_option', ['name' => $format['label'], 'example' => $format['example']])],
                app(Formats::class)->options(App::getLocale()),
            ),
            'timezones' => app(Formats::class)->timezones(),
            default => $definition->enum === null ? null : array_map(
                fn (string|int|float|bool $value, int $index): array => [
                    'value' => $value,
                    'label' => isset($definition->enumLabels[$index]) ? __($definition->enumLabels[$index]) : (string) $value,
                ],
                $definition->enum,
                array_keys($definition->enum),
            ),
        };
    }
}
