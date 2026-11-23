<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import LicenseController from '@/actions/App/Http/Controllers/Admin/LicenseController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatNumber } from '@/i18n/formats';
import { t } from '@/i18n/strings';

type Status =
    | 'active'
    | 'pending_payment'
    | 'grace'
    | 'read_only'
    | 'not_yet_valid'
    | 'invalid';

type License = {
    edition: 'business' | 'enterprise' | 'partner' | 'vendor';
    customer: string;
    id: string;
    serial: number;
    full: number;
    light: number;
    expiresAt: string;
    readOnlyFrom: string;
    statusUntil: string | null;
    trial: boolean;
    addons: string[];
    features: string[];
    domains: string[];
    domainCovered: boolean;
};

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: t('core:license.title'),
                href: LicenseController.show(),
            },
        ],
    }),
});

const props = defineProps<{
    instanceId: string | null;
    clockProblem: boolean;
    status: Status | null;
    used: { full: number; light: number };
    license: License | null;
}>();

const form = useForm({ license: '' });

const statusText = computed(() => {
    const license = props.license;

    switch (props.status) {
        case 'active':
            return license?.trial && license.statusUntil
                ? t('core:license.status.trial', {
                      date: formatDateTime(license.statusUntil),
                  })
                : t('core:license.status.active');
        case 'pending_payment':
            return t('core:license.status.pending_payment', {
                date: license?.statusUntil
                    ? formatDateTime(license.statusUntil)
                    : '',
            });
        case 'grace':
            return t('core:license.status.grace', {
                date: license ? formatDateTime(license.readOnlyFrom) : '',
            });
        case 'read_only':
            return t('core:license.status.read_only');
        case 'not_yet_valid':
            return t('core:license.status.not_yet_valid');
        case 'invalid':
            return t('core:license.status.invalid');
        default:
            return t('core:license.status.community');
    }
});

const edition = computed(() => {
    switch (props.license?.edition) {
        case 'business':
            return t('core:license.edition.business');
        case 'enterprise':
            return t('core:license.edition.enterprise');
        case 'partner':
            return t('core:license.edition.partner');
        case 'vendor':
            return t('core:license.edition.vendor');
        default:
            return t('core:license.edition.community');
    }
});

const over = computed(
    () =>
        props.license !== null &&
        (props.used.full > props.license.full ||
            props.used.light > props.license.light),
);

async function readFile(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    // A license is short; anything larger is another file.
    form.license = file && file.size <= 16384 ? (await file.text()).trim() : '';
    form.clearErrors();
    // So that choosing the same file again reads it again.
    input.value = '';

    if (file && form.license === '') {
        form.setError('license', t('core:license.refused.invalid'));
    }
}

