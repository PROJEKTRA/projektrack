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

// Password of a shared link (S-0987); the form posts to the link itself.
const props = defineProps<{ action: string }>();

defineOptions({
    layout: () => ({
        title: t('core:shared_links.unlock.title'),
        description: t('core:shared_links.unlock.description'),
    }),
});
</script>

<template>
    <Head :title="$t('core:shared_links.unlock.title')" />

    <Form
        v-slot="{ errors, processing }"
        :action="props.action"
        method="post"
        reset-on-success
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label html-for="password">{{
                    $t('core:shared_links.unlock.password_label')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="off"
                    autofocus
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                class="w-full"
                :disabled="processing"
                data-test="shared-link-unlock"
            >
                <Spinner v-if="processing" />
                {{ $t('core:shared_links.unlock.submit') }}
            </Button>
        </div>
    </Form>
</template>
