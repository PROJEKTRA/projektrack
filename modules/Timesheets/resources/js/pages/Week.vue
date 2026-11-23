<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import TimesheetController from '@/actions/Modules/Timesheets/Http/TimesheetController';
import Heading from '@/components/Heading.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDay } from '@/i18n/formats';
import { t } from '@/i18n/strings';
import { formatHours, parseHours } from '@/lib/hours';
import { requestJson } from '@/lib/requestJson';

type Entry = {
    id: string;
    work_package_id: string;
    date: string;
    duration_minutes: number;
    billable_minutes: number;
    description: string | null;
    status: string;
    reject_reason: string | null;
    correction: boolean;
};
type Package = { id: string; code: string; name: string; project: string };
type Cell = {
    text: string;
    saved: number;
    note: string;
    fixed: number;
    reason: string | null;
    error: string | null;
    busy: boolean;
};

const props = defineProps<{
    from: string;
    days: { date: string; locked: boolean }[];
    packages: Package[];
    bookable: string[];
    entries: Entry[];
    dayWarning: number;
    canBook: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: t('timesheets:title'), href: TimesheetController.index() },
        ],
    }),
});

const rows = ref<string[]>([]);
const cells = reactive<Record<string, Cell>>({});
const adding = ref('');
const selected = ref<string | null>(null);
const undo = ref<{ key: string; minutes: number; note: string } | null>(null);

const key = (packageId: string, date: string) => `${packageId}|${date}`;

function load(): void {
    rows.value = [...new Set(props.entries.map((e) => e.work_package_id))];

    // Few packages: all of them at once, so nothing has to be added first.
    if (props.packages.length <= 8) {
        rows.value = props.packages.map((p) => p.id);
    }

    for (const k of Object.keys(cells)) {
        delete cells[k];
    }

    for (const p of props.packages) {
        for (const day of props.days) {
            const own = props.entries.filter(
                (e) => e.work_package_id === p.id && e.date === day.date,
            );
            const edit = own.find(
                (e) =>
                    ['draft', 'rejected'].includes(e.status) && !e.correction,
            );
            cells[key(p.id, day.date)] = {
                text: edit ? formatHours(edit.duration_minutes) : '',
                saved: edit?.duration_minutes ?? 0,
                note: edit?.description ?? '',
                fixed: own
                    .filter((e) => e !== edit)
                    .reduce((sum, e) => sum + e.duration_minutes, 0),
                reason: edit?.reject_reason ?? null,
                error: null,
                busy: false,
            };
        }
    }

    undo.value = null;
    selected.value = null;
}

watch(() => props.entries, load, { immediate: true });

const shown = computed(() =>
    props.packages.filter((p) => rows.value.includes(p.id)),
);
const addable = computed(() =>
    props.packages.filter((p) => !rows.value.includes(p.id)),
);
const minutesOf = (c: Cell | undefined) => (c ? c.saved + c.fixed : 0);
const dayTotal = (date: string) =>
    props.packages.reduce(
        (sum, p) => sum + minutesOf(cells[key(p.id, date)]),
        0,
    );
const rowTotal = (id: string) =>
    props.days.reduce((sum, d) => sum + minutesOf(cells[key(id, d.date)]), 0);
const weekTotal = computed(() =>
    props.days.reduce((sum, d) => sum + dayTotal(d.date), 0),
);
const anyDraft = computed(() => Object.values(cells).some((c) => c.saved > 0));

function parse(text: string): number | null {
    return text.trim() === '' ? 0 : parseHours(text);
}

async function save(k: string, keepUndo = true): Promise<void> {
    const cell = cells[k];
    const minutes = parse(cell.text);

    if (minutes === null || minutes > 1440) {
        cell.error = t('timesheets:invalid');

        return;
    }

    const [work_package_id, date] = k.split('|');
    const before = { key: k, minutes: cell.saved, note: cell.note };
    cell.busy = true;
    const result = await requestJson<{ entry: Entry | null }>(
        'PUT',
        TimesheetController.book.url(),
        {
            work_package_id,
            date,
            minutes,
            description: cell.note || null,
        },
    );
    cell.busy = false;

    if (!result.ok) {
        cell.error = result.message ?? t('timesheets:save_failed');
        selected.value = k;

        return;
    }

    cell.error = null;
    cell.reason = null;

    if (keepUndo && before.minutes !== minutes) {
        undo.value = before;
    }

    cell.saved = minutes;
    cell.text = minutes === 0 ? '' : formatHours(minutes);
}

