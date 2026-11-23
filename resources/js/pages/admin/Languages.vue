<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    Copy,
    Download,
    MoreHorizontal,
    Pencil,
    Power,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import LanguageController from '@/actions/App/Http/Controllers/Admin/LanguageController';
import StringController from '@/actions/App/Http/Controllers/Admin/StringController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/i18n/strings';

/** One language of the tenant (App\I18n\TenantLanguages::describe). */
type Language = {
    locale: string;
    name: string;
    direction: 'ltr' | 'rtl';
    kind: 'shipped' | 'custom' | 'pseudo';
    based_on: string | null;
    active: boolean;
    is_default: boolean;
    completeness: number;
    users: number;
};

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:languages.title'),
                href: LanguageController.index(),
            },
        ],
    }),
});

const props = defineProps<{
    languages: Language[];
}>();

const names = computed(
    () => new Map(props.languages.map((l) => [l.locale, l.name])),
);
const copySources = computed(() =>
    props.languages.filter((l) => l.kind !== 'pseudo'),
);

const kindLabel: Record<Language['kind'], string> = {
    shipped: 'core:languages.kind_shipped',
    custom: 'core:languages.kind_custom',
    pseudo: 'core:languages.kind_pseudo',
};

const copyOpen = ref(false);
const copyFrom = ref('');
const copyDirection = ref<'ltr' | 'rtl'>('ltr');
const deactivating = ref<Language | null>(null);
const importing = ref<Language | null>(null);

function openCopy(language: Language | null): void {
    const source = language ?? copySources.value[0];
    copyFrom.value = source?.locale ?? '';
    copyDirection.value = source?.direction ?? 'ltr';
    copyOpen.value = true;
}

function onCopySourceChange(): void {
    const source = props.languages.find((l) => l.locale === copyFrom.value);
    copyDirection.value = source?.direction ?? 'ltr';
}

function setActive(language: Language, active: boolean): void {
    router.patch(
        LanguageController.update.url(language.locale),
        { active },
        {
            preserveScroll: true,
            onFinish: () => {
                deactivating.value = null;
            },
        },
    );
}

function onToggle(language: Language): void {
    if (language.active) {
        deactivating.value = language;
    } else {
        setActive(language, true);
    }
}

