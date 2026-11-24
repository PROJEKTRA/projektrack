<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import ProjectController from '@/actions/Modules/Projects/Http/ProjectController';
import FormDialog from '@/components/FormDialog.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { t } from '@/i18n/strings';
import { formatHours, parseHours } from '@/lib/hours';
import type { ProjectDetails, ProjectFormMeta } from '../types';

const props = defineProps<{
    meta: ProjectFormMeta;
    project?: ProjectDetails;
}>();
const open = defineModel<boolean>('open', { required: true });

function values() {
    const p = props.project;

    return {
        name: p?.name ?? '',
        company_id: p?.company_id ?? props.meta.companies[0]?.value ?? '',
        status: p?.status ?? 'active',
        billing_model: p?.billing_model ?? 'time_and_material',
        start_date: p?.start_date ?? null,
        end_date: p?.end_date ?? null,
        budget_minutes:
            p?.budget_minutes == null ? '' : formatHours(p.budget_minutes),
        budget_minor:
            p?.budget_minor == null ? '' : (p.budget_minor / 100).toFixed(2),
        currency: p?.currency ?? props.meta.currency,
        amber_percent: p?.amber_percent ?? 90,
    };
}

const form = useForm(values());
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

watch(open, (shown) => {
    if (shown) {
        form.defaults(values());
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    const request = form.transform(
        ({ budget_minutes, budget_minor, status, ...rest }) => ({
            ...rest,
            ...(props.project ? { status } : {}),
            // An unreadable value is sent as it is, so the server refuses it.
            budget_minutes:
                budget_minutes === ''
                    ? null
                    : (parseHours(budget_minutes) ?? budget_minutes),
            ...(props.meta.can.money
                ? { budget_minor: minor(budget_minor) }
                : {}),
        }),
    );
    const then = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.project) {
        request.put(ProjectController.update.url(props.project.id), then);
    } else {
        request.post(ProjectController.store.url(), then);
    }
}

function minor(text: string): number | string | null {
    const match = /^(\d{1,10})(?:[.,](\d{1,2}))?$/.exec(text.trim());

    return text === ''
        ? null
        : match
          ? Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'))
          : text;
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="$t(project ? 'projects:edit_title' : 'projects:new')"
        :description="project ? undefined : $t('projects:dialog_description')"
        :save="$t('projects:save')"
        :processing="form.processing"
        @submit="submit"
    >
        <TextField
            v-model="form.name"
            name="name"
            :label="$t('projects:field.name')"
            :errors="errors"
            required
        />
        <SelectField
            v-model="form.company_id"
            name="company_id"
            :label="$t('projects:field.company')"
            :errors="errors"
            :options="meta.companies"
        />
        <SelectField
            v-model="form.billing_model"
            name="billing_model"
            :label="$t('projects:field.billing_model')"
            :errors="errors"
            :options="
                meta.billingModels.map((value) => ({
                    value,
                    text: t(`projects:billing.${value}`),
                }))
            "
        />
        <SelectField
            v-if="project"
            v-model="form.status"
            name="status"
            :label="$t('projects:field.status')"
            :errors="errors"
            :options="
                meta.statuses.map((value) => ({
                    value,
                    text: t(`projects:status.${value}`),
                }))
            "
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
        <TextField
            v-model="form.budget_minutes"
            name="budget_minutes"
            :label="$t('projects:field.budget_hours')"
            :errors="errors"
        />
        <TextField
            v-if="meta.can.money"
            v-model="form.budget_minor"
            name="budget_minor"
            :label="$t('projects:field.budget')"
            :errors="errors"
        />
        <TextField
            v-model="form.currency"
            name="currency"
            :label="$t('projects:field.currency')"
            :errors="errors"
            :maxlength="3"
            required
        />
        <TextField
            v-model="form.amber_percent"
            name="amber_percent"
            type="number"
            :label="$t('projects:field.amber_percent')"
            :errors="errors"
            required
        />
    </FormDialog>
</template>