function onChange(k: string): void {
    if (parse(cells[k].text) !== cells[k].saved) {
        void save(k);
    }
}

async function undoLast(): Promise<void> {
    const last = undo.value;

    if (last) {
        undo.value = null;
        cells[last.key].text = last.minutes ? formatHours(last.minutes) : '';
        cells[last.key].note = last.note;
        await save(last.key, false);
    }
}

/** Arrow keys and Enter move between cells. */
function onKey(event: KeyboardEvent, row: number, column: number): void {
    const input = event.target as HTMLInputElement;
    const atEdge = input.selectionStart === input.selectionEnd;
    const rtl = document.documentElement.dir === 'rtl' ? -1 : 1;
    let [r, c] = [row, column];

    if (event.key === 'ArrowUp') {
        r--;
    } else if (event.key === 'ArrowDown' || event.key === 'Enter') {
        r++;
    } else if (
        event.key === 'ArrowLeft' &&
        atEdge &&
        input.selectionStart === 0
    ) {
        c -= rtl;
    } else if (
        event.key === 'ArrowRight' &&
        atEdge &&
        input.selectionStart === input.value.length
    ) {
        c += rtl;
    } else {
        return;
    }

    event.preventDefault();
    document
        .querySelector<HTMLInputElement>(`[data-cell="${r}-${c}"]`)
        ?.focus();
}

function addRow(id: string): void {
    if (id) {
        rows.value.push(id);
        adding.value = '';
    }
}

function week(offset: number): string {
    const day = new Date(`${props.from}T00:00:00Z`);
    day.setUTCDate(day.getUTCDate() + offset * 7);

    return day.toISOString().slice(0, 10);
}

const current = computed(() => (selected.value ? cells[selected.value] : null));
</script>

