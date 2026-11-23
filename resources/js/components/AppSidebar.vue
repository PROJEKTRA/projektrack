<!--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarCheck,
    Clock,
    FolderKanban,
    KeyRound,
    Languages,
    LayoutGrid,
    ListChecks,
    Palette,
    Settings,
    Stethoscope,
} from '@lucide/vue';
import { computed } from 'vue';
import LanguageController from '@/actions/App/Http/Controllers/Admin/LanguageController';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
import DoctorController from '@/actions/App/Http/Controllers/Admin/DoctorController';
import LicenseController from '@/actions/App/Http/Controllers/Admin/LicenseController';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import CompanyController from '@/actions/Modules/Crm/Http/CompanyController';
import ProjectController from '@/actions/Modules/Projects/Http/ProjectController';
import ApprovalController from '@/actions/Modules/Timesheets/Http/ApprovalController';
import PeriodController from '@/actions/Modules/Timesheets/Http/PeriodController';
import TimesheetController from '@/actions/Modules/Timesheets/Http/TimesheetController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { t } from '@/i18n/strings';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

// Entries the user may open; the server checks every action again.
const mainNavItems = computed<NavItem[]>(() => {
    const permissions = page.props.auth.permissions ?? [];
    const anywhere = page.props.auth.permissionsAnywhere ?? [];

    return [
        {
            title: t('core:nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(permissions.includes('crm.view')
            ? [
                  {
                      title: t('crm:title'),
                      href: CompanyController.index(),
                      icon: Building2,
                  },
              ]
            : []),
        ...(anywhere.includes('projects.view') ||
        permissions.includes('projects.create')
            ? [
                  {
                      title: t('projects:title'),
                      href: ProjectController.index(),
                      icon: FolderKanban,
                  },
              ]
            : []),
        ...(permissions.includes('timesheets.view')
            ? [
                  {
                      title: t('timesheets:title'),
                      href: TimesheetController.index(),
                      icon: Clock,
                  },
              ]
            : []),
        ...(anywhere.includes('timesheets.approve')
            ? [
                  {
                      title: t('timesheets:approvals.title'),
                      href: ApprovalController.index(),
                      icon: ListChecks,
                  },
              ]
            : []),
        ...(permissions.includes('timesheets.lock_period')
            ? [
                  {
                      title: t('timesheets:periods.title'),
                      href: PeriodController.index(),
                      icon: CalendarCheck,
                  },
              ]
            : []),
        ...(permissions.includes('core.strings.customize')
            ? [
                  {
                      title: t('core:nav.languages'),
                      href: LanguageController.index(),
                      icon: Languages,
                  },
              ]
            : []),
        ...(permissions.includes('core.branding.manage')
            ? [
                  {
                      title: t('core:nav.brand'),
                      href: BrandController.edit(),
                      icon: Palette,
                  },
              ]
            : []),
        ...(page.props.licenseAccess
            ? [
                  {
                      title: t('core:nav.license'),
                      href: LicenseController.show(),
                      icon: KeyRound,
                  },
              ]
            : []),
        // Operator administrators only; the server sends null to everyone else.
        ...(page.props.doctorAlerts !== null &&
        page.props.doctorAlerts !== undefined
            ? [
                  {
                      title: t('core:nav.doctor'),
                      href: DoctorController.index(),
                      icon: Stethoscope,
                  },
              ]
            : []),
        ...(permissions.includes('core.settings.manage')
            ? [
                  {
                      title: t('core:nav.admin_settings'),
                      href: SettingsController.index(),
                      icon: Settings,
                  },
              ]
            : []),
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