function install(): void {
    form.post(LicenseController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="$t('core:license.title')" />

    <div class="max-w-3xl space-y-8 px-4 py-6">
        <Heading
            :title="$t('core:license.title')"
            :description="$t('core:license.description')"
        />

        <section class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-base font-medium">{{ edition }}</span>
                <Badge
                    :variant="
                        status === 'invalid' || status === 'read_only'
                            ? 'destructive'
                            : 'secondary'
                    "
                    class="whitespace-normal"
                    data-test="license-status"
                    >{{ statusText }}</Badge
                >
            </div>

            <ul
                v-if="
                    clockProblem || over || (license && !license.domainCovered)
                "
                class="space-y-1 rounded-lg border border-warning/40 bg-warning-subtle p-4 text-sm text-warning"
            >
                <li v-if="clockProblem">{{ $t('core:license.clock') }}</li>
                <li v-if="over">{{ $t('core:license.over') }}</li>
                <li v-if="license && !license.domainCovered">
                    {{ $t('core:license.domain') }}
                </li>
            </ul>

            <dl
                v-if="license"
                class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_1fr]"
            >
                <dt class="text-muted-foreground">
                    {{ $t('core:license.customer') }}
                </dt>
                <dd>
                    <bdi>{{ license.customer }}</bdi>
                </dd>
                <dt class="text-muted-foreground">
                    {{ $t('core:license.expires') }}
                </dt>
                <dd>
                    <time :datetime="license.expiresAt">{{
                        formatDateTime(license.expiresAt)
                    }}</time>
                </dd>
                <dt class="text-muted-foreground">
                    {{ $t('core:license.number') }}
                </dt>
                <dd>
                    <bdi class="font-mono text-xs break-all">{{
                        license.id
                    }}</bdi>
                    ({{
                        $t('core:license.issue', {
                            serial: formatNumber(license.serial),
                        })
                    }})
                </dd>
                <dt class="text-muted-foreground">
                    {{ $t('core:license.domains') }}
                </dt>
                <dd>
                    <bdi dir="ltr">{{ license.domains.join(', ') }}</bdi>
                </dd>
                <dt class="text-muted-foreground">
                    {{ $t('core:license.check_in') }}
                </dt>
                <dd>{{ $t('core:license.check_in_none') }}</dd>
            </dl>

            <Table :label="$t('core:license.users')">
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ $t('core:license.users') }}</TableHead>
                        <TableHead numeric>{{
                            $t('core:license.used')
                        }}</TableHead>
                        <TableHead v-if="license" numeric>{{
                            $t('core:license.licensed')
                        }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>{{ $t('core:license.full') }}</TableCell>
                        <TableCell numeric>{{
                            formatNumber(used.full)
                        }}</TableCell>
                        <TableCell v-if="license" numeric>{{
                            formatNumber(license.full)
                        }}</TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell>{{ $t('core:license.light') }}</TableCell>
                        <TableCell numeric>{{
                            formatNumber(used.light)
                        }}</TableCell>
                        <TableCell v-if="license" numeric>{{
                            formatNumber(license.light)
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <section class="space-y-2 text-sm">
            <h3 class="text-base">{{ $t('core:license.includes') }}</h3>
            <p>
                {{
                    license
                        ? $t('core:license.includes_commercial')
                        : $t('core:license.includes_community')
                }}
            </p>
            <p v-if="license && license.addons.length > 0">
                {{ $t('core:license.addons') }}:
                <bdi class="font-mono text-xs">{{
                    license.addons.join(', ')
                }}</bdi>
            </p>
            <p v-if="license && license.features.length > 0">
                {{ $t('core:license.features') }}:
                <bdi class="font-mono text-xs">{{
                    license.features.join(', ')
                }}</bdi>
            </p>
        </section>

        <form class="space-y-4" @submit.prevent="install">
            <div class="space-y-1">
                <h3 class="text-base">{{ $t('core:license.install') }}</h3>
                <p class="text-sm text-muted-foreground">
                    {{ $t('core:license.install_help') }}
                </p>
            </div>
            <p v-if="instanceId" class="text-sm">
                {{ $t('core:license.instance_id') }}:
                <bdi
                    class="font-mono text-xs break-all"
                    data-test="license-instance-id"
                    >{{ instanceId }}</bdi
                >
            </p>
            <div class="grid gap-2">
                <Label for="license-file">{{ $t('core:license.file') }}</Label>
                <input
                    id="license-file"
                    type="file"
                    accept=".txt,.lic,text/plain"
                    class="text-sm"
                    :aria-invalid="form.errors.license ? true : undefined"
                    aria-describedby="license-error"
                    @change="readFile"
                />
            </div>
            <div class="grid gap-2">
                <Label for="license-text">{{ $t('core:license.text') }}</Label>
                <textarea
                    id="license-text"
                    v-model="form.license"
                    dir="ltr"
                    rows="4"
                    spellcheck="false"
                    autocomplete="off"
                    class="block w-full rounded-input border border-input bg-transparent px-3 py-2 font-mono text-xs break-all outline-hidden focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring"
                    :aria-invalid="form.errors.license ? true : undefined"
                    aria-describedby="license-error"
                ></textarea>
                <InputError
                    id="license-error"
                    role="alert"
                    :message="form.errors.license"
                />
            </div>
            <Button
                type="submit"
                :disabled="form.processing || form.license.trim() === ''"
                data-test="license-install"
                >{{ $t('core:license.install_submit') }}</Button
            >
        </form>
    </div>
</template>
