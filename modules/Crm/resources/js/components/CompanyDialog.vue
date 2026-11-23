<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import CompanyController from '@/actions/Modules/Crm/Http/CompanyController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/i18n/strings';
import AddressFields from './AddressFields.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import type {
    AddressData,
    CompanyData,
    CompanyFormMeta,
    ContactData,
    SiteData,
} from '../types';

const props = defineProps<{ meta: CompanyFormMeta; company?: CompanyData }>();
const open = defineModel<boolean>('open', { required: true });

type SiteForm = SiteData & { address: AddressData };

function emptyAddress(): AddressData {
    return {
        line1: '',
        line2: null,
        postal_code: null,
        city: '',
        region: null,
        country_code: props.meta.defaults.country,
    };
}

function emptyContact(): ContactData {
    return {
        first_name: null,
        last_name: '',
        email: null,
        phone: null,
        position: null,
        invoice_recipient: true,
    };
}

function values() {
    const c = props.company;

    return {
        name: c?.name ?? '',
        legal_name: c?.legal_name ?? null,
        kind: c?.kind ?? 'customer',
        status: c?.status ?? 'active',
        document_locale: c?.document_locale ?? props.meta.defaults.locale,
        currency: c?.currency ?? props.meta.defaults.currency,
        payment_days: c?.payment_days ?? 14,
        einvoice_format: c?.einvoice_format ?? 'pdf',
        leitweg_id: c?.leitweg_id ?? null,
        tax: {
            ...(c?.tax ?? {
                tax_status: 'domestic',
                country_code: props.meta.defaults.country,
                vat_id: null,
                tax_number: null,
            }),
            valid_from: null as string | null,
        },
        address: { ...(c?.address ?? emptyAddress()) },
        contacts: (c?.contacts ?? (c ? [] : [emptyContact()])).map(
            (contact) => ({ ...contact }),
        ),
        sites: (c?.sites ?? []).map((site): SiteForm => ({
            ...site,
            address: { ...(site.address ?? emptyAddress()) },
        })),
    };
}

const form = useForm(values());

