<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n/strings';

export type Line = {
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    rate_class: string;
};

defineProps<{ errors: Record<string, string | undefined> }>();
const lines = defineModel<Line[]>({ required: true });
</script>

<template>
    <div class="space-y-4 sm:col-span-2">
        <div
            v-for="(line, i) in lines"
            :key="i"
            class="grid gap-3 rounded-lg border p-3 sm:grid-cols-4"
        >
            <TextField
                v-model="line.description"
                class="sm:col-span-4"
                :name="`lines.${i}.description`"
                :label="$t('invoices:field.description')"
                :errors="errors"
                required
            />
            <TextField
                v-model="line.quantity"
                :name="`lines.${i}.quantity`"
                :label="$t('invoices:field.quantity')"
                :errors="errors"
                required
            />
            <SelectField
                v-model="line.unit"
                :name="`lines.${i}.unit`"
                :label="$t('invoices:field.unit')"
                :errors="errors"
                :options="
                    ['piece', 'hour', 'day', 'flat'].map((value) => ({
                        value,
                        text: t(`invoices:unit.${value}`),
                    }))
                "
            />
            <TextField
                v-model="line.unit_price"
                :name="`lines.${i}.unit_price`"
                :label="$t('invoices:field.unit_price')"
                :errors="errors"
                required
            />
            <SelectField
                v-model="line.rate_class"
                :name="`lines.${i}.rate_class`"
                :label="$t('invoices:field.rate_class')"
                :errors="errors"
                :options="
                    ['standard', 'reduced'].map((value) => ({
                        value,
                        text: t(`invoices:rate_class.${value}`),
                    }))
                "
            />
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="lines.splice(i, 1)"
                ><Trash2 class="size-4" />{{ $t('invoices:remove') }}</Button
            >
        </div>
        <Button
            type="button"
            size="sm"
            variant="secondary"
            @click="
                lines.push({
                    description: '',
                    quantity: '1',
                    unit: 'piece',
                    unit_price: '',
                    rate_class: 'standard',
                })
            "
            ><Plus class="size-4" />{{ $t('invoices:add_line') }}</Button
        >
    </div>
</template>
