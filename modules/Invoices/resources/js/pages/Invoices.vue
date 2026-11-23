<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InvoiceController from '@/actions/Modules/Invoices/Http/InvoiceController';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDay, formatMoney } from '@/i18n/formats';
import { t } from '@/i18n/strings';
import LinesEditor from '../components/LinesEditor.vue';
import type { Line } from '../components/LinesEditor.vue';

type Row = {
    id: string;
    number: string | null;
    company: string;
    status: string;
    issue_date: string | null;
    due_date: string | null;
    net_minor: number;
    gross_minor: number;
    open_minor: number;
    overdue_days: number;
    currency: string;
};
type Option = { value: string; text: string };

const props = defineProps<{
    invoices: {
        data: Row[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    open: boolean;
    companies: Option[];
    projects: Option[];
    from: string;
    to: string;
    canCreate: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: t('invoices:title'), href: InvoiceController.index() },
        ],
    }),
});

const freeOpen = ref(false);
const timeOpen = ref(false);
const free = useForm({
    company_id: props.companies[0]?.value ?? '',
    service_from: props.from,
    service_to: props.to,
    lines: [] as Line[],
});
const time = useForm({
    project_id: props.projects[0]?.value ?? '',
    period_from: props.from,
    period_to: props.to,
});
const freeErrors = computed(
    () => free.errors as Record<string, string | undefined>,
);
const timeErrors = computed(
    () => time.errors as Record<string, string | undefined>,
);
</script>

<template>
    <Head :title="$t('invoices:title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('invoices:title')"
                :description="$t('invoices:description')"
            />
            <div v-if="canCreate" class="flex gap-2">
                <Button
                    variant="secondary"
                    data-test="new-invoice"
                    @click="freeOpen = true"
                    >{{ $t('invoices:new') }}</Button
                >
                <Button data-test="new-time-invoice" @click="timeOpen = true">{{
                    $t('invoices:new_time')
                }}</Button>
            </div>
        </div>
        <div class="flex gap-2">
            <Button as-child :variant="open ? 'ghost' : 'secondary'"
                ><Link :href="InvoiceController.index()">{{
                    $t('invoices:show_all')
                }}</Link></Button
            >
            <Button as-child :variant="open ? 'secondary' : 'ghost'"
                ><Link
                    :href="InvoiceController.index({ query: { show: 'open' } })"
                    >{{ $t('invoices:show_open') }}</Link
                ></Button
            >
        </div>
        <p v-if="invoices.data.length === 0" class="text-muted-foreground">
            {{ $t('invoices:empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            v-for="c in [
                                'number',
                                'company',
                                'status',
                                'issue_date',
                                'due_date',
                                'gross',
                                'open',
                            ]"
                            :key="c"
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t(`invoices:field.${c}`) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in invoices.data" :key="i.id" class="border-t">
                        <td class="px-4 py-2">
                            <Link
                                :href="InvoiceController.show(i.id)"
                                class="text-primary underline-offset-4 hover:underline"
                                ><bdi>{{
                                    i.number ?? $t('invoices:draft')
                                }}</bdi></Link
                            >
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{ i.company }}</bdi>
                        </td>
                        <td class="px-4 py-2">
                            <Badge variant="secondary">{{
                                $t(`invoices:status.${i.status}`)
                            }}</Badge>
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{
                                i.issue_date && formatDay(i.issue_date)
                            }}</bdi>
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{ i.due_date && formatDay(i.due_date) }}</bdi>
                            <span
                                v-if="i.overdue_days"
                                class="text-destructive"
                                >{{
                                    $t('invoices:overdue', {
                                        days: i.overdue_days,
                                    })
                                }}</span
                            >
                        </td>
                        <td class="px-4 py-2 tabular-nums">
                            <bdi>{{
                                formatMoney(
                                    i.status === 'draft'
                                        ? i.net_minor
                                        : i.gross_minor,
                                    i.currency,
                                )
                            }}</bdi>
                        </td>
                        <td class="px-4 py-2 tabular-nums">
                            <bdi>{{
                                i.open_minor
                                    ? formatMoney(i.open_minor, i.currency)
                                    : ''
                            }}</bdi>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="invoices.prev_page_url || invoices.next_page_url"
            class="flex gap-2"
        >
            <Button v-if="invoices.prev_page_url" as-child variant="secondary"
                ><Link :href="invoices.prev_page_url">{{
                    $t('invoices:previous')
                }}</Link></Button
            >
            <Button v-if="invoices.next_page_url" as-child variant="secondary"
                ><Link :href="invoices.next_page_url">{{
                    $t('invoices:next')
                }}</Link></Button
            >
        </div>
    </div>

    <FormDialog
        v-model:open="freeOpen"
        :title="$t('invoices:new')"
        :save="$t('invoices:create')"
        :processing="free.processing"
        @submit="free.post(InvoiceController.store.url())"
    >
        <SelectField
            v-model="free.company_id"
            name="company_id"
            :label="$t('invoices:field.company')"
            :errors="freeErrors"
            :options="companies"
        />
        <span />
        <TextField
            v-model="free.service_from"
            name="service_from"
            type="date"
            :label="$t('invoices:field.from')"
            :errors="freeErrors"
            required
        />
        <TextField
            v-model="free.service_to"
            name="service_to"
            type="date"
            :label="$t('invoices:field.to')"
            :errors="freeErrors"
            required
        />
        <LinesEditor v-model="free.lines" :errors="freeErrors" />
    </FormDialog>
    <FormDialog
        v-model:open="timeOpen"
        :title="$t('invoices:new_time')"
        :save="$t('invoices:create')"
        :processing="time.processing"
        @submit="time.post(InvoiceController.fromTime.url())"
    >
        <SelectField
            v-model="time.project_id"
            name="project_id"
            :label="$t('invoices:field.project')"
            :errors="timeErrors"
            :options="projects"
        />
        <span />
        <TextField
            v-model="time.period_from"
            name="period_from"
            type="date"
            :label="$t('invoices:field.from')"
            :errors="timeErrors"
            required
        />
        <TextField
            v-model="time.period_to"
            name="period_to"
            type="date"
            :label="$t('invoices:field.to')"
            :errors="timeErrors"
            required
        />
    </FormDialog>
</template>
