<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatDateTime } from '@/i18n/formats';
import { t } from '@/i18n/strings';

type Token = {
    id: string;
    name: string;
    scopes: string[];
    lastUsedAt: string | null;
    expiresAt: string;
    expired: boolean;
};

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:api_tokens.title'),
                href: ApiTokenController.index(),
            },
        ],
    }),
});

const props = defineProps<{
    tokens: Token[];
    scopes: { value: string; label: string }[];
    defaultDays: number;
    maxDays: number;
}>();

const page = usePage();
// The secret of a token just created: shown once, never sent again.
const newToken = computed(
    () =>
        (page.flash as Record<string, unknown> | undefined)?.newToken as
            string | undefined,
);

const form = useForm({
    name: '',
    scopes: [] as string[],
    days: props.defaultDays,
});

function create(): void {
    form.post(ApiTokenController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function revoke(token: Token): void {
    router.delete(ApiTokenController.destroy.url(token.id), {
        preserveScroll: true,
    });
}

function copy(): void {
    if (newToken.value) {
        void navigator.clipboard?.writeText(newToken.value);
    }
}
</script>

<template>
    <Head :title="$t('core:api_tokens.title')" />

    <h1 class="sr-only">{{ $t('core:api_tokens.title') }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="$t('core:api_tokens.title')"
            :description="$t('core:api_tokens.description')"
        />

        <div
            v-if="newToken"
            class="space-y-2 rounded-lg border border-primary p-4"
            role="status"
            data-test="new-token"
        >
            <p class="text-sm font-medium">
                {{ $t('core:api_tokens.shown_once') }}
            </p>
            <code
                dir="ltr"
                class="block rounded bg-muted p-2 text-xs break-all"
                >{{ newToken }}</code
            >
            <Button type="button" variant="outline" size="sm" @click="copy">{{
                $t('core:api_tokens.copy')
            }}</Button>
        </div>

        <form class="space-y-4" @submit.prevent="create">
            <div class="grid gap-2">
                <Label for="token-name">{{ $t('core:api_tokens.name') }}</Label>
                <Input id="token-name" v-model="form.name" maxlength="80" />
                <InputError :message="form.errors.name" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">
                    {{ $t('core:api_tokens.scopes_label') }}
                </legend>
                <label
                    v-for="scope in scopes"
                    :key="scope.value"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="form.scopes"
                        type="checkbox"
                        :value="scope.value"
                        class="size-4 rounded border-input"
                    />
                    <span>{{ scope.label }}</span>
                    <code dir="ltr" class="text-xs text-muted-foreground">{{
                        scope.value
                    }}</code>
                </label>
                <InputError :message="form.errors.scopes" />
            </fieldset>

            <div class="grid gap-2">
                <Label for="token-days">{{ $t('core:api_tokens.days') }}</Label>
                <Input
                    id="token-days"
                    v-model.number="form.days"
                    type="number"
                    min="1"
                    :max="maxDays"
                    class="w-32"
                />
                <InputError :message="form.errors.days" />
            </div>

            <Button
                type="submit"
                :disabled="form.processing"
                data-test="create-token-button"
                >{{ $t('core:api_tokens.create') }}</Button
            >
        </form>

        <ul v-if="tokens.length > 0" class="divide-y rounded-lg border">
            <li
                v-for="token in tokens"
                :key="token.id"
                class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
            >
                <div class="space-y-1">
                    <p class="text-sm font-medium">
                        <bdi>{{ token.name }}</bdi>
                        <Badge v-if="token.expired" variant="secondary">{{
                            $t('core:api_tokens.expired')
                        }}</Badge>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        <bdi>{{ token.scopes.join(', ') }}</bdi>
                        ·
                        {{
                            $t('core:api_tokens.expires', {
                                date: formatDate(token.expiresAt),
                            })
                        }}
                        ·
                        {{
                            token.lastUsedAt
                                ? $t('core:api_tokens.last_used', {
                                      date: formatDateTime(token.lastUsedAt),
                                  })
                                : $t('core:api_tokens.never_used')
                        }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="revoke(token)"
                    >{{ $t('core:api_tokens.revoke') }}</Button
                >
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            {{ $t('core:api_tokens.empty') }}
        </p>
    </div>
</template>
