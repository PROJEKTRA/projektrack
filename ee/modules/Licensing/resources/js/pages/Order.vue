<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    Proprietary. Not part of the Community Edition.
-->
<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InvoiceController from '@/actions/Modules/Invoices/Http/InvoiceController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatDay } from '@/i18n/formats';
import { t } from '@/i18n/strings';

type Order = {
    id: string;
    number: string;
    company: string;
    status: string;
    license_id: string;
    serial: number;
    full_seats: number;
    light_seats: number;
    instance_id: string;
    domain: string;
    invoice_id: string | null;
    token: string | null;
    starts_on: string;
    price_locked_until: string;
    ends_on: string;
};
const props = defineProps<{
    order: Order;
    plan: string;
    events: {
        id: string;
        event: string;
        at: string | null;
        actor: string | null;
    }[];
    can: { manage: boolean; sign: boolean; cancel: boolean; invoice: boolean };
}>();

defineOptions({
    layout: (page: { order: Order }) => ({
        breadcrumbs: [
            { title: t('licensing:title'), href: '/licensing' },
            {
                title: page.order.number,
                href: `/licensing/orders/${page.order.id}`,
            },
        ],
    }),
});

const form = useForm({ token: '' });
const facts = computed(() => [
    ['plan', props.plan],
    ['seats', `${props.order.full_seats} / ${props.order.light_seats}`],
    ['instance', props.order.instance_id],
    ['domain', props.order.domain],
    ['starts_on', formatDay(props.order.starts_on)],
    ['ends_on', formatDay(props.order.ends_on)],
    ['price_locked_until', formatDay(props.order.price_locked_until)],
]);
</script>

<template>
    <Head :title="order.number" />

    <div class="max-w-3xl space-y-6 px-4 py-6">
        <div>
            <Heading :title="order.number" :description="order.company" />
            <Badge variant="secondary">{{
                $t(`licensing:status.${order.status}`)
            }}</Badge>
        </div>
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <template v-for="[key, value] in facts" :key="key">
                <dt class="text-muted-foreground">
                    {{ $t(`licensing:field.${key}`) }}
                </dt>
                <dd>
                    <bdi>{{ value }}</bdi>
                </dd>
            </template>
        </dl>
        <div class="flex flex-wrap gap-2">
            <Button v-if="order.invoice_id" as-child variant="secondary"
                ><Link :href="InvoiceController.show(order.invoice_id)">{{
                    $t('licensing:invoice')
                }}</Link></Button
            >
            <Button v-if="can.sign" as-child variant="secondary"
                ><a :href="`/licensing/orders/${order.id}/payload`" download>{{
                    $t('licensing:payload')
                }}</a></Button
            >
        </div>
        <div v-if="can.cancel || can.invoice" class="flex gap-2">
            <Button
                v-if="can.invoice"
                variant="secondary"
                @click="router.post(`/licensing/orders/${order.id}/invoice`)"
                >{{ $t('licensing:invoice_again') }}</Button
            >
            <Button
                v-if="can.cancel"
                variant="destructive"
                @click="router.post(`/licensing/orders/${order.id}/cancel`)"
                >{{ $t('licensing:cancel') }}</Button
            >
        </div>
        <InputError
            :message="
                ($page.props.errors as Record<string, string | undefined>).order
            "
        />
        <form
            v-if="can.sign && order.status !== 'active'"
            class="space-y-2"
            @submit.prevent="
                form.post(`/licensing/orders/${order.id}/license`, {
                    preserveScroll: true,
                    onSuccess: () => form.reset(),
                })
            "
        >
            <label class="text-sm font-medium" for="token">{{
                $t('licensing:field.token')
            }}</label>
            <textarea
                id="token"
                v-model="form.token"
                rows="4"
                class="block w-full rounded-input border border-input bg-transparent p-3 font-mono text-xs"
                dir="ltr"
                required
            />
            <InputError :message="form.errors.token" />
            <Button type="submit" :disabled="form.processing">{{
                $t('licensing:enter')
            }}</Button>
        </form>
        <section v-if="order.token" class="space-y-1">
            <h2 class="font-medium">{{ $t('licensing:license') }}</h2>
            <code class="block text-xs break-all" dir="ltr">{{
                order.token
            }}</code>
        </section>
        <section class="space-y-1">
            <h2 class="font-medium">{{ $t('licensing:history') }}</h2>
            <ul class="text-sm">
                <li v-for="e in events" :key="e.id">
                    <bdi>{{
                        `${e.at ? formatDateTime(e.at) : ''} ${$t(`licensing:event.${e.event}`)} ${e.actor ?? ''}`
                    }}</bdi>
                </li>
            </ul>
        </section>
    </div>
</template>
