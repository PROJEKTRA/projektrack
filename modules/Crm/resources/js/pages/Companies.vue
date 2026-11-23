<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import CompanyController from '@/actions/Modules/Crm/Http/CompanyController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/i18n/strings';
import CompanyDialog from '../components/CompanyDialog.vue';
import type { CompanyFormMeta } from '../types';

type Row = {
    id: string;
    number: string;
    name: string;
    kind: string;
    city: string | null;
    country: string | null;
};

const props = defineProps<{
    companies: {
        data: Row[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    search: string;
    status: 'active' | 'archived';
    form: CompanyFormMeta;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: t('crm:title'), href: CompanyController.index() },
        ],
    }),
});

const open = ref(false);
const search = ref(props.search);

function find(status: string): void {
    router.get(
        CompanyController.index.url(),
        { search: search.value || undefined, status },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="$t('crm:title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('crm:title')"
                :description="$t('crm:description')"
            />
            <Button
                v-if="form.can.create"
                data-test="new-company"
                @click="open = true"
            >
                <Plus class="size-4" />
                {{ $t('crm:new') }}
            </Button>
        </div>

        <form
            class="flex flex-wrap gap-2"
            role="search"
            @submit.prevent="find(status)"
        >
            <Input
                v-model="search"
                type="search"
                class="max-w-sm"
                :placeholder="$t('crm:search')"
                :aria-label="$t('crm:search')"
            />
            <Button
                v-for="s in ['active', 'archived'] as const"
                :key="s"
                type="button"
                :variant="s === status ? 'secondary' : 'ghost'"
                :aria-pressed="s === status"
                @click="find(s)"
            >
                {{ $t(`crm:show_${s}`) }}
            </Button>
        </form>

        <p v-if="companies.data.length === 0" class="text-muted-foreground">
            {{ $t('crm:empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            v-for="column in ['number', 'name', 'kind', 'city']"
                            :key="column"
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t(`crm:field.${column}`) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="company in companies.data"
                        :key="company.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <bdi class="font-mono text-xs">{{
                                company.number
                            }}</bdi>
                        </td>
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="CompanyController.show(company.id)"
                                class="text-primary underline-offset-4 hover:underline"
                                dir="auto"
                                >{{ company.name }}</Link
                            >
                        </td>
                        <td class="px-4 py-3">
                            {{ $t(`crm:kind.${company.kind}`) }}
                        </td>
                        <td class="px-4 py-3">
                            <bdi>{{
                                [company.city, company.country]
                                    .filter(Boolean)
                                    .join(', ')
                            }}</bdi>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="companies.prev_page_url || companies.next_page_url"
            class="flex gap-2"
        >
            <Button v-if="companies.prev_page_url" as-child variant="secondary">
                <Link :href="companies.prev_page_url">{{
                    $t('crm:previous')
                }}</Link>
            </Button>
            <Button v-if="companies.next_page_url" as-child variant="secondary">
                <Link :href="companies.next_page_url">{{
                    $t('crm:next')
                }}</Link>
            </Button>
        </div>
    </div>

    <CompanyDialog v-model:open="open" :meta="form" />
</template>
