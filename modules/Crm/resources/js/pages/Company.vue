<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import CompanyController from '@/actions/Modules/Crm/Http/CompanyController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n/strings';
import CompanyDialog from '../components/CompanyDialog.vue';
import type { AddressData, CompanyData, CompanyFormMeta } from '../types';

const props = defineProps<{ company: CompanyData; form: CompanyFormMeta }>();

defineOptions({
    layout: (page: { company: CompanyData }) => ({
        breadcrumbs: [
            { title: t('crm:title'), href: CompanyController.index() },
            {
                title: page.company.name,
                href: CompanyController.show(page.company.id),
            },
        ],
    }),
});

const open = ref(false);

function lines(address: AddressData | null): string[] {
    return address
        ? [
              address.line1,
              address.line2,
              [address.postal_code, address.city].filter(Boolean).join(' '),
              [address.region, address.country_code].filter(Boolean).join(', '),
          ].filter((line): line is string => Boolean(line))
        : [];
}

const facts = computed(() => {
    const c = props.company;

    return [
        ['legal_name', c.legal_name],
        ['document_locale', c.document_locale],
        ['currency', c.currency],
        ['payment_days', String(c.payment_days)],
        ['einvoice_format', t(`crm:einvoice.${c.einvoice_format}`)],
        ['leitweg_id', c.leitweg_id],
        ['tax_status', c.tax && t(`crm:tax.${c.tax.tax_status}`)],
        ['tax_country', c.tax?.country_code],
        ['vat_id', c.tax?.vat_id],
        ['tax_number', c.tax?.tax_number],
    ].filter(([, value]) => value) as [string, string][];
});
</script>

<template>
    <Head :title="company.name" />

    <div class="space-y-8 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Heading :title="company.name" :description="company.number" />
                <div class="flex gap-2">
                    <Badge variant="secondary">{{
                        $t(`crm:kind.${company.kind}`)
                    }}</Badge>
                    <Badge
                        v-if="company.status === 'archived'"
                        variant="outline"
                        >{{ $t('crm:status.archived') }}</Badge
                    >
                </div>
            </div>
            <Button
                v-if="form.can.manage"
                data-test="edit-company"
                @click="open = true"
            >
                <Pencil class="size-4" />
                {{ $t('crm:edit') }}
            </Button>
        </div>

        <div class="grid gap-8 lg:grid-cols-2">
            <section>
                <h2 class="mb-2 font-medium">
                    {{ $t('crm:section.billing') }}
                </h2>
                <p v-for="line in lines(company.address)" :key="line">
                    <bdi>{{ line }}</bdi>
                </p>
                <p v-if="!company.address" class="text-muted-foreground">
                    {{ $t('crm:none') }}
                </p>
            </section>
            <section>
                <h2 class="mb-2 font-medium">
                    {{ $t('crm:section.invoicing') }}
                </h2>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <template v-for="[key, value] in facts" :key="key">
                        <dt class="text-muted-foreground">
                            {{ $t(`crm:field.${key}`) }}
                        </dt>
                        <dd>
                            <bdi>{{ value }}</bdi>
                        </dd>
                    </template>
                </dl>
            </section>
            <section>
                <h2 class="mb-2 font-medium">
                    {{ $t('crm:section.contacts') }}
                </h2>
                <p
                    v-if="company.contacts.length === 0"
                    class="text-muted-foreground"
                >
                    {{ $t('crm:none') }}
                </p>
                <ul class="space-y-3 text-sm">
                    <li v-for="contact in company.contacts" :key="contact.id">
                        <span class="font-medium"
                            ><bdi>{{
                                [contact.first_name, contact.last_name]
                                    .filter(Boolean)
                                    .join(' ')
                            }}</bdi></span
                        >
                        <Badge
                            v-if="contact.invoice_recipient"
                            variant="outline"
                            class="ms-2"
                            >{{ $t('crm:field.invoice_recipient') }}</Badge
                        >
                        <p class="text-muted-foreground">
                            <bdi>{{
                                [contact.position, contact.email, contact.phone]
                                    .filter(Boolean)
                                    .join(', ')
                            }}</bdi>
                        </p>
                    </li>
                </ul>
            </section>
            <section>
                <h2 class="mb-2 font-medium">{{ $t('crm:section.sites') }}</h2>
                <p
                    v-if="company.sites.length === 0"
                    class="text-muted-foreground"
                >
                    {{ $t('crm:none') }}
                </p>
                <ul class="space-y-3 text-sm">
                    <li v-for="site in company.sites" :key="site.id">
                        <span class="font-medium"
                            ><bdi>{{ site.name }}</bdi></span
                        >
                        <p class="text-muted-foreground">
                            <bdi>{{ lines(site.address).join(', ') }}</bdi>
                        </p>
                    </li>
                </ul>
            </section>
        </div>
    </div>

    <CompanyDialog v-model:open="open" :meta="form" :company="company" />
</template>
