<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface OverviewCard {
    key: string;
    label: string;
    value: number;
    helper?: string | null;
    format?: 'percentage';
}

interface UsageAction {
    key: string;
    label: string;
    helper?: string | null;
    count: number;
    avg_per_user: number;
    share: number;
}

const props = defineProps<{
    overview: {
        cards: OverviewCard[];
        totals: OverviewCard[];
    };
    usage: {
        common_actions: UsageAction[];
        totals: {
            tracked_actions: number;
        };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: '/admin',
    },
];

const cards = computed(() => props.overview.cards ?? []);
const totals = computed(() => props.overview.totals ?? []);
const commonActions = computed(() => props.usage.common_actions ?? []);
const trackedActions = computed(() => props.usage.totals?.tracked_actions ?? 0);

const integerFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 0,
});
const decimalFormatter = new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
});

const formatCardValue = (card: OverviewCard) => {
    if (card.format === 'percentage') {
        return `${decimalFormatter.format(card.value ?? 0)}%`;
    }

    return integerFormatter.format(card.value ?? 0);
};

const formatNumber = (value: number) => integerFormatter.format(value ?? 0);
const formatDecimal = (value: number) => decimalFormatter.format(value ?? 0);
const clampShare = (value: number) => Math.min(Math.max(value ?? 0, 0), 100);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Admin overview" />

        <div class="space-y-8">
            <div class="space-y-2">
                <p class="text-sm font-medium text-primary">Internal dashboard</p>
                <h1 class="text-3xl font-semibold tracking-tight text-foreground">
                    Admin overview
                </h1>
                <p class="text-muted-foreground">
                    Monitor signups, subscribers, and how people use key resume tools.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="card in cards" :key="card.key">
                    <CardHeader class="space-y-1 pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            {{ card.label }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-1">
                        <p class="text-3xl font-semibold tracking-tight tabular-nums">
                            {{ formatCardValue(card) }}
                        </p>
                        <p
                            v-if="card.helper"
                            class="text-sm text-muted-foreground"
                        >
                            {{ card.helper }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Usage totals</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Snapshot of records created across all workspaces.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="item in totals"
                                :key="item.key"
                                class="rounded-lg border p-4"
                            >
                                <dt class="text-sm font-medium text-muted-foreground">
                                    {{ item.label }}
                                </dt>
                                <dd class="mt-2 text-2xl font-semibold tracking-tight tabular-nums">
                                    {{ formatNumber(item.value) }}
                                </dd>
                                <p
                                    v-if="item.helper"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ item.helper }}
                                </p>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Common actions</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Totals, average per user, and share of tracked actions
                            ({{ formatNumber(trackedActions) }} overall).
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="action in commonActions"
                            :key="action.key"
                            class="space-y-4 rounded-lg border p-4"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ action.label }}
                                </p>
                                <p
                                    v-if="action.helper"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ action.helper }}
                                </p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                    >
                                        Total
                                    </p>
                                    <p class="text-lg font-semibold tabular-nums">
                                        {{ formatNumber(action.count) }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                    >
                                        Avg / user
                                    </p>
                                    <p class="text-lg font-semibold tabular-nums">
                                        {{ formatDecimal(action.avg_per_user) }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                    >
                                        Share
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 flex-1 rounded-full bg-muted">
                                            <div
                                                class="h-2 rounded-full bg-primary transition-all"
                                                :style="{
                                                    width: `${clampShare(action.share)}%`,
                                                }"
                                            />
                                        </div>
                                        <span class="text-sm font-medium tabular-nums">
                                            {{ formatDecimal(action.share) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