watch(open, (shown) => {
    if (shown) {
        form.defaults(values());
        form.reset();
        form.clearErrors();
    }
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function options(prefix: string, list: string[]) {
    return list.map((value) => ({ value, text: t(`crm:${prefix}.${value}`) }));
}

function submit(): void {
    const request = form.transform(({ tax, contacts, ...rest }) => ({
        ...rest,
        // Parts the user may not change are not sent at all.
        ...(props.meta.can.tax ? { tax } : {}),
        ...(props.meta.can.contacts
            ? // The optional first contact of a new customer may stay empty.
              {
                  contacts: contacts.filter(
                      (c) =>
                          props.company ||
                          c.last_name ||
                          c.first_name ||
                          c.email,
                  ),
              }
            : {}),
    }));
    const then = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.company) {
        request.put(CompanyController.update.url(props.company.id), then);
    } else {
        request.post(CompanyController.store.url(), then);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <form class="space-y-8" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        $t(company ? 'crm:edit_title' : 'crm:new')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        $t('crm:dialog_description')
                    }}</DialogDescription>
                </DialogHeader>

                <fieldset class="space-y-4">
                    <legend class="mb-2 font-medium">
                        {{ $t('crm:section.company') }}
                    </legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <TextField
                            v-model="form.name"
                            name="name"
                            :label="$t('crm:field.name')"
                            :errors="errors"
                            required
                        />
                        <TextField
                            v-model="form.legal_name"
                            name="legal_name"
                            :label="$t('crm:field.legal_name')"
                            :errors="errors"
                        />
                        <SelectField
                            v-model="form.kind"
                            name="kind"
                            :label="$t('crm:field.kind')"
                            :errors="errors"
                            :options="options('kind', meta.kinds)"
                        />
                        <SelectField
                            v-if="company"
                            v-model="form.status"
                            name="status"
                            :label="$t('crm:field.status')"
                            :errors="errors"
                            :options="options('status', ['active', 'archived'])"
                        />
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="mb-2 font-medium">
                        {{ $t('crm:section.billing') }}
                    </legend>
                    <AddressFields
                        v-model="form.address"
                        prefix="address"
                        :errors="errors"
                    />
                </fieldset>

                <fieldset v-if="meta.can.tax" class="space-y-4">
                    <legend class="mb-2 font-medium">
                        {{ $t('crm:section.tax') }}
                    </legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            v-model="form.tax.tax_status"
                            name="tax.tax_status"
                            :label="$t('crm:field.tax_status')"
                            :errors="errors"
                            :options="options('tax', meta.taxStatuses)"
                        />
                        <TextField
                            v-model="form.tax.country_code"
                            name="tax.country_code"
                            :label="$t('crm:field.tax_country')"
                            :errors="errors"
                            :maxlength="2"
                            required
                        />
                        <TextField
                            v-model="form.tax.vat_id"
                            name="tax.vat_id"
                            :label="$t('crm:field.vat_id')"
                            :errors="errors"
                        />
                        <TextField
                            v-model="form.tax.tax_number"
                            name="tax.tax_number"
                            :label="$t('crm:field.tax_number')"
                            :errors="errors"
                        />
                        <TextField
                            v-if="company"
                            v-model="form.tax.valid_from"
                            name="tax.valid_from"
                            type="date"
                            :label="$t('crm:field.tax_valid_from')"
                            :errors="errors"
                        />
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="mb-2 font-medium">
                        {{ $t('crm:section.invoicing') }}
                    </legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            v-model="form.document_locale"
                            name="document_locale"
                            :label="$t('crm:field.document_locale')"
                            :errors="errors"
                            :options="
                                meta.locales.map((l) => ({
                                    value: l.code,
                                    text: l.name,
                                }))
                            "
                        />
                        <TextField
                            v-model="form.currency"
                            name="currency"
                            :label="$t('crm:field.currency')"
                            :errors="errors"
                            :maxlength="3"
                            required
                        />
                        <TextField
                            v-model="form.payment_days"
                            name="payment_days"
                            :label="$t('crm:field.payment_days')"
                            :errors="errors"
                            type="number"
                            required
                        />
                        <SelectField
                            v-model="form.einvoice_format"
                            name="einvoice_format"
                            :label="$t('crm:field.einvoice_format')"
                            :errors="errors"
                            :options="options('einvoice', meta.einvoiceFormats)"
                        />
                        <TextField
                            v-if="form.tax.tax_status === 'public_sector'"
                            v-model="form.leitweg_id"
                            name="leitweg_id"
                            :label="$t('crm:field.leitweg_id')"
                            :errors="errors"
                        />
                    </div>
                </fieldset>

                <fieldset v-if="meta.can.contacts" class="space-y-4">
                    <legend class="mb-2 font-medium">
                        {{ $t('crm:section.contacts') }}
                    </legend>
                    <div
                        v-for="(contact, i) in form.contacts"
                        :key="contact.id ?? i"
                        class="space-y-4 rounded-lg border p-4"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <TextField
                                v-model="contact.first_name"
                                :name="`contacts.${i}.first_name`"
                                :label="$t('crm:field.first_name')"
                                :errors="errors"
                            />
                            <TextField
                                v-model="contact.last_name"
                                :name="`contacts.${i}.last_name`"
                                :label="$t('crm:field.last_name')"
                                :errors="errors"
                            />
                            <TextField
                                v-model="contact.email"
                                :name="`contacts.${i}.email`"
                                :label="$t('crm:field.email')"
                                :errors="errors"
                                type="email"
                            />
                            <TextField
                                v-model="contact.phone"
                                :name="`contacts.${i}.phone`"
                                :label="$t('crm:field.phone')"
                                :errors="errors"
                            />
                            <TextField
                                v-model="contact.position"
                                :name="`contacts.${i}.position`"
                                :label="$t('crm:field.position')"
                                :errors="errors"
                            />
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="contact.invoice_recipient"
                                    type="checkbox"
                                />
                                {{ $t('crm:field.invoice_recipient') }}
                            </label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="form.contacts.splice(i, 1)"
                            >
                                <Trash2 class="size-4" />
                                {{ $t('crm:remove') }}
                            </Button>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="form.contacts.push(emptyContact())"
                    >
                        <Plus class="size-4" />
                        {{ $t('crm:add_contact') }}
                    </Button>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="mb-2 font-medium">
                        {{ $t('crm:section.sites') }}
                    </legend>
                    <div
                        v-for="(site, i) in form.sites"
                        :key="site.id ?? i"
                        class="space-y-4 rounded-lg border p-4"
                    >
                        <TextField
                            v-model="site.name"
                            :name="`sites.${i}.name`"
                            :label="$t('crm:field.site_name')"
                            :errors="errors"
                            required
                        />
                        <AddressFields
                            v-model="site.address"
                            :prefix="`sites.${i}.address`"
                            :errors="errors"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="form.sites.splice(i, 1)"
                        >
                            <Trash2 class="size-4" />
                            {{ $t('crm:remove') }}
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="
                            form.sites.push({
                                name: '',
                                address: emptyAddress(),
                            })
                        "
                    >
                        <Plus class="size-4" />
                        {{ $t('crm:add_site') }}
                    </Button>
                </fieldset>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">{{
                            $t('core:common.cancel')
                        }}</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-company"
                        >{{ $t('crm:save') }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
