<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { t } from '@/i18n/strings';

// Shown to link previews and mail scanners instead of the shared object (S-0987).
defineOptions({
    layout: () => ({
        title: t('core:shared_links.hidden.title'),
        description: t('core:shared_links.hidden.description'),
    }),
});
</script>

<template>
    <Head :title="$t('core:shared_links.hidden.title')" />
</template>
