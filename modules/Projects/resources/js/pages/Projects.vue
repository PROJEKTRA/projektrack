<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import ProjectController from '@/actions/Modules/Projects/Http/ProjectController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n/strings';
import { formatHours } from '@/lib/hours';
import HealthBadge from '../components/HealthBadge.vue';
import ProjectDialog from '../components/ProjectDialog.vue';
import type { ProjectFormMeta, ProjectRow } from '../types';

defineProps<{
    projects: {
        data: ProjectRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    status: string;
    form: ProjectFormMeta;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: t('projects:title'), href: ProjectController.index() },
        ],
    }),
});

const open = ref(false);
</script>

<template>
    <Head :title="$t('projects:title')" />

    <div class="space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('projects:title')"
                :description="$t('projects:description')"
            />
            <Button
                v-if="form.can.edit"
                data-test="new-project"
                @click="open = true"
            >
                <Plus class="size-4" />
                {{ $t('projects:new') }}
            </Button>
        </div>

        <div class="flex gap-2">
            <Button
                v-for="s in form.statuses"
                :key="s"
                as-child
                :variant="s === status ? 'secondary' : 'ghost'"
            >
                <Link
                    :href="ProjectController.index({ query: { status: s } })"
                    :aria-current="s === status ? 'page' : undefined"
                    >{{ $t(`projects:status.${s}`) }}</Link
                >
            </Button>
        </div>

        <p v-if="projects.data.length === 0" class="text-muted-foreground">
            {{ $t('projects:empty') }}
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            v-for="column in [
                                'number',
                                'name',
                                'company',
                                'hours',
                                'health',
                            ]"
                            :key="column"
                            scope="col"
                            class="px-4 py-2 text-start font-medium"
                        >
                            {{ $t(`projects:field.${column}`) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="project in projects.data"
                        :key="project.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <bdi class="font-mono text-xs">{{
                                project.number
                            }}</bdi>
                        </td>
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="ProjectController.show(project.id)"
                                class="text-primary underline-offset-4 hover:underline"
                                dir="auto"
                                >{{ project.name }}</Link
                            >
                        </td>
                        <td class="px-4 py-3">
                            <bdi>{{ project.company }}</bdi>
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{
                                project.budget_minutes
                                    ? $t('projects:hours_of', {
                                          booked: formatHours(
                                              project.booked_minutes,
                                          ),
                                          budget: formatHours(
                                              project.budget_minutes,
                                          ),
                                      })
                                    : $t('projects:hours', {
                                          booked: formatHours(
                                              project.booked_minutes,
                                          ),
                                      })
                            }}
                        </td>
                        <td class="px-4 py-3">
                            <HealthBadge :health="project.health" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="projects.prev_page_url || projects.next_page_url"
            class="flex gap-2"
        >
            <Button v-if="projects.prev_page_url" as-child variant="secondary"
                ><Link :href="projects.prev_page_url">{{
                    $t('projects:previous')
                }}</Link></Button
            >
            <Button v-if="projects.next_page_url" as-child variant="secondary"
                ><Link :href="projects.next_page_url">{{
                    $t('projects:next')
                }}</Link></Button
            >
        </div>
    </div>

    <ProjectDialog v-model:open="open" :meta="form" />
</template>
