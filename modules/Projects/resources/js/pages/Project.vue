<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    IndentDecrease,
    IndentIncrease,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MemberController from '@/actions/Modules/Projects/Http/MemberController';
import ProjectController from '@/actions/Modules/Projects/Http/ProjectController';
import WorkPackageController from '@/actions/Modules/Projects/Http/WorkPackageController';
import Heading from '@/components/Heading.vue';
import SelectField from '@/components/SelectField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n/strings';
import { formatHours } from '@/lib/hours';
import ContractDialog from '../components/ContractDialog.vue';
import HealthBadge from '../components/HealthBadge.vue';
import PackageDialog from '../components/PackageDialog.vue';
import ProjectDialog from '../components/ProjectDialog.vue';
import type {
    ContractData,
    Option,
    PackageData,
    ProjectDetails,
    ProjectFormMeta,
} from '../types';

const props = defineProps<{
    project: ProjectDetails;
    form: ProjectFormMeta;
    users: Option[];
    roles: Option[];
    contractTypes: string[];
    categories: string[];
}>();

defineOptions({
    layout: (page: { project: ProjectDetails }) => ({
        breadcrumbs: [
            { title: t('projects:title'), href: ProjectController.index() },
            {
                title: page.project.name,
                href: ProjectController.show(page.project.id),
            },
        ],
    }),
});

const editing = ref(false);
const contractOpen = ref(false);
const contract = ref<ContractData | null>(null);
const packageOpen = ref(false);
const workPackage = ref<PackageData | null>(null);

const member = useForm({
    user_id: '',
    project_role_id: props.roles[1]?.value ?? '',
});
const memberErrors = computed(
    () => member.errors as Record<string, string | undefined>,
);

function addMember(): void {
    member.post(MemberController.store.url(props.project.id), {
        preserveScroll: true,
        onSuccess: () => member.reset('user_id'),
    });
}

function siblings(p: PackageData): PackageData[] {
    return props.project.work_packages.filter(
        (x) => x.parent_id === p.parent_id,
    );
}

function move(p: PackageData, parentId: string | null, place: number): void {
    router.post(
        WorkPackageController.move.url([props.project.id, p.id]),
        { parent_id: parentId, place },
        { preserveScroll: true },
    );
}

function shift(p: PackageData, by: number): void {
    move(p, p.parent_id, siblings(p).indexOf(p) + by);
}

function indent(p: PackageData): void {
    const above = siblings(p)[siblings(p).indexOf(p) - 1];

    if (above) {
        move(p, above.id, 9999);
    }
}

function outdent(p: PackageData): void {
    const parent = props.project.work_packages.find(
        (x) => x.id === p.parent_id,
    );

    if (parent) {
        move(p, parent.parent_id, siblings(parent).indexOf(parent) + 1);
    }
}

function depth(p: PackageData): number {
    return p.wbs_code.split('.').length - 1;
}
</script>

