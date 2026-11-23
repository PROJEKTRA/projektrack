<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('core:settings.profile.delete.title')"
            :description="$t('core:settings.profile.delete.description')"
        />
        <div
            class="space-y-4 rounded-lg border border-danger/30 bg-danger-subtle p-4"
        >
            <div class="relative space-y-0.5 text-danger">
                <p class="font-medium">{{ $t('core:common.warning') }}</p>
                <p class="text-sm">
                    {{ $t('core:settings.profile.delete.warning') }}
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                        >{{ $t('core:settings.profile.delete.open') }}</Button
                    >
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-slot="{ errors, processing, reset, clearErrors }"
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        @error="() => passwordInput?.focus()"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>{{
                                $t('core:settings.profile.delete.confirm_title')
                            }}</DialogTitle>
                            <DialogDescription>
                                {{
                                    $t(
                                        'core:settings.profile.delete.confirm_description',
                                    )
                                }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">{{
                                $t(
                                    'core:settings.profile.delete.password_label',
                                )
                            }}</Label>
                            <PasswordInput
                                id="password"
                                ref="passwordInput"
                                name="password"
                                :placeholder="
                                    $t(
                                        'core:settings.profile.delete.password_placeholder',
                                    )
                                "
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    {{ $t('core:common.cancel') }}
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                {{ $t('core:settings.profile.delete.submit') }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
