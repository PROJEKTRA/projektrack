<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** A dialog with one form: title, fields in the slot, cancel and save. */
defineProps<{
    title: string;
    description?: string;
    save: string;
    processing: boolean;
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <form class="space-y-6" @submit.prevent="emit('submit')">
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription :class="description ? '' : 'sr-only'">{{
                        description || title
                    }}</DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 sm:grid-cols-2"><slot /></div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">{{
                            $t('core:common.cancel')
                        }}</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="dialog-save"
                        >{{ save }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
