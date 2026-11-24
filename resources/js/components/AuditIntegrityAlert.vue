<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { KeyRound, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';

// The server sends the (translated) warning only to people who may read the audit log.
const page = usePage();
const license = computed<string | null>(
    () => page.props.licenseWarning ?? null,
);
const warning = computed<string | null>(() => page.props.auditWarning ?? null);
</script>

<template>
    <div
        v-if="warning"
        role="alert"
        class="m-4 flex items-start gap-3 rounded-md border border-destructive/50 p-3 text-sm text-destructive"
        data-test="audit-integrity-warning"
    >
        <ShieldAlert class="mt-0.5 size-4 shrink-0" />
        <p>{{ warning }}</p>
    </div>
    <div
        v-if="license"
        role="status"
        class="m-4 flex items-start gap-3 rounded-md border p-3 text-sm"
        data-test="license-warning"
    >
        <KeyRound class="mt-0.5 size-4 shrink-0" />
        <p>{{ license }}</p>
    </div>
</template>
