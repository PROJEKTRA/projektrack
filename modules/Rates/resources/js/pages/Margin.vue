<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import RateController from '@/actions/Modules/Rates/Http/RateController';
import Heading from '@/components/Heading.vue';
import TextField from '@/components/TextField.vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/i18n/formats';
import { t } from '@/i18n/strings';
import { formatHours } from '@/lib/hours';

const props = defineProps<{
    from: string;
    to: string;
    rows: {
        id: string;
        number: string;
        name: string;
        currency: string;
        minutes: number;
        sales_minor: number;
        cost_minor: number;
        margin_minor: number;
        without_sales: number;
        without_cost: number;
    }[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: t('rates:margin.title'), href: RateController.margin() },
        ],
    }),
});

const from = ref(props.from);
const to = ref(props.to);
</script>

<template>
    <Head :title="$t('rates:margin.title')" />

    <div class="space-y-6 px-4 py-6">
        <Heading
            :title="$t('rates:margin.title')"
            :description="$t('rates:margin.description')"
        />
        <form
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="
                router.get(RateController.margin.url(), { from, to })
            "
        >
            <TextField
                v-model="from"
                name="from"
                type="date"
                :label="$t('rates:field.valid_from')"
                :errors="{}"
                required
            />
            <TextField
                v-model="to"
                name="to"
                type="date"
                :label="$t('rates:field.valid_to')"
                :errors="{}"
                required
            />
            <Button type="submit" variant="secondary">{{
                $t('rates:margin.show')
            }}</Button>
        </form>
        <p v-if="rows.length === 0" class="text-muted-foreground">
            {{ $t('rates:margin.empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('rates:scope.project') }}
                        </th>
                        <th
                            v-for="c in ['hours', 'sales', 'cost', 'margin']"
                            :key="c"
                            scope="col"
                            class="px-4 py-2 text-end font-medium"
                        >
                            {{ $t(`rates:margin.${c}`) }}
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('rates:margin.open') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id" class="border-t">
                        <td class="px-4 py-2">
                            <bdi>{{ r.number }}</bdi> <bdi>{{ r.name }}</bdi>
                        </td>
                        <td class="px-4 py-2 text-end tabular-nums">
                            {{ formatHours(r.minutes) }}
                        </td>
                        <td class="px-4 py-2 text-end tabular-nums">
                            <bdi>{{
                                formatMoney(r.sales_minor, r.currency)
                            }}</bdi>
                        </td>
                        <td class="px-4 py-2 text-end tabular-nums">
                            <bdi>{{
                                formatMoney(r.cost_minor, r.currency)
                            }}</bdi>
                        </td>
                        <td
                            class="px-4 py-2 text-end font-medium tabular-nums"
                            :class="
                                r.margin_minor < 0 ? 'text-destructive' : ''
                            "
                        >
                            <bdi>{{
                                formatMoney(r.margin_minor, r.currency)
                            }}</bdi>
                        </td>
                        <td class="px-4 py-2">
                            {{
                                r.without_sales || r.without_cost
                                    ? $t('rates:margin.without', {
                                          sales: r.without_sales,
                                          cost: r.without_cost,
                                      })
                                    : ''
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
