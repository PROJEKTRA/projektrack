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
    <td
        data-slot="table-cell"
        :class="
            cn(
                'px-4 py-3',
                numeric ? 'text-end whitespace-nowrap' : 'text-start',
                props.class,
            )
        "
    >
        <slot />
    </td>
</template>
