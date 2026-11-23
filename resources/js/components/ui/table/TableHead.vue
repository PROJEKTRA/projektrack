<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    /** Amounts, hours and counts: aligned to the end of the line. */
    numeric?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <th
        scope="col"
        data-slot="table-head"
        :class="
            cn(
                'px-4 py-2 font-medium',
                numeric ? 'text-end' : 'text-start',
                props.class,
            )
        "
    >
        <slot />
    </th>
</template>
