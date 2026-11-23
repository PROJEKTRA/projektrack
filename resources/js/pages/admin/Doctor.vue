<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import DoctorController from '@/actions/App/Http/Controllers/Admin/DoctorController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/i18n/formats';
import { t } from '@/i18n/strings';

type Finding = {
    check: string;
    severity: 'critical' | 'warning';
    ok: boolean;
    detail: string | null;
    title: string;
    fix: string;
};

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:doctor.page.title'),
                href: DoctorController.index(),
            },
        ],
    }),
});

const props = defineProps<{ checkedAt: string | null; findings: Finding[] }>();

// Problems first, critical before warnings.
const ordered = [...props.findings].sort(
    (a, b) =>
        Number(a.ok) - Number(b.ok) ||
        Number(a.severity !== 'critical') - Number(b.severity !== 'critical'),
);

function checkNow(): void {
    router.post(DoctorController.store.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('core:doctor.page.title')" />

    <div class="max-w-3xl space-y-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('core:doctor.page.title')"
                :description="$t('core:doctor.page.description')"
            />
            <Button data-test="doctor-check-now" @click="checkNow">{{
                $t('core:doctor.page.check_now')
            }}</Button>
        </div>

        <p class="text-sm text-muted-foreground tabular-nums">
            {{
                checkedAt
                    ? $t('core:doctor.page.checked_at', {
                          date: formatDateTime(checkedAt),
                      })
                    : $t('core:doctor.page.never_run')
            }}
        </p>

        <ul v-if="ordered.length > 0" class="divide-y rounded-lg border">
            <li
                v-for="finding in ordered"
                :key="finding.check + (finding.detail ?? '')"
                class="space-y-1 px-4 py-3"
                :data-test="`doctor-${finding.check}`"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        :variant="
                            finding.ok
                                ? 'secondary'
                                : finding.severity === 'critical'
                                  ? 'destructive'
                                  : 'outline'
                        "
                        >{{
                            finding.ok
                                ? $t('core:doctor.page.ok')
                                : finding.severity === 'critical'
                                  ? $t('core:doctor.page.critical')
                                  : $t('core:doctor.page.warning')
                        }}</Badge
                    >
                    <span class="text-sm font-medium">{{ finding.title }}</span>
                    <code
                        v-if="finding.detail"
                        dir="ltr"
                        class="text-xs text-muted-foreground"
                        >{{ finding.detail }}</code
                    >
                </div>
                <p v-if="!finding.ok" class="text-sm text-muted-foreground">
                    {{ finding.fix }}
                </p>
            </li>
        </ul>
    </div>
</template>
