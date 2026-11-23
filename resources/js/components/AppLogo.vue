<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

// The tenant's brand (S-0911): its logo if it has one, else the shipped mark.
const page = usePage();
const brand = computed(() => page.props.brand);
</script>

<template>
    <img
        v-if="brand.logoUrl"
        :src="brand.logoUrl"
        alt=""
        class="size-8 rounded-md object-contain"
    />
    <div
        v-else
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ms-1 grid flex-1 text-start text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            brand.name
        }}</span>
    </div>
</template>
