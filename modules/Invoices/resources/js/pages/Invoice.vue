<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InvoiceController from '@/actions/Modules/Invoices/Http/InvoiceController';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDay, formatMoney, formatNumber } from '@/i18n/formats';
import { t } from '@/i18n/strings';
import { formatHours } from '@/lib/hours';
import LinesEditor from '../components/LinesEditor.vue';
import type { Line } from '../components/LinesEditor.vue';

type LineRow = {
    position: number;
    description: string;
    minutes: number | null;
    quantity: string | null;
    unit: string;
    unit_price: string;
    rate_class?: string;
    net_minor: number;
};
type Ref = { id: string; number: string } | null;
type InvoiceData = {
    id: string;
    kind: string;
    number: string | null;
    company: string;
    status: string;
    currency: string;
    issue_date: string | null;
    due_date: string | null;
    service_from: string;
    service_to: string;
    fiscal_reference: string | null;
    lines: LineRow[];
    groups: {
        case: string;
        rate_ppm: number;
        net_minor: number;
        tax_minor: number;
    }[];
    net_minor: number;
    tax_minor: number;
    gross_minor: number | null;
    open_minor: number;
    overdue_days: number;
    problems: string[];
    notices: string[];
    cancels: Ref;
    cancelled_by: Ref;
    credits: Ref;
    credit_notes: { id: string; number: string }[];
    override_case: string | null;
    payments: {
        id: string;
        paid_on: string;
        amount_minor: number;
        note: string | null;
    }[];
};

const props = defineProps<{
    invoice: InvoiceData;
    today: string;
    confirmed: boolean;
    hasXml: boolean;
    cases: string[];
    recipients: string[];
    deliveries: {
        id: string;
        recipient: string;
        status: string;
        at: string | null;
    }[];
    can: {
        create: boolean;
        finalize: boolean;
        cancel: boolean;
        pay: boolean;
        override: boolean;
    };
}>();

defineOptions({
    layout: (page: { invoice: InvoiceData }) => ({
        breadcrumbs: [
            { title: t('invoices:title'), href: InvoiceController.index() },
            {
                title: page.invoice.number ?? t('invoices:draft'),
                href: InvoiceController.show(page.invoice.id),
            },
        ],
    }),
});

/** "1.0000" as "1", "1500.0000" as "1500.00". */
function plain(value: string | null, least: number): string {
    const [whole, fraction = ''] = (value ?? '').split('.');
    const short = fraction.replace(/0+$/, '').padEnd(least, '0');

    return short ? `${whole}.${short}` : whole;
}

const isDraft = computed(() => props.invoice.status === 'draft');
const money = (minor: number) => formatMoney(minor, props.invoice.currency);
const dialog = ref<
    | ''
    | 'edit'
    | 'finalize'
    | 'cancel'
    | 'discard'
    | 'pay'
    | 'fiscal'
    | 'send'
    | 'override'
    | 'credit'
>('');
const shown = (name: typeof dialog.value) =>
    computed({
        get: () => dialog.value === name,
        set: (on) => (dialog.value = on ? name : ''),
    });
const [
    editing,
    finalizing,
    cancelling,
    discarding,
    paying,
    fiscalOpen,
    sending,
    overriding,
    crediting,
] = (
    [
        'edit',
        'finalize',
        'cancel',
        'discard',
        'pay',
        'fiscal',
        'send',
        'override',
        'credit',
    ] as const
).map(shown);

const edit = useForm({ service_from: '', service_to: '', lines: [] as Line[] });
const action = useForm({ gross_minor: null as number | null });

/** Issuing and cancelling ask for the password first, then come back here. */
function guarded(name: 'finalize' | 'cancel' | 'fiscal' | 'credit'): void {
    if (props.confirmed) {
        dialog.value = name;
    } else {
        router.visit(InvoiceController.confirm.url(props.invoice.id));
    }
}

