<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import RateController from '@/actions/Modules/Rates/Http/RateController';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { Button } from '@/components/ui/button';
import { formatDay } from '@/i18n/formats';
import { t } from '@/i18n/strings';

type Rule = {
    id: string;
    scope: string;
    name: string;
    rate: string;
    currency: string;
    valid_from: string;
    valid_to: string | null;
};

const props = defineProps<{
    rules: Rule[];
    scopes: string[];
    choices: Record<string, { value: string; text: string }[]>;
    currency: string;
    today: string;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: t('rates:title'), href: RateController.index() },
        ],
    }),
});

const open = ref(false);
const ending = ref<Rule | null>(null);
const endOpen = ref(false);
const form = useForm({
    scope: 'tenant',
    scope_id: '',
    rate: '',
    currency: props.currency,
    valid_from: props.today,
});
const endForm = useForm({ valid_to: props.today });
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const endErrors = computed(
    () => endForm.errors as Record<string, string | undefined>,
);

function create(): void {
    form.reset();
    form.clearErrors();
    open.value = true;
}

function submit(): void {
    form.transform((d) => ({ ...d, scope_id: d.scope_id || null })).post(
        RateController.store.url(),
        { preserveScroll: true, onSuccess: () => (open.value = false) },
    );
}

function end(): void {
    if (ending.value) {
        endForm.post(RateController.end.url(ending.value.id), {
            preserveScroll: true,
            onSuccess: () => (endOpen.value = false),
        });
    }
}
</script>

<template>
    <Head :title="$t('rates:title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('rates:title')"
                :description="$t('rates:description')"
            />
            <Button data-test="new-rate" @click="create"
                ><Plus class="size-4" />{{ $t('rates:new') }}</Button
            >
        </div>
        <p v-if="rules.length === 0" class="text-muted-foreground">
            {{ $t('rates:empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            v-for="c in [
                                'scope',
                                'name',
                                'rate',
                                'valid_from',
                                'valid_to',
                            ]"
                            :key="c"
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t(`rates:field.${c}`) }}
                        </th>
                        <th scope="col">
                            <span class="sr-only">{{ $t('rates:end') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rules" :key="r.id" class="border-t">
                        <td class="px-4 py-2">
                            {{ $t(`rates:scope.${r.scope}`) }}
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{ r.name }}</bdi>
                        </td>
                        <td class="px-4 py-2 tabular-nums">
                            <bdi>{{ r.rate }} {{ r.currency }}</bdi>
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{ formatDay(r.valid_from) }}</bdi>
                        </td>
                        <td class="px-4 py-2">
                            <bdi>{{
                                r.valid_to
                                    ? formatDay(r.valid_to)
                                    : $t('rates:open')
                            }}</bdi>
                        </td>
                        <td class="px-4 py-2 text-end">
                            <Button
                                v-if="!r.valid_to"
                                size="sm"
                                variant="ghost"
                                @click="((ending = r), (endOpen = true))"
                                >{{ $t('rates:end') }}</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <FormDialog
        v-model:open="open"
        :title="$t('rates:new')"
        :save="$t('rates:save')"
        :processing="form.processing"
        @submit="submit"
    >
        <SelectField
            v-model="form.scope"
            name="scope"
            :label="$t('rates:field.scope')"
            :errors="errors"
            :options="
                scopes.map((value) => ({
                    value,
                    text: t(`rates:scope.${value}`),
                }))
            "
            @update:model-value="form.scope_id = ''"
        />
        <SelectField
            v-if="form.scope !== 'tenant'"
            v-model="form.scope_id"
            name="scope_id"
            :label="$t('rates:field.name')"
            :errors="errors"
            :options="[{ value: '', text: '' }, ...choices[form.scope]]"
        />
        <TextField
            v-model="form.rate"
            name="rate"
            :label="$t('rates:field.rate')"
            :errors="errors"
            required
        />
        <TextField
            v-model="form.currency"
            name="currency"
            :label="$t('rates:field.currency')"
            :errors="errors"
            :maxlength="3"
            required
        />
        <TextField
            v-model="form.valid_from"
            name="valid_from"
            type="date"
            :label="$t('rates:field.valid_from')"
            :errors="errors"
            required
        />
    </FormDialog>
    <FormDialog
        v-model:open="endOpen"
        :title="$t('rates:end_title')"
        :save="$t('rates:end')"
        :processing="endForm.processing"
        @submit="end"
    >
        <TextField
            v-model="endForm.valid_to"
            name="valid_to"
            type="date"
            :label="$t('rates:field.valid_to')"
            :errors="endErrors"
            required
        />
    </FormDialog>
</template>