<template>
    <Head :title="$t('timesheets:title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('timesheets:title')"
                :description="$t('timesheets:description')"
            />
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="secondary"
                    ><Link
                        :href="
                            TimesheetController.index({
                                query: { date: week(-1) },
                            })
                        "
                        >{{ $t('timesheets:previous') }}</Link
                    ></Button
                >
                <Button as-child variant="secondary"
                    ><Link :href="TimesheetController.index()">{{
                        $t('timesheets:today')
                    }}</Link></Button
                >
                <Button as-child variant="secondary"
                    ><Link
                        :href="
                            TimesheetController.index({
                                query: { date: week(1) },
                            })
                        "
                        >{{ $t('timesheets:next') }}</Link
                    ></Button
                >
                <Button
                    v-if="canBook"
                    data-test="submit-week"
                    :disabled="!anyDraft"
                    @click="
                        router.post(TimesheetController.submit.url(), {
                            date: from,
                        })
                    "
                    >{{ $t('timesheets:submit') }}</Button
                >
            </div>
        </div>

        <p v-if="packages.length === 0" class="text-muted-foreground">
            {{ $t('timesheets:empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            class="px-3 py-2 text-start font-medium"
                        >
                            {{ $t('timesheets:work_package') }}
                        </th>
                        <th
                            v-for="day in days"
                            :key="day.date"
                            scope="col"
                            class="px-1 py-2 text-center font-medium"
                        >
                            <bdi>{{ formatDay(day.date) }}</bdi>
                        </th>
                        <th scope="col" class="px-3 py-2 text-end font-medium">
                            {{ $t('timesheets:total') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, r) in shown" :key="p.id" class="border-t">
                        <th
                            scope="row"
                            class="px-3 py-2 text-start font-normal"
                        >
                            <span class="block font-medium"
                                ><bdi>{{ p.code }}</bdi>
                                <bdi>{{ p.name }}</bdi></span
                            >
                            <bdi class="text-xs text-muted-foreground">{{
                                p.project
                            }}</bdi>
                        </th>
                        <td
                            v-for="(day, c) in days"
                            :key="day.date"
                            class="min-w-20 px-1 py-2 text-center"
                        >
                            <Input
                                v-model="cells[key(p.id, day.date)].text"
                                inputmode="decimal"
                                class="h-9 text-center tabular-nums"
                                :class="
                                    cells[key(p.id, day.date)].reason
                                        ? 'border-destructive'
                                        : ''
                                "
                                :data-cell="`${r}-${c}`"
                                :data-test="`cell-${p.code}-${day.date}`"
                                :aria-label="
                                    $t('timesheets:cell_label', {
                                        package: p.name,
                                        date: formatDay(day.date),
                                    })
                                "
                                :aria-invalid="
                                    cells[key(p.id, day.date)].error
                                        ? true
                                        : undefined
                                "
                                :disabled="
                                    !canBook ||
                                    day.locked ||
                                    !bookable.includes(p.id)
                                "
                                @focus="selected = key(p.id, day.date)"
                                @change="onChange(key(p.id, day.date))"
                                @keydown="onKey($event, r, c)"
                            />
                            <span
                                v-if="cells[key(p.id, day.date)].fixed"
                                class="text-xs text-muted-foreground tabular-nums"
                                :title="
                                    $t('timesheets:locked_value', {
                                        hours: formatHours(
                                            cells[key(p.id, day.date)].fixed,
                                        ),
                                    })
                                "
                                >{{
                                    formatHours(
                                        cells[key(p.id, day.date)].fixed,
                                    )
                                }}</span
                            >
                        </td>
                        <td class="px-3 py-2 text-end tabular-nums">
                            {{ formatHours(rowTotal(p.id)) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t font-medium">
                    <tr>
                        <th scope="row" class="px-3 py-2 text-start">
                            {{ $t('timesheets:total') }}
                        </th>
                        <td
                            v-for="day in days"
                            :key="day.date"
                            class="px-1 py-2 text-center tabular-nums"
                            :class="
                                dayTotal(day.date) > dayWarning
                                    ? 'text-destructive'
                                    : ''
                            "
                            :title="
                                dayTotal(day.date) > dayWarning
                                    ? $t('timesheets:long_day', {
                                          hours: formatHours(dayWarning),
                                      })
                                    : undefined
                            "
                            :data-test="`total-${day.date}`"
                        >
                            {{ formatHours(dayTotal(day.date)) }}
                        </td>
                        <td
                            class="px-3 py-2 text-end tabular-nums"
                            data-test="week-total"
                        >
                            {{ formatHours(weekTotal) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div
            v-if="current && selected"
            class="max-w-xl space-y-2"
            role="status"
        >
            <p v-if="current.error" class="text-sm text-destructive">
                {{ current.error }}
            </p>
            <p v-if="current.reason" class="text-sm text-destructive">
                <bdi>{{
                    $t('timesheets:rejected_note', { reason: current.reason })
                }}</bdi>
            </p>
            <Input
                v-model="current.note"
                dir="auto"
                maxlength="2000"
                :placeholder="$t('timesheets:note')"
                :aria-label="$t('timesheets:note')"
                data-test="note"
                @change="current.text && save(selected)"
            />
        </div>

        <div class="flex flex-wrap items-end gap-4">
            <SelectField
                v-if="addable.length > 0 && canBook"
                v-model="adding"
                name="add"
                :label="$t('timesheets:add_row')"
                :errors="{}"
                :options="[
                    { value: '', text: '' },
                    ...addable.map((p) => ({
                        value: p.id,
                        text: `${p.project}: ${p.code} ${p.name}`,
                    })),
                ]"
                @update:model-value="addRow"
            />
            <Button
                v-if="undo"
                variant="ghost"
                data-test="undo"
                @click="undoLast"
                >{{ $t('timesheets:undo') }}</Button
            >
        </div>
    </div>
</template>
