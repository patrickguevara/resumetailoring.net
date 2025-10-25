<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useBilling } from '@/composables/useBilling';
import { dashboard } from '@/routes';
import billingRoutes from '@/routes/billing';
import jobs from '@/routes/jobs';
import resumes from '@/routes/resumes';
import { type AppPageProps, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    Clipboard,
    House,
    LayoutGrid,
    Settings2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const baseNavItems: NavItem[] = [
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

const page = usePage<AppPageProps>();
const canAccessAdmin = computed(
    () => page.props.admin?.can_access_admin ?? false,
);
const mainNavItems = computed<NavItem[]>(() => {
    const items = [...baseNavItems];

    if (canAccessAdmin.value) {
        items.push({
            title: 'Admin',
            href: '/admin',
            icon: Settings2,
        });
    }

    return items;
});

const { hasSubscription, usageFeatures, planPrice } = useBilling();
const limitedFeatures = computed(() =>
    usageFeatures.value.filter((feature) => feature.limit !== null),
);
const showTrialReminder = computed(
    () => !hasSubscription.value && limitedFeatures.value.length > 0,
);
const nextLimitedFeature = computed(() =>
    limitedFeatures.value.find((feature) => (feature.remaining ?? 0) > 0),
);
const trialHeadline = computed(() => {
    if (nextLimitedFeature.value) {
        const remaining = nextLimitedFeature.value.remaining ?? 0;
        const label = nextLimitedFeature.value.label.toLowerCase();
        return `${remaining} ${label} left`;
    }

    return 'Free preview complete';
});
const planPriceLabel = computed(() => planPrice.value ?? '$10/month');
const trialFeatureSummaries = computed(() =>
    limitedFeatures.value.slice(0, 3).map((feature) => ({
        key: feature.key,
        label: feature.label,
        summary:
            feature.limit !== null
                ? `${Math.min(feature.used, feature.limit)}/${feature.limit}`
                : 'Unlimited',
    })),
);
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
        <div
            v-if="showTrialReminder"
            class="rounded-lg border border-primary/30 bg-primary/10 px-4 py-3 text-xs text-primary shadow-sm group-data-[state=collapsed]:hidden"
        >
            <p class="text-[11px] font-semibold uppercase tracking-wide text-primary/90">
                Free preview
            </p>
            <p class="mt-1 text-sm font-semibold text-sidebar-foreground">
                {{ trialHeadline }}
            </p>
            <ul class="mt-2 space-y-1 text-xs text-primary/80">
                <li
                    v-for="feature in trialFeatureSummaries"
                    :key="feature.key"
                    class="flex items-center justify-between gap-3"
                >
                    <span>{{ feature.label }}</span>
                    <span class="font-semibold text-primary">
                        {{ feature.summary }}
                    </span>
                </li>
            </ul>
            <Button
                size="sm"
                class="mt-3 w-full justify-center"
                as-child
            >
                <Link :href="billingRoutes.edit.url()">
                    Upgrade · {{ planPriceLabel }}
                </Link>
            </Button>
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
