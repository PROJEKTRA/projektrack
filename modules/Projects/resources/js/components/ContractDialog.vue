<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import ContractController from '@/actions/Modules/Projects/Http/ContractController';
import FormDialog from '@/components/FormDialog.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { t } from '@/i18n/strings';
import { formatHours, parseHours } from '@/lib/hours';
import type { ContractData } from '../types';

const props = defineProps<{
    projectId: string;
    contract: ContractData | null;
    types: string[];
}>();
const open = defineModel<boolean>('open', { required: true });

function values() {
    const c = props.contract;

    return {
        number: c?.number ?? '',
        contract_type: c?.contract_type ?? 'time_and_material',
        status: c?.status ?? 'active',
        start_date: c?.start_date ?? null,
        end_date: c?.end_date ?? null,
        budget_minutes:
            c?.budget_minutes == null ? '' : formatHours(c.budget_minutes),
        rounding_minutes: String(c?.rounding_minutes ?? 15),
        minimum_minutes: c?.minimum_minutes ?? 0,
    };
}

const form = useForm(values());
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const roundings = ['1', '5', '6', '10', '15', '30', '60'].map((value) => ({
    value,
    text: value,
}));

watch(open, (shown) => {
    if (shown) {
        form.defaults(values());
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    const request = form.transform((data) => ({
        ...data,
        budget_minutes:
            data.budget_minutes === ''
                ? null
                : (parseHours(data.budget_minutes) ?? data.budget_minutes),
    }));
    const then = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.contract) {
        request.put(
            ContractController.update.url([props.projectId, props.contract.id]),
            then,
        );
    } else {
        request.post(ContractController.store.url(props.projectId), then);
    }
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="
            $t(contract ? 'projects:edit_contract' : 'projects:new_contract')
        "
        :save="$t('projects:save')"
        :processing="form.processing"
        @submit="submit"
    >
        <TextField
            v-model="form.number"
            name="number"
            :label="$t('projects:field.number')"
            :errors="errors"
            required
        />
        <SelectField
            v-model="form.contract_type"
            name="contract_type"
            :label="$t('projects:field.contract_type')"
            :errors="errors"
            :options="
                types.map((value) => ({
                    value,
                    text: t(`projects:contract.${value}`),
                }))
            "
        />
        <SelectField
            v-model="form.status"
            name="status"
            :label="$t('projects:field.status')"
            :errors="errors"
            :options="
                ['active', 'closed'].map((value) => ({
                    value,
                    text: t(`projects:status.${value}`),
                }))
            "
        />
        <TextField
            v-model="form.budget_minutes"
            name="budget_minutes"
            :label="$t('projects:field.budget_hours')"
            :errors="errors"
        />
        <TextField
            v-model="form.start_date"
            name="start_date"
            type="date"
            :label="$t('projects:field.start_date')"
            :errors="errors"
        />
        <TextField
            v-model="form.end_date"
            name="end_date"
            type="date"
            :label="$t('projects:field.end_date')"
            :errors="errors"
        />
        <SelectField
            v-model="form.rounding_minutes"
            name="rounding_minutes"
            :label="$t('projects:field.rounding')"
            :errors="errors"
            :options="roundings"
        />
        <TextField
            v-model="form.minimum_minutes"
            name="minimum_minutes"
            type="number"
            :label="$t('projects:field.minimum')"
            :errors="errors"
            required
        />
    </FormDialog>
</template>
