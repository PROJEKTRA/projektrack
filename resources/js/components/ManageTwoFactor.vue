<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { disable, enable } from '@/routes/two-factor';

export type TwoFactorNotices = {
    required: string | null;
    shownOnce: string;
    hidden: string;
};

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    twoFactorRequired?: boolean;
    twoFactorNotices?: TwoFactorNotices;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
    twoFactorRequired: false,
    twoFactorNotices: () => ({ required: null, shownOnce: '', hidden: '' }),
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <div v-if="canManageTwoFactor" class="space-y-6">
        <Heading
            variant="small"
            :title="$t('core:settings.security.two_factor.title')"
            :description="$t('core:settings.security.two_factor.description')"
        />

        <p
            v-if="twoFactorNotices.required && !twoFactorEnabled"
            role="alert"
            class="rounded-md border border-destructive/50 p-3 text-sm text-destructive"
            data-test="two-factor-required"
        >
            {{ twoFactorNotices.required }}
        </p>

        <div
            v-if="!twoFactorEnabled"
            class="flex flex-col items-start justify-start space-y-4"
        >
            <p class="text-sm text-muted-foreground">
                {{ $t('core:settings.security.two_factor.disabled_help') }}
            </p>

            <div>
                <Button v-if="hasSetupData" @click="showSetupModal = true">
                    <ShieldCheck />{{
                        $t('core:settings.security.two_factor.continue_setup')
                    }}
                </Button>
                <Form
                    v-else
                    v-slot="{ processing }"
                    v-bind="enable.form()"
                    @success="showSetupModal = true"
                >
                    <Button type="submit" :disabled="processing">
                        {{ $t('core:settings.security.two_factor.enable') }}
                    </Button>
                </Form>
            </div>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                {{ $t('core:settings.security.two_factor.enabled_help') }}
            </p>

            <div v-if="!twoFactorRequired" class="relative inline">
                <Form v-slot="{ processing }" v-bind="disable.form()">
                    <Button
                        variant="destructive"
                        type="submit"
                        :disabled="processing"
                    >
                        {{ $t('core:settings.security.two_factor.disable') }}
                    </Button>
                </Form>
            </div>

            <TwoFactorRecoveryCodes
                :shown-once-notice="twoFactorNotices.shownOnce"
                :hidden-notice="twoFactorNotices.hidden"
            />
        </div>

        <TwoFactorSetupModal
            v-model:is-open="showSetupModal"
            :requires-confirmation="requiresConfirmation"
            :two-factor-enabled="twoFactorEnabled"
        />
    </div>
</template>
