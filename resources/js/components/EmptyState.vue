<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import type { Component } from 'vue';

/**
 * Nothing to show yet (S-0910): what is missing and, in the default slot, the
 * one action that changes it. The icon is decoration only.
 */
defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        data-slot="empty-state"
        class="flex flex-col items-center gap-3 rounded-card border bg-card px-6 py-12 text-center text-card-foreground"
    >
        <component
            :is="icon"
            v-if="icon"
            class="size-6 text-muted-foreground"
            aria-hidden="true"
        />
        <p class="text-base font-medium">{{ title }}</p>
        <p v-if="description" class="max-w-prose text-sm text-muted-foreground">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
