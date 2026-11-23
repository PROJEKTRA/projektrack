<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ApprovalController from '@/actions/Modules/Timesheets/Http/ApprovalController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDay } from '@/i18n/formats';
import { t } from '@/i18n/strings';
import { formatHours } from '@/lib/hours';

const props = defineProps<{
    entries: {
        id: string;
        date: string;
        user: string;
        project: string;
        package: string;
        duration_minutes: number;
        billable_minutes: number;
        description: string | null;
    }[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('timesheets:approvals.title'),
                href: ApprovalController.index(),
            },
        ],
    }),
});

const chosen = ref<string[]>([]);
const form = useForm({ ids: [] as string[], decision: 'approve', reason: '' });
const all = computed({
    get: () =>
        props.entries.length > 0 &&
        chosen.value.length === props.entries.length,
    set: (on: boolean) =>
        (chosen.value = on ? props.entries.map((e) => e.id) : []),
});

function decide(decision: string): void {
    form.ids = chosen.value;
    form.decision = decision;
    form.post(ApprovalController.decide.url(), {
        preserveScroll: true,
        onSuccess: () => {
            chosen.value = [];
            form.reset();
        },
    });
}
</script>

<template>
    <Head :title="$t('timesheets:approvals.title')" />

    <div class="space-y-6 px-4 py-6">
        <Heading
            :title="$t('timesheets:approvals.title')"
            :description="$t('timesheets:approvals.description')"
        />

        <p v-if="entries.length === 0" class="text-muted-foreground">
            {{ $t('timesheets:approvals.empty') }}
        </p>
        <template v-else>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-3 py-2">
                                <input
                                    v-model="all"
                                    type="checkbox"
                                    :aria-label="
                                        $t('timesheets:approvals.select_all')
                                    "
                                    data-test="select-all"
                                />
                            </th>
                            <th
                                v-for="column in [
                                    'user',
                                    'date',
                                    'project',
                                    'package',
                                    'hours',
                                    'billable',
                                    'note',
                                ]"
                                :key="column"
                                scope="col"
                                class="px-3 py-2 text-start font-medium"
                            >
                                {{ $t(`timesheets:field.${column}`) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in entries" :key="e.id" class="border-t">
                            <td class="px-3 py-2 text-center">
                                <input
                                    v-model="chosen"
                                    type="checkbox"
                                    :value="e.id"
                                    :aria-label="
                                        $t('timesheets:approvals.select')
                                    "
                                />
                            </td>
                            <td class="px-3 py-2">
                                <bdi>{{ e.user }}</bdi>
                            </td>
                            <td class="px-3 py-2">
                                <bdi>{{ formatDay(e.date) }}</bdi>
                            </td>
                            <td class="px-3 py-2">
                                <bdi>{{ e.project }}</bdi>
                            </td>
                            <td class="px-3 py-2">
                                <bdi>{{ e.package }}</bdi>
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ formatHours(e.duration_minutes) }}
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ formatHours(e.billable_minutes) }}
                            </td>
                            <td class="px-3 py-2">
                                <bdi>{{ e.description }}</bdi>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-start gap-2">
                <Button
                    :disabled="chosen.length === 0 || form.processing"
                    data-test="approve"
                    @click="decide('approve')"
                    >{{ $t('timesheets:approvals.approve') }}</Button
                >
                <div class="grid gap-1">
                    <Input
                        v-model="form.reason"
                        class="w-72"
                        maxlength="500"
                        dir="auto"
                        :placeholder="$t('timesheets:approvals.reason')"
                        :aria-label="$t('timesheets:approvals.reason')"
                    />
                    <InputError :message="form.errors.reason" />
                </div>
                <Button
                    variant="secondary"
                    :disabled="chosen.length === 0 || form.processing"
                    data-test="reject"
                    @click="decide('reject')"
                    >{{ $t('timesheets:approvals.reject') }}</Button
                >
            </div>
        </template>
    </div>
</template>
