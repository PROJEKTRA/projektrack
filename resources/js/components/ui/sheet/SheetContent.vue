<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { X } from '@lucide/vue';
import { reactiveOmit } from '@vueuse/core';
import {
    DialogClose,
    DialogContent,
    DialogPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import { useMirroredSide } from '@/lib/direction';
import { cn } from '@/lib/utils';
import SheetOverlay from './SheetOverlay.vue';

interface SheetContentProps extends DialogContentProps {
    class?: HTMLAttributes['class'];
    side?: 'top' | 'right' | 'bottom' | 'left';
}

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(defineProps<SheetContentProps>(), {
    side: 'right',
});
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = reactiveOmit(props, 'class', 'side');
// Given for left to right, mirrored in right to left languages (S-0920).
const physicalSide = useMirroredSide(() => props.side);

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <DialogPortal>
        <SheetOverlay />
        <DialogContent
            data-slot="sheet-content"
            :class="
                cn(
                    'fixed z-50 flex flex-col gap-4 bg-popover text-popover-foreground shadow-lg transition ease-in-out data-[state=closed]:animate-out data-[state=closed]:duration-(--pt-duration-slow) data-[state=open]:animate-in data-[state=open]:duration-(--pt-duration-slow)',
                    /* i18n-physical-ok-begin: follows the physical side prop */
                    physicalSide === 'right' &&
                        'inset-y-0 right-0 h-full w-3/4 border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right sm:max-w-sm',
                    physicalSide === 'left' &&
                        'inset-y-0 left-0 h-full w-3/4 border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left sm:max-w-sm',
                    /* i18n-physical-ok-end */
                    side === 'top' &&
                        'inset-x-0 top-0 h-auto border-b data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top',
                    side === 'bottom' &&
                        'inset-x-0 bottom-0 h-auto border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom',
                    props.class,
                )
            "
            v-bind="{ ...$attrs, ...forwarded }"
        >
            <slot />

            <DialogClose
                class="absolute end-4 top-4 rounded-xs opacity-70 ring-offset-popover transition-opacity hover:opacity-100 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none data-[state=open]:bg-secondary"
            >
                <X class="size-4" />
                <span class="sr-only">{{ $t('core:common.close') }}</span>
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>
