<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { t } from '@/i18n/strings';

const { appearance, updateAppearance } = useAppearance();

const tabs = computed(
    () =>
        [
            {
                value: 'light',
                Icon: Sun,
                label: t('core:settings.appearance.theme_light'),
            },
            {
                value: 'dark',
                Icon: Moon,
                label: t('core:settings.appearance.theme_dark'),
            },
            {
                value: 'system',
                Icon: Monitor,
                label: t('core:settings.appearance.theme_system'),
            },
        ] as const,
);
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-muted p-1">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                appearance === value
                    ? 'bg-card text-card-foreground shadow-xs'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            ]"
            @click="updateAppearance(value)"
        >
            <component :is="Icon" class="-ms-1 h-4 w-4" />
            <span class="ms-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