<template>
    <Head :title="project.name" />

    <div class="space-y-8 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Heading
                    :title="project.name"
                    :description="`${project.number}, ${project.company}`"
                />
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge variant="secondary">{{
                        $t(`projects:status.${project.status}`)
                    }}</Badge>
                    <HealthBadge :health="project.health" />
                    <span class="tabular-nums">{{
                        project.budget_minutes
                            ? $t('projects:hours_of', {
                                  booked: formatHours(project.booked_minutes),
                                  budget: formatHours(project.budget_minutes),
                              })
                            : $t('projects:hours', {
                                  booked: formatHours(project.booked_minutes),
                              })
                    }}</span>
                </div>
            </div>
            <Button
                v-if="form.can.edit"
                data-test="edit-project"
                @click="editing = true"
            >
                <Pencil class="size-4" />
                {{ $t('projects:edit') }}
            </Button>
        </div>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-medium">{{ $t('projects:section.wbs') }}</h2>
                <Button
                    v-if="form.can.wbs"
                    size="sm"
                    data-test="new-package"
                    @click="((workPackage = null), (packageOpen = true))"
                >
                    <Plus class="size-4" />
                    {{ $t('projects:new_package') }}
                </Button>
            </div>
            <p
                v-if="project.work_packages.length === 0"
                class="text-muted-foreground"
            >
                {{ $t('projects:none') }}
            </p>
            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="p in project.work_packages"
                    :key="p.id"
                    class="flex flex-wrap items-center gap-2 px-4 py-2 text-sm"
                    :style="{ paddingInlineStart: `${1 + depth(p) * 1.5}rem` }"
                >
                    <bdi class="font-mono text-xs text-muted-foreground">{{
                        p.wbs_code
                    }}</bdi>
                    <span class="grow font-medium" dir="auto">{{
                        p.name
                    }}</span>
                    <Badge v-if="p.status === 'closed'" variant="outline">{{
                        $t('projects:status.closed')
                    }}</Badge>
                    <Badge v-if="p.service_category" variant="secondary">{{
                        $t(`projects:category.${p.service_category}`)
                    }}</Badge>
                    <span class="text-muted-foreground tabular-nums">{{
                        p.planned_minutes
                            ? $t('projects:hours_of', {
                                  booked: formatHours(p.booked_minutes),
                                  budget: formatHours(p.planned_minutes),
                              })
                            : $t('projects:hours', {
                                  booked: formatHours(p.booked_minutes),
                              })
                    }}</span>
                    <span v-if="form.can.wbs" class="flex">
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('projects:move_up')"
                            :disabled="siblings(p).indexOf(p) === 0"
                            @click="shift(p, -1)"
                            ><ArrowUp class="size-4"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('projects:move_down')"
                            :disabled="
                                siblings(p).indexOf(p) ===
                                siblings(p).length - 1
                            "
                            @click="shift(p, 1)"
                            ><ArrowDown class="size-4"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('projects:move_in')"
                            :disabled="siblings(p).indexOf(p) === 0"
                            @click="indent(p)"
                            ><IndentIncrease class="size-4"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('projects:move_out')"
                            :disabled="!p.parent_id"
                            @click="outdent(p)"
                            ><IndentDecrease class="size-4"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('projects:edit_package')"
                            @click="((workPackage = p), (packageOpen = true))"
                            ><Pencil class="size-4"
                        /></Button>
                    </span>
                </li>
            </ul>
        </section>

        <div class="grid gap-8 lg:grid-cols-2">
            <section class="space-y-3">
                <h2 class="font-medium">
                    {{ $t('projects:section.members') }}
                </h2>
                <ul class="space-y-1 text-sm">
                    <li
                        v-for="m in project.members"
                        :key="m.id"
                        class="flex items-center gap-2"
                    >
                        <bdi class="font-medium">{{ m.name }}</bdi>
                        <span class="grow text-muted-foreground">{{
                            m.role
                        }}</span>
                        <Button
                            v-if="form.can.members"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('projects:remove')"
                            @click="
                                router.delete(
                                    MemberController.destroy.url([
                                        project.id,
                                        m.id,
                                    ]),
                                    { preserveScroll: true },
                                )
                            "
                            ><Trash2 class="size-4"
                        /></Button>
                    </li>
                </ul>
                <form
                    v-if="form.can.members"
                    class="grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]"
                    @submit.prevent="addMember"
                >
                    <SelectField
                        v-model="member.user_id"
                        name="user_id"
                        :label="$t('projects:field.user')"
                        :errors="memberErrors"
                        :options="[
                            { value: '', text: t('projects:choose') },
                            ...users,
                        ]"
                    />
                    <SelectField
                        v-model="member.project_role_id"
                        name="project_role_id"
                        :label="$t('projects:field.role')"
                        :errors="memberErrors"
                        :options="roles"
                    />
                    <Button
                        type="submit"
                        variant="secondary"
                        :disabled="!member.user_id || member.processing"
                        >{{ $t('projects:add') }}</Button
                    >
                </form>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-medium">
                        {{ $t('projects:section.contracts') }}
                    </h2>
                    <Button
                        v-if="form.can.contracts"
                        size="sm"
                        variant="secondary"
                        data-test="new-contract"
                        @click="((contract = null), (contractOpen = true))"
                    >
                        <Plus class="size-4" />
                        {{ $t('projects:new_contract') }}
                    </Button>
                </div>
                <p
                    v-if="project.contracts.length === 0"
                    class="text-muted-foreground"
                >
                    {{ $t('projects:none') }}
                </p>
                <ul class="space-y-1 text-sm">
                    <li
                        v-for="c in project.contracts"
                        :key="c.id"
                        class="flex items-center gap-2"
                    >
                        <bdi class="font-medium">{{ c.number }}</bdi>
                        <span class="grow text-muted-foreground">{{
                            $t(`projects:contract.${c.contract_type}`)
                        }}</span>
                        <Button
                            v-if="form.can.contracts"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('projects:edit_contract')"
                            @click="((contract = c), (contractOpen = true))"
                            ><Pencil class="size-4"
                        /></Button>
                    </li>
                </ul>
            </section>
        </div>
    </div>

    <ProjectDialog v-model:open="editing" :meta="form" :project="project" />
    <ContractDialog
        v-model:open="contractOpen"
        :project-id="project.id"
        :contract="contract"
        :types="contractTypes"
    />
    <PackageDialog
        v-model:open="packageOpen"
        :project="project"
        :work-package="workPackage"
        :categories="categories"
    />
</template>
