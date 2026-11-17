<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/i18n/strings';
import { edit } from '@/routes/security';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManageTwoFactorProps;

const props = defineProps<Props>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:settings.security.title'),
                href: edit(),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="$t('core:settings.security.title')" />

    <h1 class="sr-only">{{ $t('core:settings.security.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('core:settings.security.password.title')"
            :description="$t('core:settings.security.password.description')"
        />

        <Form
            v-slot="{ errors, processing }"
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
        >
            <div class="grid gap-2">
                <Label for="current_password">{{
                    $t('core:settings.security.password.current_label')
                }}</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    :placeholder="
                        $t(
                            'core:settings.security.password.current_placeholder',
                        )
                    "
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{
                    $t('core:settings.security.password.new_label')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="
                        $t('core:settings.security.password.new_placeholder')
                    "
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    $t('core:settings.security.password.confirmation_label')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="
                        $t(
                            'core:settings.security.password.confirmation_placeholder',
                        )
                    "
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    {{ $t('core:common.save') }}
                </Button>
            </div>
        </Form>
    </div>

    <ManageTwoFactor
        :can-manage-two-factor="canManageTwoFactor"
        :requires-confirmation="requiresConfirmation"
        :two-factor-enabled="twoFactorEnabled"
        :two-factor-required="twoFactorRequired"
        :two-factor-notices="twoFactorNotices"
    />
</template>
