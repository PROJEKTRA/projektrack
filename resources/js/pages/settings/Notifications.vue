<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import NotificationSettingsController from '@/actions/App/Http/Controllers/Settings/NotificationSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/i18n/strings';

type Type = { type: string; label: string; mandatory: boolean; mail: boolean };

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:notifications.settings.title'),
                href: NotificationSettingsController.edit(),
            },
        ],
    }),
});

const props = defineProps<{ types: Type[]; emailEnabled: boolean }>();

const form = useForm({
    mail: Object.fromEntries(
        props.types.map((type) => [type.type, type.mail]),
    ) as Record<string, boolean>,
});

function save(): void {
    form.put(NotificationSettingsController.update.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="$t('core:notifications.settings.title')" />

    <h1 class="sr-only">{{ $t('core:notifications.settings.title') }}</h1>

    <form class="space-y-6" @submit.prevent="save">
        <Heading
            variant="small"
            :title="$t('core:notifications.settings.title')"
            :description="$t('core:notifications.settings.description')"
        />

        <p v-if="!emailEnabled" class="text-sm text-muted-foreground">
            {{ $t('core:notifications.settings.organization_off') }}
        </p>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="type in types"
                :key="type.type"
                class="flex items-center justify-between gap-4 px-4 py-3"
            >
                <div class="space-y-1">
                    <Label :for="`mail-${type.type}`">{{ type.label }}</Label>
                    <p
                        v-if="type.mandatory"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('core:notifications.settings.mandatory') }}
                    </p>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        :id="`mail-${type.type}`"
                        v-model="form.mail[type.type]"
                        type="checkbox"
                        class="size-4 rounded border-input"
                        :disabled="type.mandatory || !emailEnabled"
                        :data-test="`mail-${type.type}`"
                    />
                    {{ $t('core:notifications.settings.email_label') }}
                </label>
            </li>
        </ul>

        <Button type="submit" :disabled="form.processing">{{
            $t('core:common.save')
        }}</Button>
    </form>
</template>