const selectClass =
    'mt-1 block h-9 w-full rounded-input border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head :title="$t('core:languages.title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('core:languages.title')"
                :description="$t('core:languages.description')"
            />
            <Button data-test="copy-language-button" @click="openCopy(null)">
                <Copy class="size-4" />
                {{ $t('core:languages.copy_title') }}
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('core:languages.column_language') }}
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('core:languages.column_status') }}
                        </th>
                        <th scope="col" class="px-4 py-2 text-end font-medium">
                            {{ $t('core:languages.column_completeness') }}
                        </th>
                        <th scope="col" class="px-4 py-2 text-end font-medium">
                            {{ $t('core:languages.column_users') }}
                        </th>
                        <th scope="col" class="px-4 py-2 text-end font-medium">
                            <span class="sr-only">{{
                                $t('core:languages.column_actions')
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="language in languages"
                        :key="language.locale"
                        class="border-t"
                        :data-test="`language-${language.locale}`"
                    >
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="font-medium"
                                    :lang="language.locale"
                                    :dir="language.direction"
                                    >{{ language.name }}</span
                                >
                                <bdi
                                    class="font-mono text-xs text-muted-foreground"
                                    >{{ language.locale }}</bdi
                                >
                                <Badge variant="outline">{{
                                    $t(kindLabel[language.kind])
                                }}</Badge>
                                <Badge
                                    v-if="language.is_default"
                                    variant="secondary"
                                    >{{
                                        $t('core:languages.default_badge')
                                    }}</Badge
                                >
                            </div>
                            <p
                                v-if="language.based_on"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{
                                    $t('core:languages.based_on', {
                                        name:
                                            names.get(language.based_on) ??
                                            language.based_on,
                                    })
                                }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    language.active ? 'default' : 'secondary'
                                "
                            >
                                {{
                                    language.active
                                        ? $t('core:languages.active')
                                        : $t('core:languages.inactive')
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-end tabular-nums">
                            {{
                                $t('core:languages.completeness', {
                                    percent: language.completeness,
                                })
                            }}
                        </td>
                        <td class="px-4 py-3 text-end tabular-nums">
                            {{
                                $t('core:languages.users', {
                                    count: language.users,
                                })
                            }}
                        </td>
                        <td class="px-4 py-3 text-end">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="
                                            $t('core:languages.actions_label', {
                                                name: language.name,
                                            })
                                        "
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-if="!language.is_default"
                                        @select="onToggle(language)"
                                    >
                                        <Power class="size-4" />
                                        {{
                                            language.active
                                                ? $t(
                                                      'core:languages.action_deactivate',
                                                  )
                                                : $t(
                                                      'core:languages.action_activate',
                                                  )
                                        }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="language.kind !== 'pseudo'"
                                        @select="openCopy(language)"
                                    >
                                        <Copy class="size-4" />
                                        {{ $t('core:languages.action_copy') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="language.kind !== 'pseudo'"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                StringController.index(
                                                    language.locale,
                                                )
                                            "
                                        >
                                            <Pencil class="size-4" />
                                            {{
                                                $t(
                                                    'core:languages.action_texts',
                                                )
                                            }}
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem as-child>
                                        <a
                                            :href="
                                                LanguageController.export.url(
                                                    language.locale,
                                                )
                                            "
                                            download
                                        >
                                            <Download class="size-4" />
                                            {{
                                                $t(
                                                    'core:languages.action_export',
                                                )
                                            }}
                                        </a>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="language.kind === 'custom'"
                                        @select="importing = language"
                                    >
                                        <Upload class="size-4" />
                                        {{ $t('core:languages.action_import') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Deactivate: says how many users are affected before anything changes. -->
    <Dialog
        :open="deactivating !== null"
        @update:open="(open) => !open && (deactivating = null)"
    >
        <DialogContent v-if="deactivating">
            <DialogHeader>
                <DialogTitle>{{
                    $t('core:languages.deactivate_title', {
                        name: deactivating.name,
                    })
                }}</DialogTitle>
                <DialogDescription>{{
                    $t('core:languages.deactivate_body', {
                        count: deactivating.users,
                    })
                }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">{{
                        $t('core:common.cancel')
                    }}</Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="destructive"
                    data-test="confirm-deactivate-button"
                    @click="setActive(deactivating, false)"
                    >{{ $t('core:languages.action_deactivate') }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Copy into a new language of the tenant. -->
    <Dialog v-model:open="copyOpen">
        <DialogContent>
            <Form
                v-slot="{ errors, processing }"
                v-bind="LanguageController.store.form()"
                class="space-y-6"
                :options="{ preserveScroll: true }"
                @success="copyOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        $t('core:languages.copy_title')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        $t('core:languages.copy_description')
                    }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="copy-from">{{
                        $t('core:languages.copy_from')
                    }}</Label>
                    <select
                        id="copy-from"
                        v-model="copyFrom"
                        name="from"
                        :class="selectClass"
                        @change="onCopySourceChange"
                    >
                        <option
                            v-for="source in copySources"
                            :key="source.locale"
                            :value="source.locale"
                            :lang="source.locale"
                        >
                            {{ source.name }}
                        </option>
                    </select>
                    <InputError :message="errors.from" />
                </div>

                <div class="grid gap-2">
                    <Label for="copy-locale">{{
                        $t('core:languages.copy_locale')
                    }}</Label>
                    <Input
                        id="copy-locale"
                        name="locale"
                        required
                        maxlength="16"
                        autocomplete="off"
                        dir="ltr"
                        aria-describedby="copy-locale-help"
                    />
                    <p
                        id="copy-locale-help"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('core:languages.copy_locale_help') }}
                    </p>
                    <InputError :message="errors.locale" />
                </div>

                <div class="grid gap-2">
                    <Label for="copy-name">{{
                        $t('core:languages.copy_name')
                    }}</Label>
                    <Input
                        id="copy-name"
                        name="name"
                        required
                        maxlength="60"
                        autocomplete="off"
                        aria-describedby="copy-name-help"
                    />
                    <p
                        id="copy-name-help"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('core:languages.copy_name_help') }}
                    </p>
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="copy-direction">{{
                        $t('core:languages.copy_direction')
                    }}</Label>
                    <select
                        id="copy-direction"
                        v-model="copyDirection"
                        name="direction"
                        :class="selectClass"
                    >
                        <option value="ltr">
                            {{ $t('core:languages.direction_ltr') }}
                        </option>
                        <option value="rtl">
                            {{ $t('core:languages.direction_rtl') }}
                        </option>
                    </select>
                    <InputError :message="errors.direction" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">{{
                            $t('core:common.cancel')
                        }}</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="confirm-copy-button"
                        >{{ $t('core:languages.copy_submit') }}</Button
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Import translations into an own language. -->
    <Dialog
        :open="importing !== null"
        @update:open="(open) => !open && (importing = null)"
    >
        <DialogContent v-if="importing">
            <Form
                v-slot="{ errors, processing }"
                v-bind="LanguageController.import.form(importing.locale)"
                class="space-y-6"
                :options="{ preserveScroll: true }"
                @success="importing = null"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        $t('core:languages.import_title', {
                            name: importing.name,
                        })
                    }}</DialogTitle>
                    <DialogDescription>{{
                        $t('core:languages.import_description')
                    }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="import-file">{{
                        $t('core:languages.import_file')
                    }}</Label>
                    <Input
                        id="import-file"
                        type="file"
                        name="file"
                        accept="application/json,.json"
                        required
                    />
                    <InputError
                        class="whitespace-pre-line"
                        :message="errors.file"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">{{
                            $t('core:common.cancel')
                        }}</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="confirm-import-button"
                        >{{ $t('core:languages.import_submit') }}</Button
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
