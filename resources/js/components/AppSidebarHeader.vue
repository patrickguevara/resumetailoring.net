<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex flex-col gap-2 border-b border-border/60 bg-background/80 px-6 py-4 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-auto supports-[backdrop-filter]:bg-background/65 md:px-8"
    >
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <SidebarTrigger class="-ml-2 md:-ml-1" />
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
            </div>
            <div
                class="hidden items-center gap-2 rounded-full border border-border/60 bg-muted/50 px-3 py-1 text-xs font-medium text-muted-foreground md:inline-flex"
            >
                <span
                    class="inline-flex size-2 rounded-full bg-primary shadow-[0_0_0_3px_rgba(52,211,153,0.35)]"
                />
                <span>Workspace ready</span>
            </div>
        </div>
        <p class="pl-9 text-xs text-muted-foreground md:pl-11">
            Track resume iterations, job research, and evaluation runs in one
            flow.
        </p>
    </header>
</template>
