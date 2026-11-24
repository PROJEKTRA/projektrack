<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    Proprietary. Not part of the Community Edition.
-->
<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDay } from '@/i18n/formats';
import { t } from '@/i18n/strings';

type Plan = {
    id: string;
    name: string;
    currency: string;
    active: boolean;
    term_months: number;
    full_price: string;
    light_price: string;
};
const props = defineProps<{
    orders: {
        id: string;
        number: string;
        company: string;
        status: string;
        full_seats: number;
        light_seats: number;
        ends_on: string;
    }[];
    plans: Plan[];
    companies: { value: string; text: string }[];
    today: string;
    can: { plans: boolean; orders: boolean };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: t('licensing:title'), href: '/licensing' }],
    }),
});

const orderOpen = ref(false);
const planOpen = ref(false);
const order = useForm({
    plan_id: props.plans.find((p) => p.active)?.id ?? '',
    company_id: props.companies[0]?.value ?? '',
    full_seats: 1,
    light_seats: 0,
    instance_id: '',
    domain: '',
    customer_name: '',
    starts_on: props.today,
});
const plan = useForm({
    key: 'founding_customer_business',
    name: 'Founding Customer',
    edition: 'business',
    term_months: 12,
    price_lock_months: 24,
    full_price: '',
    light_price: '',
    onboarding_price: '0',
    currency: 'EUR',
});
// The first plan and customer may have been added after the page opened.
function newOrder(): void {
    order.plan_id ||= props.plans.find((p) => p.active)?.id ?? '';
    order.company_id ||= props.companies[0]?.value ?? '';
    orderOpen.value = true;
}
const orderErrors = computed(
    () => order.errors as Record<string, string | undefined>,
);
const planErrors = computed(
    () => plan.errors as Record<string, string | undefined>,
);
</script>

<template>
    <Head :title="$t('licensing:title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('licensing:title')"
                :description="$t('licensing:description')"
            />
            <div class="flex gap-2">
                <Button
                    v-if="can.plans"
                    variant="secondary"
                    @click="planOpen = true"
                    >{{ $t('licensing:new_plan') }}</Button
                >
                <Button
                    v-if="can.orders"
                    :disabled="!plans.some((p) => p.active)"
                    @click="newOrder"
                    >{{ $t('licensing:new_order') }}</Button
                >
            </div>
        </div>
        <p v-if="orders.length === 0" class="text-muted-foreground">
            {{ $t('licensing:empty') }}
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
                                'seats',
                                'ends_on',
                            ]"
                            :key="c"
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t(`licensing:field.${c}`) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders" :key="o.id" class="border-t">
                        <td class="px-4 py-2">
                            <Link
                                :href="`/licensing/orders/${o.id}`"
                                class="text-primary underline-offset-4 hover:underline"
                                ><bdi>{{ o.number }}</bdi></Link
                            >
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{ o.company }}</bdi>
                        </td>
                        <td class="px-4 py-2">
                            <Badge variant="secondary">{{
                                $t(`licensing:status.${o.status}`)
                            }}</Badge>
                        </td>
                        <td class="px-4 py-2 tabular-nums">
                            {{ `${o.full_seats} / ${o.light_seats}` }}
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{ formatDay(o.ends_on) }}</bdi>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <section class="space-y-2">
            <h2 class="font-medium">{{ $t('licensing:plans') }}</h2>
            <ul class="text-sm">
                <li v-for="p in plans" :key="p.id">
                    <bdi>{{
                        `${p.name}: ${p.full_price} / ${p.light_price} ${p.currency}`
                    }}</bdi>
                    <Button
                        v-if="can.plans"
                        size="sm"
                        variant="ghost"
                        @click="router.post(`/licensing/plans/${p.id}/retire`)"
                        >{{
                            p.active
                                ? $t('licensing:retire')
                                : $t('licensing:offer')
                        }}</Button
                    >
                </li>
            </ul>
        </section>
    </div>

    <FormDialog
        v-model:open="orderOpen"
        :title="$t('licensing:new_order')"
        :save="$t('licensing:confirm')"
        :processing="order.processing"
        @submit="order.post('/licensing/orders')"
    >
        <SelectField
            v-model="order.company_id"
            name="company_id"
            :label="$t('licensing:field.company')"
            :errors="orderErrors"
            :options="companies"
        />
        <SelectField
            v-model="order.plan_id"
            name="plan_id"
            :label="$t('licensing:field.plan')"
            :errors="orderErrors"
            :options="
                plans
                    .filter((p) => p.active)
                    .map((p) => ({ value: p.id, text: p.name }))
            "
        />
        <TextField
            v-model="order.customer_name"
            name="customer_name"
            :label="$t('licensing:field.customer_name')"
            :errors="orderErrors"
            required
        />
        <TextField
            v-model="order.full_seats"
            name="full_seats"
            type="number"
            :label="$t('licensing:field.full')"
            :errors="orderErrors"
            required
        />
        <TextField
            v-model="order.light_seats"
            name="light_seats"
            type="number"
            :label="$t('licensing:field.light')"
            :errors="orderErrors"
            required
        />
        <TextField
            v-model="order.instance_id"
            name="instance_id"
            :label="$t('licensing:field.instance')"
            :errors="orderErrors"
            required
        />
        <TextField
            v-model="order.domain"
            name="domain"
            :label="$t('licensing:field.domain')"
            :errors="orderErrors"
            required
        />
        <TextField
            v-model="order.starts_on"
            name="starts_on"
            type="date"
            :label="$t('licensing:field.starts_on')"
            :errors="orderErrors"
            required
        />
    </FormDialog>
    <FormDialog
        v-model:open="planOpen"
        :title="$t('licensing:new_plan')"
        :save="$t('licensing:save')"
        :processing="plan.processing"
        @submit="
            plan.post('/licensing/plans', {
                onSuccess: () => (planOpen = false),
            })
        "
    >
        <TextField
            v-model="plan.key"
            name="key"
            :label="$t('licensing:field.key')"
            :errors="planErrors"
            required
        />
        <TextField
            v-model="plan.name"
            name="name"
            :label="$t('licensing:field.name')"
            :errors="planErrors"
            required
        />
        <SelectField
            v-model="plan.edition"
            name="edition"
            :label="$t('licensing:field.edition')"
            :errors="planErrors"
            :options="
                ['business', 'enterprise', 'partner'].map((value) => ({
                    value,
                    text: value,
                }))
            "
        />
        <TextField
            v-model="plan.term_months"
            name="term_months"
            type="number"
            :label="$t('licensing:field.term')"
            :errors="planErrors"
            required
        />
        <TextField
            v-model="plan.price_lock_months"
            name="price_lock_months"
            type="number"
            :label="$t('licensing:field.lock')"
            :errors="planErrors"
            required
        />
        <TextField
            v-model="plan.full_price"
            name="full_price"
            :label="$t('licensing:field.full_price')"
            :errors="planErrors"
            required
        />
        <TextField
            v-model="plan.light_price"
            name="light_price"
            :label="$t('licensing:field.light_price')"
            :errors="planErrors"
            required
        />
        <TextField
            v-model="plan.onboarding_price"
            name="onboarding_price"
            :label="$t('licensing:field.onboarding_price')"
            :errors="planErrors"
            required
        />
        <TextField
            v-model="plan.currency"
            name="currency"
            :label="$t('licensing:field.currency')"
            :errors="planErrors"
            :maxlength="3"
            required
        />
    </FormDialog>
</template>
