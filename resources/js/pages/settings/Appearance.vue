<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { t } from '@/i18n/strings';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:settings.appearance.title'),
                href: edit(),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="$t('core:settings.appearance.title')" />

    <h1 class="sr-only">{{ $t('core:settings.appearance.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('core:settings.appearance.title')"
            :description="$t('core:settings.appearance.description')"
        />
        <AppearanceTabs />
    </div>
</template>
