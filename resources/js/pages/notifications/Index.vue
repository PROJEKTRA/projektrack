<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/i18n/formats';
import { t } from '@/i18n/strings';

type Item = {
    id: string;
    type: string;
    message: string;
    url: string | null;
    read: boolean;
    createdAt: string | null;
};

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:notifications.title'),
                href: NotificationController.index(),
            },
        ],
    }),
});

defineProps<{ notifications: Item[] }>();

function open(item: Item): void {
    router.post(NotificationController.open.url(item.id));
}

function readAll(): void {
    router.post(
        NotificationController.readAll.url(),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="$t('core:notifications.title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('core:notifications.title')"
                :description="$t('core:notifications.description')"
            />
            <Button
                v-if="notifications.some((item) => !item.read)"
                variant="outline"
                data-test="read-all-button"
                @click="readAll"
                >{{ $t('core:notifications.mark_all_read') }}</Button
            >
        </div>

        <p
            v-if="notifications.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ $t('core:notifications.empty') }}
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="item in notifications"
                :key="item.id"
                :data-test="`notification-${item.id}`"
            >
                <button
                    type="button"
                    class="flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-muted/50"
                    @click="open(item)"
                >
                    <span class="flex-1 space-y-1">
                        <span
                            class="block text-sm"
                            :class="{ 'font-medium': !item.read }"
                            >{{ item.message }}</span
                        >
                        <span
                            v-if="item.createdAt"
                            class="block text-xs text-muted-foreground tabular-nums"
                            >{{ formatDateTime(item.createdAt) }}</span
                        >
                    </span>
                    <Badge v-if="!item.read" variant="secondary">{{
                        $t('core:notifications.unread')
                    }}</Badge>
                </button>
            </li>
        </ul>
    </div>
</template>
