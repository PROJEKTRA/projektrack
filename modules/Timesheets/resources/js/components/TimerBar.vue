<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import TimerController from '@/actions/Modules/Timesheets/Http/TimerController';
import InputError from '@/components/InputError.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatHours } from '@/lib/hours';

const props = defineProps<{
    timer: { work_package_id: string; started_at: string; name: string } | null;
    options: { value: string; text: string }[];
}>();

const start = useForm({ work_package_id: props.options[0]?.value ?? '' });
const stop = useForm({ description: '' });
const startErrors = computed(
    () => start.errors as Record<string, string | undefined>,
);
const stopError = computed(
    () => Object.values(stop.errors as Record<string, string>)[0],
);
const now = ref(Date.now());
let tick: number | undefined;

onMounted(
    () => (tick = window.setInterval(() => (now.value = Date.now()), 10_000)),
);
onBeforeUnmount(() => window.clearInterval(tick));

const elapsed = computed(() =>
    props.timer
        ? formatHours(
              Math.max(
                  0,
                  Math.floor(
                      (now.value - Date.parse(props.timer.started_at)) / 60_000,
                  ),
              ),
          )
        : '',
);
</script>

<template>
    <div class="rounded-lg border p-4">
        <form
            v-if="timer"
            class="flex flex-wrap items-center gap-3"
            @submit.prevent="
                stop.post(TimerController.stop.url(), {
                    preserveScroll: true,
                    onSuccess: () => stop.reset(),
                })
            "
        >
            <bdi class="font-medium">{{ timer.name }}</bdi>
            <span class="text-lg tabular-nums" role="timer" data-test="timer">{{
                elapsed
            }}</span>
            <Input
                v-model="stop.description"
                class="max-w-xs"
                dir="auto"
                maxlength="2000"
                :placeholder="$t('timesheets:note')"
                :aria-label="$t('timesheets:note')"
            />
            <Button
                type="submit"
                :disabled="stop.processing"
                data-test="timer-stop"
                >{{ $t('timesheets:timer.stop') }}</Button
            >
            <Button
                type="button"
                variant="ghost"
                @click="
                    router.delete(TimerController.discard.url(), {
                        preserveScroll: true,
                    })
                "
                >{{ $t('timesheets:timer.discard') }}</Button
            >
            <InputError :message="stopError" />
        </form>
        <form
            v-else
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="
                start.post(TimerController.start.url(), {
                    preserveScroll: true,
                })
            "
        >
            <SelectField
                v-model="start.work_package_id"
                name="work_package_id"
                :label="$t('timesheets:work_package')"
                :errors="startErrors"
                :options="options"
            />
            <Button
                type="submit"
                :disabled="start.processing"
                data-test="timer-start"
                >{{ $t('timesheets:timer.start') }}</Button
            >
        </form>
    </div>
</template>
