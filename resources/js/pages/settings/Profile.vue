<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/i18n/strings';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:settings.profile.title'),
                href: edit(),
            },
        ],
    }),
});

defineProps<{
    /** The user's own language; null means the organization's language. */
    locale: string | null;
    /** Offered languages, each labeled in its own language. */
    locales: { value: string; label: string }[];
    /** Regional format and time zone, separate from the language (S-0919). */
    formatLocale: string | null;
    formats: { value: string; label: string; example: string }[];
    timezone: string | null;
    timezones: { value: string; label: string }[];
    tenantTimezone: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="$t('core:settings.profile.title')" />

    <h1 class="sr-only">{{ $t('core:settings.profile.title') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('core:settings.profile.heading')"
            :description="$t('core:settings.profile.description')"
        />

        <Form
            v-slot="{ errors, processing }"
            v-bind="ProfileController.update.form()"
            class="space-y-6"
        >
            <div class="grid gap-2">
                <Label for="name">{{
                    $t('core:settings.profile.name_label')
                }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="$t('core:settings.profile.name_placeholder')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{
                    $t('core:settings.profile.email_label')
                }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :placeholder="$t('core:settings.profile.email_placeholder')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ $t('core:settings.profile.email_unverified') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-muted-foreground underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                    >
                        {{ $t('core:settings.profile.resend_verification') }}
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-success"
                >
                    {{ $t('core:settings.profile.verification_link_sent') }}
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{
                    $t('core:settings.profile.locale_label')
                }}</Label>
                <select
                    id="locale"
                    name="locale"
                    class="mt-1 block h-9 w-full rounded-input border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                    aria-describedby="locale-help"
                >
                    <option value="" :selected="locale === null">
                        {{ $t('core:settings.profile.locale_default') }}
                    </option>
                    <option
                        v-for="option in locales"
                        :key="option.value"
                        :value="option.value"
                        :lang="option.value"
                        :selected="locale === option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <p id="locale-help" class="text-sm text-muted-foreground">
                    {{ $t('core:settings.profile.locale_help') }}
                </p>
                <InputError class="mt-2" :message="errors.locale" />
            </div>

            <div class="grid gap-2">
                <Label for="format_locale">{{
                    $t('core:settings.profile.format_label')
                }}</Label>
                <select
                    id="format_locale"
                    name="format_locale"
                    class="mt-1 block h-9 w-full rounded-input border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                    aria-describedby="format-help"
                >
                    <option value="" :selected="formatLocale === null">
                        {{ $t('core:settings.profile.format_default') }}
                    </option>
                    <option
                        v-for="option in formats"
                        :key="option.value"
                        :value="option.value"
                        :selected="formatLocale === option.value"
                    >
                        {{
                            $t('core:settings.profile.format_option', {
                                name: option.label,
                                example: option.example,
                            })
                        }}
                    </option>
                </select>
                <p id="format-help" class="text-sm text-muted-foreground">
                    {{ $t('core:settings.profile.format_help') }}
                </p>
                <InputError class="mt-2" :message="errors.format_locale" />
            </div>

            <div class="grid gap-2">
                <Label for="timezone">{{
                    $t('core:settings.profile.timezone_label')
                }}</Label>
                <select
                    id="timezone"
                    name="timezone"
                    class="mt-1 block h-9 w-full rounded-input border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                    aria-describedby="timezone-help"
                >
                    <option value="" :selected="timezone === null">
                        {{
                            $t('core:settings.profile.timezone_default', {
                                timezone: tenantTimezone,
                            })
                        }}
                    </option>
                    <option
                        v-for="option in timezones"
                        :key="option.value"
                        :value="option.value"
                        :selected="timezone === option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <p id="timezone-help" class="text-sm text-muted-foreground">
                    {{ $t('core:settings.profile.timezone_help') }}
                </p>
                <InputError class="mt-2" :message="errors.timezone" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                    >{{ $t('core:common.save') }}</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
