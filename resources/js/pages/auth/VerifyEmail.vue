<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/i18n/strings';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: () => ({
        title: t('core:auth.verify_email.title'),
        description: t('core:auth.verify_email.description'),
    }),
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="$t('core:auth.verify_email.title')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-success"
    >
        {{ $t('core:auth.verify_email.link_sent') }}
    </div>

    <Form
        v-slot="{ processing }"
        v-bind="send.form()"
        class="space-y-6 text-center"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ $t('core:auth.verify_email.resend') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ $t('core:nav.log_out') }}
        </TextLink>
    </Form>
</template>
