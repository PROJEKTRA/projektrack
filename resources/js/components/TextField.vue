<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    name: string;
    label: string;
    errors: Record<string, string | undefined>;
    type?: string;
    maxlength?: number;
    required?: boolean;
}>();

const value = defineModel<string | number | null>({ required: true });
const id = useId();
</script>

<template>
    <div class="grid content-start gap-1.5">
        <Label :for="id">{{ label }}</Label>
        <Input
            :id="id"
            :model-value="value ?? ''"
            :name="name"
            :type="type ?? 'text'"
            :maxlength="maxlength"
            :required="required"
            :aria-invalid="errors[name] ? true : undefined"
            dir="auto"
            @update:model-value="value = $event"
        />
        <InputError :message="errors[name]" />
    </div>
</template>
