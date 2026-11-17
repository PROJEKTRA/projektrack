<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { LockKeyhole, RefreshCw } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import AlertError from '@/components/AlertError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

/*
 * Recovery codes are stored as hashes. The server returns new codes exactly
 * once, right after they were created; afterwards only regenerating helps.
 */
defineProps<{
    shownOnceNotice: string;
    hiddenNotice: string;
}>();

const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();
const loaded = ref<boolean>(false);

const loadNewCodes = async (): Promise<void> => {
    await fetchRecoveryCodes();
    loaded.value = true;
};

onMounted(async () => {
    if (!recoveryCodesList.value.length) {
        await loadNewCodes();
    }
});
</script>

<template>
    <Card class="w-full">
        <CardHeader>
            <CardTitle class="flex gap-3">
                <LockKeyhole class="size-4" />{{
                    $t('core:settings.security.recovery_codes.title')
                }}
            </CardTitle>
            <CardDescription>
                {{ $t('core:settings.security.recovery_codes.description') }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
            <AlertError v-if="errors?.length" :errors="errors" />

            <template v-else-if="recoveryCodesList.length">
                <p
                    role="status"
                    class="text-sm font-medium"
                    data-test="recovery-codes-shown-once"
                >
                    {{ shownOnceNotice }}
                </p>
                <div
                    class="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                >
                    <div
                        v-for="(code, index) in recoveryCodesList"
                        :key="index"
                    >
                        <bdi>{{ code }}</bdi>
                    </div>
                </div>
            </template>

            <p
                v-else-if="loaded"
                class="text-sm text-muted-foreground"
                data-test="recovery-codes-hidden"
            >
                {{ hiddenNotice }}
            </p>

            <Form
                v-slot="{ processing }"
                v-bind="regenerateRecoveryCodes.form()"
                method="post"
                :options="{ preserveScroll: true }"
                @success="loadNewCodes"
            >
                <Button
                    variant="secondary"
                    type="submit"
                    :disabled="processing"
                >
                    <RefreshCw />
                    {{ $t('core:settings.security.recovery_codes.regenerate') }}
                </Button>
            </Form>
        </CardContent>
    </Card>
</template>
