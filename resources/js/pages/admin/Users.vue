<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import FormDialog from '@/components/FormDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import TextField from '@/components/TextField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n/strings';

type Person = {
    id: string;
    name: string;
    email: string;
    type: string;
    two_factor: boolean;
    portal: boolean;
    roles: string[];
};

const props = defineProps<{
    users: { data: Person[] };
    roles: { value: string; text: string; allowed: boolean }[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: t('core:users.title'), href: UserController.index() },
        ],
    }),
});

const open = ref(false);
const person = ref<Person | null>(null);
const form = useForm({ name: '', email: '', roles: [] as string[] });
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const roleName = computed(
    () => new Map(props.roles.map((r) => [r.value, r.text])),
);

function show(who: Person | null): void {
    person.value = who;
    form.clearErrors();
    form.name = who?.name ?? '';
    form.email = who?.email ?? '';
    form.roles = [...(who?.roles ?? [])];
    open.value = true;
}

function submit(): void {
    const then = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (person.value) {
        form.transform(({ roles }) => ({ roles })).put(
            UserController.update.url(person.value.id),
            then,
        );
    } else {
        form.transform((data) => data).post(UserController.store.url(), then);
    }
}
</script>

<template>
    <Head :title="$t('core:users.title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('core:users.title')"
                :description="$t('core:users.description')"
            />
            <Button data-test="invite" @click="show(null)"
                ><Plus class="size-4" />{{ $t('core:users.invite') }}</Button
            >
        </div>
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('core:users.name') }}
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('core:users.roles') }}
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('core:users.two_factor') }}
                        </th>
                        <th scope="col">
                            <span class="sr-only">{{
                                $t('core:users.edit_roles')
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users.data" :key="u.id" class="border-t">
                        <td class="px-4 py-2">
                            <bdi class="block font-medium">{{
                                u.name
                            }}</bdi><bdi class="text-muted-foreground">{{
                                u.email
                            }}</bdi>
                        </td>
                        <td class="space-x-1 px-4 py-2 rtl:space-x-reverse">
                            <Badge
                                v-for="r in u.roles"
                                :key="r"
                                variant="secondary"
                                >{{ roleName.get(r) ?? '' }}</Badge
                            >
                        </td>
                        <td class="px-4 py-2">
                            {{
                                u.two_factor
                                    ? $t('core:users.on')
                                    : $t('core:users.off')
                            }}
                        </td>
                        <td class="px-4 py-2 text-end">
                            <Button
                                v-if="
                                    !u.portal &&
                                    u.id !== $page.props.auth.user.id
                                "
                                size="sm"
                                variant="ghost"
                                @click="show(u)"
                                >{{ $t('core:users.edit_roles') }}</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <FormDialog
        v-model:open="open"
        :title="$t(person ? 'core:users.edit_roles' : 'core:users.invite')"
        :description="
            person ? person.name : $t('core:users.invite_description')
        "
        :save="$t(person ? 'core:users.save' : 'core:users.send')"
        :processing="form.processing"
        @submit="submit"
    >
        <template v-if="!person">
            <TextField
                v-model="form.name"
                name="name"
                :label="$t('core:users.name')"
                :errors="errors"
                required
            />
            <TextField
                v-model="form.email"
                name="email"
                type="email"
                :label="$t('core:users.email')"
                :errors="errors"
                required
            />
        </template>
        <fieldset class="space-y-2 sm:col-span-2">
            <legend class="mb-1 text-sm font-medium">
                {{ $t('core:users.roles') }}
            </legend>
            <label
                v-for="role in roles"
                :key="role.value"
                class="flex items-center gap-2 text-sm"
            >
                <input
                    v-model="form.roles"
                    type="checkbox"
                    :value="role.value"
                    :disabled="!role.allowed"
                />
                {{ role.text }}
            </label>
            <InputError :message="errors.roles" />
        </fieldset>
    </FormDialog>
</template>
