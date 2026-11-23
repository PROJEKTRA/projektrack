<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { t } from '@/i18n/strings';
import { dashboard } from '@/routes';

defineProps<{
    cards: { label: string; value: string; href: string; alert?: boolean }[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:nav.dashboard'),
                href: dashboard(),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="$t('core:nav.dashboard')" />

    <div class="px-4 py-6">
        <p v-if="cards.length === 0" class="text-muted-foreground">
            {{ $t('core:dashboard.empty') }}
        </p>
        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <li v-for="card in cards" :key="card.label">
                <Link
                    :href="card.href"
                    class="block rounded-xl border bg-card p-4 hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-hidden"
                >
                    <span class="block text-sm text-muted-foreground">{{
                        $t(card.label)
                    }}</span>
                    <bdi
                        class="mt-1 block text-2xl font-semibold tabular-nums"
                        :class="card.alert ? 'text-destructive' : ''"
                        >{{ card.value }}</bdi
                    >
                </Link>
            </li>
        </ul>
    </div>
</template>
