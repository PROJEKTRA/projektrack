<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/i18n/strings';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    // A function, so the layout re-reads the strings when the language changes.
    layout: () => ({
        title: t('core:auth.login.title'),
        description: t('core:auth.login.description'),
    }),
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head :title="$t('core:auth.login.page_title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-slot="{ errors, processing }"
        v-bind="store.form()"
        :reset-on-success="['password']"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{
                    $t('core:auth.login.email_label')
                }}</Label>
                <Input
                    id="email"
                    v-focus
                    type="email"
                    name="email"
                    required
                    :tabindex="1"
                    autocomplete="email"
                    :placeholder="$t('core:auth.login.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{
                        $t('core:auth.login.password_label')
                    }}</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        {{ $t('core:auth.login.forgot_password') }}
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    :placeholder="$t('core:auth.login.password_placeholder')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>{{ $t('core:auth.login.remember') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ $t('core:auth.login.submit') }}
            </Button>
        </div>
    </Form>
</template>
