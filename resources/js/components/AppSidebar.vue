<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
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
import { dashboard } from '@/routes';
import resumes from '@/routes/resumes';
import jobs from '@/routes/jobs';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Clipboard, BriefcaseBusiness, House, LayoutGrid } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Resumes',
        href: resumes.index(),
        icon: Clipboard,
    },
    {
        title: 'Jobs',
        href: jobs.index(),
        icon: BriefcaseBusiness,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Home Page',
        href: '/',
        icon: House,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader
        class="gap-4 border-b border-sidebar-border/60 bg-gradient-to-br from-sidebar-background via-sidebar-background/90 to-accent/20 p-4"
    >
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                    <Link :href="dashboard()">
                        <AppLogo />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
        <div
            class="rounded-lg border border-sidebar-border/70 bg-sidebar/80 px-4 py-3 text-xs text-sidebar-foreground shadow-sm group-data-[state=collapsed]:hidden"
        >
            <p class="text-sm font-semibold text-sidebar-foreground">
                Tailor smarter
            </p>
            <p class="mt-1 text-xs text-sidebar-foreground/70">
                Keep your resumes, jobs, and research in one workspace.
            </p>
        </div>
    </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
