<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import WorkPackageController from '@/actions/Modules/Projects/Http/WorkPackageController';
import FormDialog from '@/components/FormDialog.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { t } from '@/i18n/strings';
import { formatHours, parseHours } from '@/lib/hours';
import type { PackageData, ProjectDetails } from '../types';

const props = defineProps<{
    project: ProjectDetails;
    workPackage: PackageData | null;
    categories: string[];
}>();
const open = defineModel<boolean>('open', { required: true });

function values() {
    const p = props.workPackage;

    return {
        name: p?.name ?? '',
        parent_id: '',
        status: p?.status ?? 'open',
        contract_id: p?.contract_id ?? '',
        planned_minutes:
            p?.planned_minutes == null ? '' : formatHours(p.planned_minutes),
        start_date: p?.start_date ?? null,
        end_date: p?.end_date ?? null,
        billable: p?.billable ?? true,
        service_category: p?.service_category ?? '',
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
    const request = form.transform((data) => ({
        ...data,
        parent_id: data.parent_id || null,
        contract_id: data.contract_id || null,
        service_category: data.service_category || null,
        planned_minutes:
            data.planned_minutes === ''
                ? null
                : (parseHours(data.planned_minutes) ?? data.planned_minutes),
    }));
    const then = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.workPackage) {
        request.put(
            WorkPackageController.update.url([
                props.project.id,
                props.workPackage.id,
            ]),
            then,
        );
    } else {
        request.post(WorkPackageController.store.url(props.project.id), then);
    }
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="
            $t(workPackage ? 'projects:edit_package' : 'projects:new_package')
        "
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
            v-if="!workPackage"
            v-model="form.parent_id"
            name="parent_id"
            :label="$t('projects:field.parent')"
            :errors="errors"
            :options="[
                { value: '', text: t('projects:top_level') },
                ...project.work_packages.map((p) => ({
                    value: p.id,
                    text: `${p.wbs_code} ${p.name}`,
                })),
            ]"
        />
        <SelectField
            v-else
            v-model="form.status"
            name="status"
            :label="$t('projects:field.status')"
            :errors="errors"
            :options="
                ['open', 'closed'].map((value) => ({
                    value,
                    text: t(`projects:status.${value}`),
                }))
            "
        />
        <SelectField
            v-model="form.contract_id"
            name="contract_id"
            :label="$t('projects:field.contract')"
            :errors="errors"
            :options="[
                { value: '', text: t('projects:no_contract') },
                ...project.contracts.map((c) => ({
                    value: c.id,
                    text: c.number,
                })),
            ]"
        />
        <SelectField
            v-model="form.service_category"
            name="service_category"
            :label="$t('projects:field.category')"
            :errors="errors"
            :options="[
                { value: '', text: t('projects:no_category') },
                ...categories.map((value) => ({
                    value,
                    text: t(`projects:category.${value}`),
                })),
            ]"
        />
        <TextField
            v-model="form.planned_minutes"
            name="planned_minutes"
            :label="$t('projects:field.planned_hours')"
            :errors="errors"
        />
        <label class="flex items-center gap-2 self-end pb-2 text-sm">
            <input v-model="form.billable" type="checkbox" />
            {{ $t('projects:field.billable') }}
        </label>
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
    </FormDialog>
</template>