const pay = useForm({ amount: '', paid_on: props.today, note: '' });
const override = useForm({
    case: props.invoice.override_case ?? '',
    reason: '',
});
const credit = useForm({ lines: {} as Record<string, string>, reason: '' });
const overrideErrors = computed(
    () => override.errors as Record<string, string | undefined>,
);
const creditErrors = computed(
    () => credit.errors as Record<string, string | undefined>,
);
const send = useForm({ recipients: [...props.recipients] });
const fiscal = useForm({ fiscal_reference: '', reason: '' });
const editErrors = computed(
    () => edit.errors as Record<string, string | undefined>,
);
const payErrors = computed(
    () => pay.errors as Record<string, string | undefined>,
);
const fiscalErrors = computed(
    () => fiscal.errors as Record<string, string | undefined>,
);
const actionError = computed(
    () => (action.errors as Record<string, string | undefined>).invoice,
);
const close = { preserveScroll: true, onSuccess: () => (dialog.value = '') };

function openEdit(): void {
    edit.service_from = props.invoice.service_from;
    edit.service_to = props.invoice.service_to;
    edit.lines = props.invoice.lines
        .filter((l) => l.minutes === null)
        .map((l) => ({
            description: l.description,
            quantity: plain(l.quantity, 0) ?? '1',
            unit: l.unit,
            unit_price: l.unit_price,
            rate_class: l.rate_class ?? 'standard',
        }));
    dialog.value = 'edit';
}
</script>

