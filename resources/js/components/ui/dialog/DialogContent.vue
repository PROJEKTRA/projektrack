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
import { cn } from '@/lib/utils';
import DialogOverlay from './DialogOverlay.vue';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<
        DialogContentProps & {
            class?: HTMLAttributes['class'];
            showCloseButton?: boolean;
        }
    >(),
    {
        showCloseButton: true,
    },
);
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = reactiveOmit(props, 'class');

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <DialogPortal>
        <DialogOverlay />
        <DialogContent
            data-slot="dialog-content"
            v-bind="{ ...$attrs, ...forwarded }"
            :class="
                cn(
                    /* i18n-physical-ok: centered on the viewport together with translate-x-[-50%] */
                    'fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 sm:max-w-lg',
                    props.class,
                )
            "
        >
            <slot />

            <DialogClose
                v-if="showCloseButton"
                data-slot="dialog-close"
                class="absolute end-4 top-4 rounded-xs opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
            >
                <X />
                <span class="sr-only">{{ $t('core:common.close') }}</span>
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>
