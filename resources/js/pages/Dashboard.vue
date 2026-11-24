<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, Circle } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n/strings';
import { dashboard } from '@/routes';

defineProps<{
    cards: { label: string; value: string; href: string; alert?: boolean }[];
    steps: { label: string; href: string; done: boolean }[];
}>();

// Only a convenience of this browser: the list is hidden, not finished.
const KEY = 'getting-started-hidden';
const hidden = ref(false);

try {
    hidden.value = localStorage.getItem(KEY) === '1';
} catch {
    // Storage is blocked: the list just stays visible.
}

function setHidden(value: boolean): void {
    hidden.value = value;

    try {
        localStorage.setItem(KEY, value ? '1' : '0');
    } catch {
        // See above.
    }
}

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

    <div class="space-y-6 px-4 py-6">
        <section
            v-if="steps.length && !hidden"
            class="rounded-xl border bg-card p-4"
            data-test="steps"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-medium">{{ $t('core:dashboard.steps') }}</h2>
                <span class="text-sm text-muted-foreground">{{
                    $t('core:dashboard.steps_done', {
                        done: steps.filter((s) => s.done).length,
                        all: steps.length,
                    })
                }}</span>
                <Button size="sm" variant="ghost" @click="setHidden(true)">{{
                    $t('core:dashboard.hide')
                }}</Button>
            </div>
            <ol class="mt-2 space-y-1">
                <li v-for="step in steps" :key="step.label">
                    <Link
                        :href="step.href"
                        class="flex items-center gap-2 rounded-md p-1 text-sm hover:bg-accent"
                    >
                        <component
                            :is="step.done ? CircleCheck : Circle"
                            class="size-4 shrink-0"
                            :class="
                                step.done
                                    ? 'text-primary'
                                    : 'text-muted-foreground'
                            "
                        />
                        <span
                            :class="step.done ? 'text-muted-foreground' : ''"
                            >{{ $t(step.label) }}</span
                        >
                        <span class="sr-only">{{
                            step.done
                                ? $t('core:dashboard.done')
                                : $t('core:dashboard.open')
                        }}</span>
                    </Link>
                </li>
            </ol>
        </section>
        <Button
            v-else-if="steps.length"
            size="sm"
            variant="ghost"
            @click="setHidden(false)"
            >{{ $t('core:dashboard.show') }}</Button
        >
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
