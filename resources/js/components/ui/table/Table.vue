<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    /** Names the table; the area scrolls by keyboard on narrow screens. */
    label: string;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        data-slot="table-container"
        role="region"
        tabindex="0"
        :aria-label="label"
        class="overflow-x-auto rounded-lg border bg-card text-card-foreground focus-visible:ring-2 focus-visible:ring-ring"
    >
        <table
            data-slot="table"
            :class="cn('w-full text-sm tabular-nums', props.class)"
        >
            <slot />
        </table>
    </div>
</template>
