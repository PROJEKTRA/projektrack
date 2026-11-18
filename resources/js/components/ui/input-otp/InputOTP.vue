<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { OTPInputEmits, OTPInputProps } from 'vue-input-otp';
import { reactiveOmit } from '@vueuse/core';
import { useForwardPropsEmits } from 'reka-ui';
import { OTPInput } from 'vue-input-otp';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<
    OTPInputProps & { class?: HTMLAttributes['class'] }
>();

const emits = defineEmits<OTPInputEmits>();

const delegatedProps = reactiveOmit(props, 'class');

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <!-- Codes are read left to right in every language (S-0920). Attributes
         (id, autofocus, v-model listener) belong to the input, not the wrapper. -->
    <div dir="ltr" class="contents">
        <OTPInput
            v-slot="slotProps"
            v-bind="{ ...$attrs, ...forwarded }"
            :container-class="
                cn(
                    'flex items-center gap-2 has-disabled:opacity-50',
                    props.class,
                )
            "
            data-slot="input-otp"
            class="disabled:cursor-not-allowed"
        >
            <slot v-bind="slotProps" />
        </OTPInput>
    </div>
</template>
