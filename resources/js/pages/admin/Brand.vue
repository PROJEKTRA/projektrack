<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/i18n/strings';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:brand.title'),
                href: BrandController.edit(),
            },
        ],
    }),
});

const props = defineProps<{
    profile: {
        product_name: string | null;
        primary_color: string | null;
        email_sender_name: string | null;
    };
    instanceName: string;
    logoUrl: string | null;
}>();

const form = useForm<{
    product_name: string;
    primary_color: string;
    email_sender_name: string;
    logo: File | null;
    remove_logo: boolean;
}>({
    product_name: props.profile.product_name ?? '',
    primary_color: props.profile.primary_color ?? '',
    email_sender_name: props.profile.email_sender_name ?? '',
    logo: null,
    remove_logo: false,
});

function pickLogo(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.logo = input.files?.[0] ?? null;
}

function save(): void {
    form.post(BrandController.update.url(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('logo', 'remove_logo'),
    });
}
</script>

<template>
    <Head :title="$t('core:brand.title')" />

    <form class="max-w-2xl space-y-6 px-4 py-6" @submit.prevent="save">
        <Heading
            :title="$t('core:brand.title')"
            :description="$t('core:brand.description')"
        />

        <div class="grid gap-2">
            <Label for="product_name">{{
                $t('core:brand.product_name')
            }}</Label>
            <Input
                id="product_name"
                v-model="form.product_name"
                maxlength="60"
                :placeholder="instanceName"
                aria-describedby="product_name-help"
            />
            <p id="product_name-help" class="text-sm text-muted-foreground">
                {{ $t('core:brand.product_name_help', { name: instanceName }) }}
            </p>
            <InputError :message="form.errors.product_name" />
        </div>

        <div class="grid gap-2">
            <Label for="logo">{{ $t('core:brand.logo') }}</Label>
            <img
                v-if="logoUrl && !form.remove_logo"
                :src="logoUrl"
                alt=""
                class="size-16 rounded-md border object-contain"
                data-test="brand-logo"
            />
            <input
                id="logo"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="text-sm"
                aria-describedby="logo-help"
                @change="pickLogo"
            />
            <p id="logo-help" class="text-sm text-muted-foreground">
                {{ $t('core:brand.logo_help') }}
            </p>
            <label v-if="logoUrl" class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.remove_logo"
                    type="checkbox"
                    class="size-4 rounded border-input"
                />
                {{ $t('core:brand.remove_logo') }}
            </label>
            <InputError :message="form.errors.logo" />
        </div>

        <div class="grid gap-2">
            <Label for="primary_color">{{
                $t('core:brand.primary_color')
            }}</Label>
            <div class="flex items-center gap-3">
                <input
                    id="primary_color"
                    v-model="form.primary_color"
                    type="color"
                    class="h-9 w-16 rounded-md border border-input"
                    aria-describedby="primary_color-help"
                />
                <Button
                    v-if="form.primary_color"
                    type="button"
                    variant="ghost"
                    @click="form.primary_color = ''"
                    >{{ $t('core:brand.reset_color') }}</Button
                >
            </div>
            <p id="primary_color-help" class="text-sm text-muted-foreground">
                {{ $t('core:brand.primary_color_help') }}
            </p>
            <InputError :message="form.errors.primary_color" />
        </div>

        <div class="grid gap-2">
            <Label for="email_sender_name">{{
                $t('core:brand.email_sender_name')
            }}</Label>
            <Input
                id="email_sender_name"
                v-model="form.email_sender_name"
                maxlength="80"
                aria-describedby="email_sender_name-help"
            />
            <p
                id="email_sender_name-help"
                class="text-sm text-muted-foreground"
            >
                {{ $t('core:brand.email_sender_name_help') }}
            </p>
            <InputError :message="form.errors.email_sender_name" />
        </div>

        <Button
            type="submit"
            :disabled="form.processing"
            data-test="save-brand-button"
            >{{ $t('core:common.save') }}</Button
        >
    </form>
</template>
