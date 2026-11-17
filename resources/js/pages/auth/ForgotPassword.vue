<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/i18n/strings';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: () => ({
        title: t('core:auth.forgot_password.title'),
        description: t('core:auth.forgot_password.description'),
    }),
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="$t('core:auth.forgot_password.title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-slot="{ errors, processing }" v-bind="email.form()">
            <div class="grid gap-2">
                <Label for="email">{{
                    $t('core:auth.forgot_password.email_label')
                }}</Label>
                <Input
                    id="email"
                    v-focus
                    type="email"
                    name="email"
                    autocomplete="off"
                    :placeholder="
                        $t('core:auth.forgot_password.email_placeholder')
                    "
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('core:auth.forgot_password.submit') }}
                </Button>
            </div>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            <TextLink :href="login()">{{
                $t('core:auth.forgot_password.back_to_login')
            }}</TextLink>
        </div>
    </div>
</template>
