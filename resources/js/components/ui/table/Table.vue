<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        data-slot="table-container"
        class="overflow-x-auto rounded-lg border bg-card text-card-foreground"
    >
        <table
            data-slot="table"
            :class="cn('w-full text-sm tabular-nums', props.class)"
        >
            <slot />
        </table>
    </div>
</template>
