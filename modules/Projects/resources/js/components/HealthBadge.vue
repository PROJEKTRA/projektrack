<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{ health: 'green' | 'amber' | 'red' }>();
</script>

<template>
    <Badge
        :variant="
            health === 'red'
                ? 'destructive'
                : health === 'amber'
                  ? 'default'
                  : 'secondary'
        "
        >{{ $t(`projects:health.${health}`) }}</Badge
    >
</template>
