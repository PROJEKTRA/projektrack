<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from '@lucide/vue';
import { ref } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/i18n/strings';

/** One string with source, text without the tenant's change and override (App\I18n\StringEditor::search). */
type Row = {
    identifier: string;
    component: string;
    source: string;
    original: string | null;
    override: string | null;
    needs_review: boolean;
    source_changed: boolean;
    protected: boolean;
};

type Filters = { q: string; component: string; filter: string };

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
    language: { locale: string; name: string; direction: 'ltr' | 'rtl' };
    filters: Filters;
    rows: Row[];
    total: number;
    page: number;
    pages: number;
    components: string[];
}>();

const search = ref<Filters>({ ...props.filters });

const filterOptions = [
    { value: 'all', label: 'core:strings.filter_all' },
    { value: 'customized', label: 'core:strings.filter_customized' },
    { value: 'untranslated', label: 'core:strings.filter_untranslated' },
    { value: 'source_changed', label: 'core:strings.filter_source_changed' },
] as const;

function visit(page: number): void {
    router.get(
        StringController.index.url(props.language.locale),
        { ...search.value, page },
        { preserveState: true, preserveScroll: page !== 1, replace: true },
    );
}

const editing = ref<Row | null>(null);
const form = useForm({ identifier: '', value: '' });

function edit(row: Row): void {
    editing.value = row;
    form.clearErrors();
    form.identifier = row.identifier;
    form.value = row.override ?? row.original ?? row.source;
}

function close(): void {
    editing.value = null;
}

function save(): void {
    form.put(StringController.update.url(props.language.locale), {
        preserveScroll: true,
        onSuccess: close,
    });
}

function reset(): void {
    if (!editing.value) {
        return;
    }

    router.delete(StringController.destroy.url(props.language.locale), {
        data: { identifier: editing.value.identifier },
        preserveScroll: true,
        onSuccess: close,
    });
}

const selectClass =
    'block h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
</script>

