<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReportController from '@/actions/Modules/ServiceReports/Http/ReportController';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { Button } from '@/components/ui/button';
import { formatDay } from '@/i18n/formats';
import { t } from '@/i18n/strings';

const props = defineProps<{
    reports: {
        id: string;
        number: string;
        project: string;
        from: string;
        to: string;
        with_amounts: boolean;
    }[];
    projects: { value: string; text: string; amounts: boolean }[];
    from: string;
    to: string;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('service_reports:title'),
                href: ReportController.index(),
            },
        ],
    }),
});

const open = ref(false);
const form = useForm({
    project_id: props.projects[0]?.value ?? '',
    period_from: props.from,
    period_to: props.to,
    with_amounts: false,
});
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const mayAmounts = computed(
    () =>
        props.projects.find((p) => p.value === form.project_id)?.amounts ??
        false,
);

function submit(): void {
    form.transform((d) => ({
        ...d,
        with_amounts: d.with_amounts && mayAmounts.value,
    })).post(ReportController.store.url(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Head :title="$t('service_reports:title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('service_reports:title')"
                :description="$t('service_reports:description')"
            />
            <Button
                v-if="projects.length > 0"
                data-test="new-report"
                @click="open = true"
                ><Plus class="size-4" />{{ $t('service_reports:new') }}</Button
            >
        </div>
        <p v-if="reports.length === 0" class="text-muted-foreground">
            {{ $t('service_reports:empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            v-for="c in [
                                'number',
                                'project',
                                'period',
                                'download',
                            ]"
                            :key="c"
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t(`service_reports:field.${c}`) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in reports" :key="r.id" class="border-t">
                        <td class="px-4 py-2">
                            <bdi class="font-mono text-xs">{{ r.number }}</bdi>
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{ r.project }}</bdi>
                        </td>
                        <td class="px-4 py-2">
                            <bdi
                                >{{ formatDay(r.from) }} -
                                {{ formatDay(r.to) }}</bdi
                            >
                        </td>
                        <td class="flex gap-2 px-4 py-2">
                            <Button as-child size="sm" variant="secondary"
                                ><a
                                    :href="ReportController.pdf.url(r.id)"
                                    download
                                    >{{ $t('service_reports:pdf') }}</a
                                ></Button
                            >
                            <Button as-child size="sm" variant="ghost"
                                ><a
                                    :href="ReportController.docx.url(r.id)"
                                    download
                                    >{{ $t('service_reports:docx') }}</a
                                ></Button
                            >
                            <Button as-child size="sm" variant="ghost"
                                ><a
                                    :href="ReportController.xlsx.url(r.id)"
                                    download
                                    >{{ $t('service_reports:xlsx') }}</a
                                ></Button
                            >
                            <Button as-child size="sm" variant="ghost"
                                ><a
                                    :href="ReportController.csv.url(r.id)"
                                    download
                                    >{{ $t('service_reports:csv') }}</a
                                ></Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <FormDialog
        v-model:open="open"
        :title="$t('service_reports:new')"
        :save="$t('service_reports:generate')"
        :processing="form.processing"
        @submit="submit"
    >
        <SelectField
            v-model="form.project_id"
            name="project_id"
            :label="$t('service_reports:field.project')"
            :errors="errors"
            :options="projects"
        />
        <label
            v-if="mayAmounts"
            class="flex items-center gap-2 self-end pb-2 text-sm"
            ><input v-model="form.with_amounts" type="checkbox" />{{
                $t('service_reports:field.with_amounts')
            }}</label
        >
        <TextField
            v-model="form.period_from"
            name="period_from"
            type="date"
            :label="$t('service_reports:field.from')"
            :errors="errors"
            required
        />
        <TextField
            v-model="form.period_to"
            name="period_to"
            type="date"
            :label="$t('service_reports:field.to')"
            :errors="errors"
            required
        />
    </FormDialog>
</template>