<template>
    <Head :title="invoice.number ?? $t('invoices:draft')" />

    <div class="space-y-8 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Heading
                    :title="`${$t(`invoices:document.${invoice.kind}`)} ${invoice.number ?? ''}`"
                    :description="invoice.company"
                />
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge variant="secondary">{{
                        $t(`invoices:status.${invoice.status}`)
                    }}</Badge>
                    <bdi v-if="invoice.due_date && invoice.open_minor"
                        >{{ $t('invoices:field.due_date') }}:
                        {{ formatDay(invoice.due_date) }}</bdi
                    >
                    <span
                        v-if="invoice.overdue_days"
                        class="text-destructive"
                        >{{
                            $t('invoices:overdue', {
                                days: invoice.overdue_days,
                            })
                        }}</span
                    >
                    <Link
                        v-if="invoice.cancels"
                        class="underline"
                        :href="InvoiceController.show(invoice.cancels.id)"
                        >{{
                            $t('invoices:cancels', {
                                number: invoice.cancels.number,
                            })
                        }}</Link
                    >
                    <Link
                        v-if="invoice.cancelled_by"
                        class="underline"
                        :href="InvoiceController.show(invoice.cancelled_by.id)"
                        >{{
                            $t('invoices:cancelled_by', {
                                number: invoice.cancelled_by.number,
                            })
                        }}</Link
                    >
                    <Badge v-if="invoice.override_case" variant="outline">{{
                        $t('invoices:override.badge')
                    }}</Badge>
                    <Link
                        v-if="invoice.credits"
                        class="underline"
                        :href="InvoiceController.show(invoice.credits.id)"
                        >{{
                            $t('invoices:credit.of', {
                                number: invoice.credits.number,
                            })
                        }}</Link
                    >
                    <Link
                        v-for="n in invoice.credit_notes"
                        :key="n.id"
                        class="underline"
                        :href="InvoiceController.show(n.id)"
                        >{{
                            $t('invoices:credit.by', { number: n.number })
                        }}</Link
                    >
                    <bdi v-if="invoice.fiscal_reference"
                        >{{ $t('invoices:fiscal') }}:
                        {{ invoice.fiscal_reference }}</bdi
                    >
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <template v-if="isDraft">
                    <Button
                        v-if="can.create"
                        variant="ghost"
                        @click="dialog = 'discard'"
                        >{{ $t('invoices:discard') }}</Button
                    >
                    <Button
                        v-if="can.override && cases.length"
                        variant="ghost"
                        data-test="override"
                        @click="dialog = 'override'"
                        >{{ $t('invoices:override.title') }}</Button
                    >
                    <Button
                        v-if="can.create"
                        variant="secondary"
                        data-test="edit-invoice"
                        @click="openEdit"
                        >{{ $t('invoices:edit') }}</Button
                    >
                    <Button
                        v-if="can.finalize"
                        data-test="finalize"
                        :disabled="invoice.problems.length > 0"
                        @click="guarded('finalize')"
                        >{{ $t('invoices:finalize') }}</Button
                    >
                </template>
                <template v-else>
                    <Button as-child variant="secondary"
                        ><a
                            :href="InvoiceController.pdf.url(invoice.id)"
                            download
                            >{{ $t('invoices:pdf') }}</a
                        ></Button
                    >
                    <Button v-if="hasXml" as-child variant="ghost"
                        ><a
                            :href="InvoiceController.xml.url(invoice.id)"
                            download
                            >{{ $t('invoices:xml') }}</a
                        ></Button
                    >
                    <Button
                        v-if="can.finalize"
                        variant="secondary"
                        data-test="send"
                        @click="dialog = 'send'"
                        >{{ $t('invoices:send.title') }}</Button
                    >
                    <Button
                        v-if="can.finalize"
                        variant="ghost"
                        @click="guarded('fiscal')"
                        >{{ $t('invoices:fiscal') }}</Button
                    >
                    <Button
                        v-if="
                            can.cancel &&
                            invoice.kind === 'invoice' &&
                            invoice.status !== 'cancelled'
                        "
                        variant="ghost"
                        data-test="credit"
                        @click="guarded('credit')"
                        >{{ $t('invoices:credit.title') }}</Button
                    >
                    <Button
                        v-if="
                            can.cancel &&
                            invoice.kind === 'invoice' &&
                            invoice.status !== 'cancelled'
                        "
                        variant="ghost"
                        data-test="cancel-invoice"
                        @click="guarded('cancel')"
                        >{{ $t('invoices:cancel') }}</Button
                    >
                    <Button
                        v-if="can.pay && invoice.open_minor > 0"
                        data-test="pay"
                        @click="dialog = 'pay'"
                        >{{ $t('invoices:pay') }}</Button
                    >
                </template>
            </div>
        </div>

        <div
            v-if="invoice.problems.length"
            class="rounded-lg border border-destructive p-4 text-sm"
            role="status"
        >
            <p class="font-medium">{{ $t('invoices:missing') }}</p>
            <ul class="list-disc ps-5">
                <li v-for="p in invoice.problems" :key="p">{{ p }}</li>
            </ul>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('invoices:field.description') }}
                        </th>
                        <th
                            v-for="c in ['quantity', 'unit_price', 'net']"
                            :key="c"
                            scope="col"
                            class="px-4 py-2 text-end font-medium"
                        >
                            {{ $t(`invoices:field.${c}`) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="l in invoice.lines"
                        :key="l.position"
                        class="border-t"
                    >
                        <td class="px-4 py-2">
                            <bdi>{{ l.description }}</bdi>
                        </td>
                        <td class="px-4 py-2 text-end tabular-nums">
                            <bdi
                                >{{
                                    l.minutes !== null
                                        ? formatHours(l.minutes)
                                        : l.quantity
                                }}
                                {{ $t(`invoices:unit.${l.unit}`) }}</bdi
                            >
                        </td>
                        <td class="px-4 py-2 text-end tabular-nums">
                            <bdi>{{ plain(l.unit_price, 2) }}</bdi>
                        </td>
                        <td class="px-4 py-2 text-end tabular-nums">
                            <bdi>{{ money(l.net_minor) }}</bdi>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t">
                    <tr>
                        <td colspan="3" class="px-4 py-1 text-end">
                            {{ $t('invoices:field.net') }}
                        </td>
                        <td class="px-4 py-1 text-end tabular-nums">
                            <bdi>{{ money(invoice.net_minor) }}</bdi>
                        </td>
                    </tr>
                    <tr
                        v-for="g in invoice.groups"
                        :key="`${g.case}-${g.rate_ppm}`"
                    >
                        <td colspan="3" class="px-4 py-1 text-end">
                            {{
                                $t('invoices:document.tax_on', {
                                    rate: formatNumber(g.rate_ppm / 10000),
                                    net: money(g.net_minor),
                                })
                            }}
                        </td>
                        <td class="px-4 py-1 text-end tabular-nums">
                            <bdi>{{ money(g.tax_minor) }}</bdi>
                        </td>
                    </tr>
                    <tr v-if="invoice.gross_minor !== null" class="font-medium">
                        <td colspan="3" class="px-4 py-2 text-end">
                            {{ $t('invoices:field.gross') }}
                        </td>
                        <td
                            class="px-4 py-2 text-end tabular-nums"
                            data-test="gross"
                        >
                            <bdi>{{ money(invoice.gross_minor) }}</bdi>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p v-for="n in invoice.notices" :key="n" class="text-sm">{{ n }}</p>

        <section v-if="deliveries.length" class="space-y-2">
            <h2 class="font-medium">{{ $t('invoices:send.history') }}</h2>
            <ul class="text-sm">
                <li v-for="d in deliveries" :key="d.id">
                    <bdi>{{
                        `${d.recipient} (${$t(`invoices:send.status_${d.status}`)})`
                    }}</bdi>
                </li>
            </ul>
        </section>

        <section v-if="invoice.payments.length" class="space-y-2">
            <h2 class="font-medium">{{ $t('invoices:payments') }}</h2>
            <ul class="text-sm">
                <li v-for="p in invoice.payments" :key="p.id">
                    <bdi
                        >{{ formatDay(p.paid_on) }}: {{ money(p.amount_minor) }}
                        {{ p.note }}</bdi
                    >
                </li>
            </ul>
        </section>
    </div>

    <FormDialog
        v-model:open="editing"
        :title="$t('invoices:edit')"
        :save="$t('invoices:save')"
        :processing="edit.processing"
        @submit="edit.put(InvoiceController.update.url(invoice.id), close)"
    >
        <TextField
            v-model="edit.service_from"
            name="service_from"
            type="date"
            :label="$t('invoices:field.from')"
            :errors="editErrors"
            required
        />
        <TextField
            v-model="edit.service_to"
            name="service_to"
            type="date"
            :label="$t('invoices:field.to')"
            :errors="editErrors"
            required
        />
        <LinesEditor v-model="edit.lines" :errors="editErrors" />
    </FormDialog>
    <FormDialog
        v-model:open="finalizing"
        :title="$t('invoices:finalize')"
        :description="$t('invoices:finalize_confirm')"
        :save="$t('invoices:finalize')"
        :processing="action.processing"
        @submit="
            action
                .transform(() => ({ gross_minor: invoice.gross_minor }))
                .post(InvoiceController.finalize.url(invoice.id), close)
        "
        ><p v-if="actionError" class="text-sm text-destructive sm:col-span-2">
            {{ actionError }}
        </p></FormDialog
    >
    <FormDialog
        v-model:open="cancelling"
        :title="$t('invoices:cancel')"
        :description="$t('invoices:cancel_confirm')"
        :save="$t('invoices:cancel')"
        :processing="action.processing"
        @submit="action.post(InvoiceController.cancel.url(invoice.id), close)"
    />
    <FormDialog
        v-model:open="discarding"
        :title="$t('invoices:discard')"
        :description="$t('invoices:discard_confirm')"
        :save="$t('invoices:discard')"
        :processing="action.processing"
        @submit="
            action.delete(InvoiceController.discard.url(invoice.id), close)
        "
    />
    <FormDialog
        v-model:open="paying"
        :title="$t('invoices:pay')"
        :save="$t('invoices:save')"
        :processing="pay.processing"
        @submit="pay.post(InvoiceController.pay.url(invoice.id), close)"
    >
        <TextField
            v-model="pay.amount"
            name="amount"
            :label="$t('invoices:field.amount')"
            :errors="payErrors"
            required
        />
        <TextField
            v-model="pay.paid_on"
            name="paid_on"
            type="date"
            :label="$t('invoices:field.paid_on')"
            :errors="payErrors"
            required
        />
        <TextField
            v-model="pay.note"
            name="note"
            :label="$t('invoices:field.note')"
            :errors="payErrors"
        />
    </FormDialog>
    <FormDialog
        v-model:open="overriding"
        :title="$t('invoices:override.title')"
        :description="$t('invoices:override.description')"
        :save="$t('invoices:save')"
        :processing="override.processing"
        @submit="
            override
                .transform((d) => ({ ...d, case: d.case || null }))
                .put(InvoiceController.taxCase.url(invoice.id), close)
        "
    >
        <SelectField
            v-model="override.case"
            name="case"
            :label="$t('invoices:override.title')"
            :errors="overrideErrors"
            :options="[
                { value: '', text: t('invoices:case.rules') },
                ...cases.map((value) => ({
                    value,
                    text: t(`invoices:case.${value.toLowerCase()}`),
                })),
            ]"
        />
        <TextField
            v-model="override.reason"
            name="reason"
            :label="$t('invoices:field.why')"
            :errors="overrideErrors"
            required
        />
    </FormDialog>
    <FormDialog
        v-model:open="crediting"
        :title="$t('invoices:credit.title')"
        :description="$t('invoices:credit.description')"
        :save="$t('invoices:credit.title')"
        :processing="credit.processing"
        @submit="credit.post(InvoiceController.credit.url(invoice.id), close)"
    >
        <TextField
            v-for="l in invoice.lines"
            :key="l.position"
            :model-value="credit.lines[l.position] ?? ''"
            :name="`lines.${l.position}`"
            :label="`${l.description} (${money(l.net_minor)})`"
            :errors="creditErrors"
            @update:model-value="
                credit.lines[l.position] = String($event ?? '')
            "
        />
        <TextField
            v-model="credit.reason"
            name="reason"
            :label="$t('invoices:field.why')"
            :errors="creditErrors"
            required
        />
    </FormDialog>
    <FormDialog
        v-model:open="sending"
        :title="$t('invoices:send.title')"
        :description="$t('invoices:send.description')"
        :save="$t('invoices:send.title')"
        :processing="send.processing"
        @submit="send.post(InvoiceController.send.url(invoice.id), close)"
    >
        <p
            v-if="recipients.length === 0"
            class="text-sm text-destructive sm:col-span-2"
        >
            {{ $t('invoices:send.none') }}
        </p>
        <label
            v-for="r in recipients"
            :key="r"
            class="flex items-center gap-2 text-sm sm:col-span-2"
            ><input
                v-model="send.recipients"
                type="checkbox"
                :value="r"
            /><bdi>{{ r }}</bdi></label
        >
    </FormDialog>
    <FormDialog
        v-model:open="fiscalOpen"
        :title="$t('invoices:fiscal')"
        :save="$t('invoices:save')"
        :processing="fiscal.processing"
        @submit="fiscal.put(InvoiceController.fiscal.url(invoice.id), close)"
    >
        <TextField
            v-model="fiscal.fiscal_reference"
            name="fiscal_reference"
            :label="$t('invoices:fiscal')"
            :errors="fiscalErrors"
            required
        />
        <TextField
            v-if="invoice.fiscal_reference"
            v-model="fiscal.reason"
            name="reason"
            :label="$t('invoices:field.reason')"
            :errors="fiscalErrors"
            required
        />
    </FormDialog>
</template>
