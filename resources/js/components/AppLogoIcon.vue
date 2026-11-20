<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Neutral mark (S-0986): no product or framework logo before sign-in. -->
        <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm16 16h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H22a2 2 0 0 1-2-2V22a2 2 0 0 1 2-2Zm0-16h12a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H22a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2ZM6 26h12a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2Z"
        />
    </svg>
</template>
