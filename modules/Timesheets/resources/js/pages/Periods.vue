<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PeriodController from '@/actions/Modules/Timesheets/Http/PeriodController';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import TextField from '@/components/TextField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDay } from '@/i18n/formats';
import { t } from '@/i18n/strings';
import { formatHours, parseHours } from '@/lib/hours';

type Entry = {
    id: string;
    user_id: string;
    user: string;
    date: string;
    project: string;
    package: string;
    duration_minutes: number;
    billable_minutes: number;
    description: string | null;
    status: string;
    corrects_id: string | null;
    correction_reason: string | null;
};

const props = defineProps<{
    month: string;
    previous: string;
    next: string;
    locked: boolean;
    canCorrect: boolean;
    me: string;
    entries: Entry[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('timesheets:periods.title'),
                href: PeriodController.index(),
            },
        ],
    }),
});

const unapproved = computed(
    () => props.entries.filter((e) => e.status !== 'approved').length,
);
const lockForm = useForm({ month: '', locked: true, reason: '' });
const reopening = ref(false);
const lockErrors = computed(
    () => lockForm.errors as Record<string, string | undefined>,
);
const correcting = ref<Entry | null>(null);
const correctOpen = ref(false);
const correction = useForm({ minutes: '', reason: '' });
const errors = computed(
    () => correction.errors as Record<string, string | undefined>,
);

function setLocked(locked: boolean): void {
    lockForm.month = props.month;
    lockForm.locked = locked;
    lockForm.post(PeriodController.update.url(), {
        preserveScroll: true,
        onSuccess: () => ((reopening.value = false), lockForm.reset()),
    });
}

function open(entry: Entry): void {
    correcting.value = entry;
    correction.reset();
    correction.clearErrors();
    correctOpen.value = true;
}

function submit(): void {
    if (correcting.value) {
        correction
            .transform((d) => ({
                ...d,
                minutes: parseHours(d.minutes) ?? d.minutes,
            }))
            .post(PeriodController.correct.url(correcting.value.id), {
                preserveScroll: true,
                onSuccess: () => (correctOpen.value = false),
            });
    }
}
</script>

<template>
    <Head :title="$t('timesheets:periods.title')" />

    <div class="space-y-6 px-4 py-6">
        <Heading
            :title="`${$t('timesheets:periods.title')} ${month}`"
            :description="$t('timesheets:periods.description')"
        />
        <div class="flex flex-wrap items-center gap-2">
            <Button as-child variant="secondary"
                ><Link
                    :href="
                        PeriodController.index({ query: { month: previous } })
                    "
                    >{{ $t('timesheets:periods.previous') }}</Link
                ></Button
            >
            <Button as-child variant="secondary"
                ><Link
                    :href="PeriodController.index({ query: { month: next } })"
                    >{{ $t('timesheets:periods.next') }}</Link
                ></Button
            >
            <Badge :variant="locked ? 'default' : 'outline'">{{
                $t(
                    locked
                        ? 'timesheets:periods.locked'
                        : 'timesheets:periods.open',
                )
            }}</Badge>
            <Button v-if="!locked" data-test="lock" @click="setLocked(true)">{{
                $t('timesheets:periods.lock')
            }}</Button>
            <Button
                v-else
                variant="secondary"
                data-test="reopen"
                @click="reopening = true"
                >{{ $t('timesheets:periods.reopen') }}</Button
            >
        </div>
        <p class="text-sm">
            {{ $t('timesheets:periods.unapproved', { count: unapproved }) }}
        </p>

        <p v-if="entries.length === 0" class="text-muted-foreground">
            {{ $t('timesheets:periods.empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            v-for="column in [
                                'date',
                                'user',
                                'project',
                                'package',
                                'hours',
                                'billable',
                                'status',
                                'note',
                            ]"
                            :key="column"
                            scope="col"
                            class="px-3 py-2 text-start font-medium"
                        >
                            {{ $t(`timesheets:field.${column}`) }}
                        </th>
                        <th scope="col">
                            <span class="sr-only">{{
                                $t('timesheets:correct')
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in entries" :key="e.id" class="border-t">
                        <td class="px-3 py-2">
                            <bdi>{{ formatDay(e.date) }}</bdi>
                        </td>
                        <td class="px-3 py-2">
                            <bdi>{{ e.user }}</bdi>
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
                            {{ $t(`timesheets:status.${e.status}`) }}
                        </td>
                        <td class="px-3 py-2">
                            <bdi>{{
                                e.corrects_id
                                    ? $t('timesheets:correction', {
                                          reason: e.correction_reason ?? '',
                                      })
                                    : e.description
                            }}</bdi>
                        </td>
                        <td class="px-3 py-2 text-end">
                            <Button
                                v-if="
                                    canCorrect &&
                                    !locked &&
                                    e.status === 'approved' &&
                                    !e.corrects_id &&
                                    e.user_id !== me
                                "
                                size="sm"
                                variant="ghost"
                                @click="open(e)"
                                >{{ $t('timesheets:correct') }}</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <FormDialog
        v-model:open="reopening"
        :title="$t('timesheets:periods.reopen')"
        :save="$t('timesheets:periods.reopen')"
        :processing="lockForm.processing"
        @submit="setLocked(false)"
    >
        <TextField
            v-model="lockForm.reason"
            name="reason"
            :label="$t('timesheets:approvals.reason')"
            :errors="lockErrors"
            required
        />
    </FormDialog>
    <FormDialog
        v-model:open="correctOpen"
        :title="$t('timesheets:correct_title')"
        :description="$t('timesheets:correct_description')"
        :save="$t('timesheets:save')"
        :processing="correction.processing"
        @submit="submit"
    >
        <TextField
            v-model="correction.minutes"
            name="minutes"
            :label="$t('timesheets:correct_hours')"
            :errors="errors"
            required
        />
        <TextField
            v-model="correction.reason"
            name="reason"
            :label="$t('timesheets:approvals.reason')"
            :errors="errors"
            required
        />
    </FormDialog>
</template>
