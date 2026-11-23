<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

defineProps<{
    name: string;
    label: string;
    errors: Record<string, string | undefined>;
    options: { value: string; text: string }[];
}>();

const value = defineModel<string>({ required: true });
const id = useId();
</script>

<template>
    <div class="grid content-start gap-1.5">
        <Label :for="id">{{ label }}</Label>
        <select
            :id="id"
            v-model="value"
            :name="name"
            class="block h-9 w-full rounded-input border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-hidden"
            :aria-invalid="errors[name] ? true : undefined"
        >
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.text }}
            </option>
        </select>
        <InputError :message="errors[name]" />
    </div>
</template>