<template>
    <Head :title="$t('core:strings.title', { name: language.name })" />

    <div class="space-y-6 px-4 py-6">
        <div class="space-y-2">
            <Link
                :href="LanguageController.index()"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" />
                {{ $t('core:strings.back') }}
            </Link>
            <Heading
                :title="$t('core:strings.title', { name: language.name })"
                :description="$t('core:strings.description')"
            />
        </div>

        <form
            class="grid gap-4 md:grid-cols-[2fr_1fr_1fr_auto] md:items-end"
            role="search"
            @submit.prevent="visit(1)"
        >
            <div class="grid gap-2">
                <Label for="strings-q">{{
                    $t('core:strings.search_label')
                }}</Label>
                <Input
                    id="strings-q"
                    v-model="search.q"
                    type="search"
                    maxlength="200"
                    :placeholder="$t('core:strings.search_placeholder')"
                />
            </div>
            <div class="grid gap-2">
                <Label for="strings-component">{{
                    $t('core:strings.component_label')
                }}</Label>
                <select
                    id="strings-component"
                    v-model="search.component"
                    :class="selectClass"
                    @change="visit(1)"
                >
                    <option value="">
                        {{ $t('core:strings.component_all') }}
                    </option>
                    <option
                        v-for="component in components"
                        :key="component"
                        :value="component"
                    >
                        {{ component }}
                    </option>
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="strings-filter">{{
                    $t('core:strings.filter_label')
                }}</Label>
                <select
                    id="strings-filter"
                    v-model="search.filter"
                    :class="selectClass"
                    @change="visit(1)"
                >
                    <option
                        v-for="option in filterOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ $t(option.label) }}
                    </option>
                </select>
            </div>
            <Button type="submit">{{
                $t('core:strings.search_submit')
            }}</Button>
        </form>

        <p class="text-sm text-muted-foreground" aria-live="polite">
            {{ $t('core:strings.results', { count: total }) }}
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('core:strings.column_identifier') }}
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t('core:strings.column_source') }}
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{
                                $t('core:strings.column_text', {
                                    name: language.name,
                                })
                            }}
                        </th>
                        <th scope="col" class="px-4 py-2">
                            <span class="sr-only">{{
                                $t('core:strings.edit')
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="rows.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('core:strings.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="row in rows"
                        :key="row.identifier"
                        class="border-t align-top"
                        :data-test="`string-${row.identifier}`"
                    >
                        <td class="px-4 py-3">
                            <bdi class="font-mono text-xs break-all">{{
                                row.identifier
                            }}</bdi>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge
                                    v-if="
                                        row.override !== null &&
                                        !row.needs_review
                                    "
                                    variant="default"
                                    >{{
                                        $t('core:strings.badge_customized')
                                    }}</Badge
                                >
                                <Badge
                                    v-if="
                                        row.needs_review ||
                                        (row.override === null &&
                                            row.original === null)
                                    "
                                    variant="secondary"
                                    >{{
                                        $t('core:strings.badge_untranslated')
                                    }}</Badge
                                >
                                <Badge
                                    v-if="row.source_changed"
                                    variant="destructive"
                                    >{{
                                        $t('core:strings.badge_source_changed')
                                    }}</Badge
                                >
                                <Badge v-if="row.protected" variant="outline">{{
                                    $t('core:strings.badge_legal')
                                }}</Badge>
                            </div>
                        </td>
                        <td
                            class="px-4 py-3 whitespace-pre-wrap"
                            lang="en"
                            dir="ltr"
                        >
                            {{ row.source }}
                        </td>
                        <td
                            class="px-4 py-3 whitespace-pre-wrap"
                            :lang="language.locale"
                            :dir="language.direction"
                        >
                            <template
                                v-if="(row.override ?? row.original) !== null"
                                >{{ row.override ?? row.original }}</template
                            >
                            <span v-else class="text-muted-foreground italic">{{
                                $t('core:strings.missing')
                            }}</span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="
                                    $t('core:strings.edit_label', {
                                        identifier: row.identifier,
                                    })
                                "
                                @click="edit(row)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="pages > 1"
            class="flex items-center justify-between gap-4 text-sm"
            :aria-label="$t('core:strings.page', { page, pages })"
        >
            <Button
                type="button"
                variant="outline"
                :disabled="page <= 1"
                @click="visit(page - 1)"
                >{{ $t('core:strings.previous') }}</Button
            >
            <span class="text-muted-foreground">{{
                $t('core:strings.page', { page, pages })
            }}</span>
            <Button
                type="button"
                variant="outline"
                :disabled="page >= pages"
                @click="visit(page + 1)"
                >{{ $t('core:strings.next') }}</Button
            >
        </nav>
    </div>

    <Dialog :open="editing !== null" @update:open="(open) => !open && close()">
        <DialogContent v-if="editing" class="sm:max-w-2xl">
            <form class="space-y-6" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle>{{
                        $t('core:strings.edit_title')
                    }}</DialogTitle>
                    <DialogDescription>
                        <bdi class="font-mono text-xs">{{
                            editing.identifier
                        }}</bdi>
                        <span class="block pt-2">{{
                            $t('core:strings.edit_description', {
                                example: '{name}',
                            })
                        }}</span>
                    </DialogDescription>
                </DialogHeader>

                <dl class="grid gap-4 text-sm">
                    <div>
                        <dt class="font-medium">
                            {{ $t('core:strings.column_source') }}
                        </dt>
                        <dd
                            class="mt-1 whitespace-pre-wrap text-muted-foreground"
                            lang="en"
                            dir="ltr"
                        >
                            {{ editing.source }}
                        </dd>
                    </div>
                    <div v-if="editing.original !== null">
                        <dt class="font-medium">
                            {{ $t('core:strings.field_original') }}
                        </dt>
                        <dd
                            class="mt-1 whitespace-pre-wrap text-muted-foreground"
                            :lang="language.locale"
                            :dir="language.direction"
                        >
                            {{ editing.original }}
                        </dd>
                    </div>
                </dl>

                <div class="grid gap-2">
                    <Label for="string-value">{{
                        $t('core:strings.field_value')
                    }}</Label>
                    <textarea
                        id="string-value"
                        v-model="form.value"
                        rows="5"
                        required
                        maxlength="5000"
                        :lang="language.locale"
                        :dir="language.direction"
                        class="block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    />
                    <InputError
                        class="whitespace-pre-line"
                        :message="form.errors.value"
                    />
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button
                        v-if="editing.override !== null"
                        type="button"
                        variant="outline"
                        data-test="reset-string-button"
                        @click="reset"
                        >{{ $t('core:strings.reset') }}</Button
                    >
                    <div class="flex gap-2 sm:ms-auto">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">{{
                                $t('core:common.cancel')
                            }}</Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            data-test="save-string-button"
                            >{{ $t('core:common.save') }}</Button
                        >
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
