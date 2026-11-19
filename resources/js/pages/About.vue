<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { t } from '@/i18n/strings';

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: t('core:about.title'), href: '/about' }],
    }),
});

defineProps<{
    product: string;
    version: string;
    poweredBy: { product: string; licensor: string } | null;
    licensor: Record<string, string>;
}>();

const fields = [
    { key: 'name', label: 'core:about.licensor_name' },
    { key: 'street', label: 'core:about.licensor_street' },
    { key: 'postal_code', label: 'core:about.licensor_postal_code' },
    { key: 'city', label: 'core:about.licensor_city' },
    { key: 'country_code', label: 'core:about.licensor_country' },
    { key: 'jib', label: 'core:about.licensor_jib' },
    { key: 'mbs', label: 'core:about.licensor_mbs' },
    { key: 'email', label: 'core:about.licensor_email' },
    { key: 'website', label: 'core:about.licensor_website' },
] as const;
</script>

<template>
    <Head :title="$t('core:about.title')" />

    <div class="max-w-2xl space-y-8 px-4 py-6">
        <Heading
            :title="product"
            :description="$t('core:about.version', { version })"
        />

        <p v-if="poweredBy" class="text-sm text-muted-foreground">
            {{ $t('core:about.powered_by', poweredBy) }}
        </p>

        <section class="space-y-3">
            <Heading
                variant="small"
                :title="$t('core:about.licensor_title')"
                :description="$t('core:about.licensor_description')"
            />
            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                <template v-for="field in fields" :key="field.key">
                    <template v-if="licensor[field.key]">
                        <dt class="text-muted-foreground">
                            {{ $t(field.label) }}
                        </dt>
                        <dd data-test="licensor-field">
                            <bdi>{{ licensor[field.key] }}</bdi>
                        </dd>
                    </template>
                </template>
            </dl>
        </section>
    </div>
</template>
