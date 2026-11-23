<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/i18n/strings';

/** One setting as declared in a settings.schema.json (App\Http\Controllers\Admin\SettingsController). */
type Value = string | number | boolean | null;
type Field = {
    name: string;
    type: 'string' | 'integer' | 'number' | 'boolean';
    label: string;
    help: string | null;
    nullable: boolean;
    nullLabel: string | null;
    value: Value;
    /** Stored encrypted; the form only learns whether one is set (S-0909). */
    secret: boolean;
    isSet: boolean;
    minimum: number | null;
    maximum: number | null;
    maxLength: number | null;
    options: { value: string | number | boolean; label: string }[] | null;
};
type Group = { component: string; title: string; fields: Field[] };

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:admin_settings.title'),
                href: SettingsController.index(),
            },
        ],
    }),
});

const props = defineProps<{ groups: Group[] }>();

// One form per component, so saving one never sends another's values.
const forms = Object.fromEntries(
    props.groups.map((group) => [
        group.component,
        useForm(
            Object.fromEntries(
                group.fields.map((field) => [field.name, field.value ?? '']),
            ) as Record<string, Value>,
        ),
    ]),
);

function save(component: string): void {
    forms[component].put(SettingsController.update.url(component), {
        preserveScroll: true,
    });
}

const inputClass =
    'mt-1 block h-9 w-full rounded-input border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head :title="$t('core:admin_settings.title')" />

    <div class="space-y-10 px-4 py-6">
        <Heading
            :title="$t('core:admin_settings.title')"
            :description="$t('core:admin_settings.description')"
        />

        <form
            v-for="group in groups"
            :key="group.component"
            class="max-w-2xl space-y-6"
            :data-test="`settings-${group.component}`"
            @submit.prevent="save(group.component)"
        >
            <Heading variant="small" :title="group.title" />

            <div
                v-for="field in group.fields"
                :key="field.name"
                class="grid gap-2"
            >
                <template v-if="field.type === 'boolean'">
                    <div class="flex items-center gap-3">
                        <input
                            :id="`${group.component}-${field.name}`"
                            v-model="forms[group.component][field.name]"
                            type="checkbox"
                            class="size-4 rounded border-input"
                            :aria-describedby="
                                field.help
                                    ? `${group.component}-${field.name}-help`
                                    : undefined
                            "
                        />
                        <Label :for="`${group.component}-${field.name}`">{{
                            field.label
                        }}</Label>
                    </div>
                </template>
                <template v-else>
                    <Label :for="`${group.component}-${field.name}`">{{
                        field.label
                    }}</Label>
                    <select
                        v-if="field.options"
                        :id="`${group.component}-${field.name}`"
                        v-model="forms[group.component][field.name]"
                        :class="inputClass"
                        :aria-describedby="
                            field.help
                                ? `${group.component}-${field.name}-help`
                                : undefined
                        "
                    >
                        <option v-if="field.nullable" value="">
                            {{ field.nullLabel }}
                        </option>
                        <option
                            v-for="option in field.options"
                            :key="String(option.value)"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <Input
                        v-else
                        :id="`${group.component}-${field.name}`"
                        v-model="
                            forms[group.component][field.name] as
                                string | number
                        "
                        :type="
                            field.secret
                                ? 'password'
                                : field.type === 'string'
                                  ? 'text'
                                  : 'number'
                        "
                        :autocomplete="
                            field.secret ? 'new-password' : undefined
                        "
                        :placeholder="
                            field.secret
                                ? $t(
                                      field.isSet
                                          ? 'core:admin_settings.secret_set'
                                          : 'core:admin_settings.secret_unset',
                                  )
                                : undefined
                        "
                        :min="field.minimum ?? undefined"
                        :max="field.maximum ?? undefined"
                        :maxlength="field.maxLength ?? undefined"
                        :aria-describedby="
                            field.help
                                ? `${group.component}-${field.name}-help`
                                : undefined
                        "
                    />
                </template>
                <p
                    v-if="field.help"
                    :id="`${group.component}-${field.name}-help`"
                    class="text-sm text-muted-foreground"
                >
                    {{ field.help }}
                </p>
                <InputError
                    :message="forms[group.component].errors[field.name]"
                />
            </div>

            <Button
                type="submit"
                :disabled="forms[group.component].processing"
                :data-test="`save-settings-${group.component}`"
                >{{ $t('core:common.save') }}</Button
            >
        </form>
    </div>
</template>
