<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/i18n/strings';
import { update } from '@/routes/password';

defineOptions({
    layout: () => ({
        title: t('core:auth.reset_password.title'),
        description: t('core:auth.reset_password.description'),
    }),
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head :title="$t('core:auth.reset_password.title')" />

    <Form
        v-slot="{ errors, processing }"
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{
                    $t('core:auth.reset_password.email_label')
                }}</Label>
                <Input
                    id="email"
                    v-model="inputEmail"
                    type="email"
                    name="email"
                    autocomplete="email"
                    class="mt-1 block w-full"
                    readonly
                />
                <InputError :message="errors.email" class="mt-2" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{
                    $t('core:auth.reset_password.password_label')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    autofocus
                    :placeholder="
                        $t('core:auth.reset_password.password_placeholder')
                    "
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    $t('core:auth.reset_password.password_confirmation_label')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    :placeholder="
                        $t(
                            'core:auth.reset_password.password_confirmation_placeholder',
                        )
                    "
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                {{ $t('core:auth.reset_password.submit') }}
            </Button>
        </div>
    </Form>
</template>
