<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import DoctorController from '@/actions/App/Http/Controllers/Admin/DoctorController';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

// Unread notifications (S-0912), refreshed with every visit.
const unread = computed(() => Number(usePage().props.unreadNotifications ?? 0));
// Critical findings of the system check, for administrators (S-0988).
const doctorAlerts = computed(() => Number(usePage().props.doctorAlerts ?? 0));

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ms-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <Link
            :href="NotificationController.index()"
            class="relative ms-auto inline-flex size-9 items-center justify-center rounded-md hover:bg-accent"
            :aria-label="$t('core:notifications.bell', { count: unread })"
            data-test="notification-bell"
        >
            <Bell class="size-5" />
            <span
                v-if="unread > 0"
                class="absolute -end-0.5 -top-0.5 min-w-4 rounded-full bg-primary px-1 text-center text-xs leading-4 text-primary-foreground tabular-nums"
                aria-hidden="true"
                >{{ unread > 99 ? '99+' : unread }}</span
            >
        </Link>
    </header>
    <div
        v-if="doctorAlerts > 0"
        role="alert"
        class="flex flex-wrap items-center gap-2 border-b border-destructive/30 bg-destructive/10 px-6 py-2 text-sm"
        data-test="doctor-banner"
    >
        <span>{{
            $t('core:doctor.page.banner', { count: doctorAlerts })
        }}</span>
        <Link :href="DoctorController.index()" class="font-medium underline">{{
            $t('core:doctor.page.banner_link')
        }}</Link>
    </div>
</template>
