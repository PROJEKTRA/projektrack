<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import TextField from '@/components/TextField.vue';
import type { AddressData } from '../types';

defineProps<{
    prefix: string;
    errors: Record<string, string | undefined>;
}>();

const address = defineModel<AddressData>({ required: true });
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <TextField
            v-model="address.line1"
            :name="`${prefix}.line1`"
            :label="$t('crm:field.line1')"
            :errors="errors"
            required
        />
        <TextField
            v-model="address.line2"
            :name="`${prefix}.line2`"
            :label="$t('crm:field.line2')"
            :errors="errors"
        />
        <TextField
            v-model="address.postal_code"
            :name="`${prefix}.postal_code`"
            :label="$t('crm:field.postal_code')"
            :errors="errors"
        />
        <TextField
            v-model="address.city"
            :name="`${prefix}.city`"
            :label="$t('crm:field.city')"
            :errors="errors"
            required
        />
        <TextField
            v-model="address.region"
            :name="`${prefix}.region`"
            :label="$t('crm:field.region')"
            :errors="errors"
        />
        <TextField
            v-model="address.country_code"
            :name="`${prefix}.country_code`"
            :label="$t('crm:field.country')"
            :errors="errors"
            :maxlength="2"
            required
        />
    </div>
</template>
