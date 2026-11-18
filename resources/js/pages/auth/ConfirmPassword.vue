<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/i18n/strings';
import { store } from '@/routes/password/confirm';

// Users with two-factor authentication may confirm with a current code (S-0903).
const props = defineProps<{ acceptsCode?: boolean }>();

defineOptions({
    layout: () => ({
        title: t('core:auth.confirm_password.title'),
        description: t('core:auth.confirm_password.description'),
    }),
});
</script>

<template>
    <Head :title="$t('core:auth.confirm_password.title')" />

    <Form
        v-slot="{ errors, processing }"
        v-bind="store.form()"
        reset-on-success
    >
        <div class="space-y-6">
            <p
                v-if="props.acceptsCode"
                class="text-center text-sm text-muted-foreground"
            >
                {{ $t('core:auth.confirm_password.code_description') }}
            </p>

            <div class="grid gap-2">
                <Label html-for="password">{{
                    props.acceptsCode
                        ? $t('core:auth.confirm_password.code_label')
                        : $t('core:auth.confirm_password.password_label')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('core:auth.confirm_password.submit') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
