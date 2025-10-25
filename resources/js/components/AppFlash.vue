<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import billingRoutes from '@/routes/billing';
import type { AppPageProps, FlashMessage, UsageLimitNotice } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<AppPageProps>();

const flash = computed<FlashMessage | null>(() => page.props.flash ?? null);
const usageLimit = computed<UsageLimitNotice | null>(
    () => page.props.usageLimit ?? null,
);

const flashVariant = computed(() =>
    flash.value?.type === 'error' ? 'destructive' : 'default',
);
</script>

<template>
    <div
        v-if="flash || usageLimit"
        class="mb-6 space-y-3 rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm"
    >
        <Alert v-if="flash" :variant="flashVariant">
            <AlertTitle class="text-sm font-semibold">
                {{ flash.type ?? 'Notice' }}
            </AlertTitle>
            <AlertDescription class="text-sm text-muted-foreground">
                {{ flash.message }}
            </AlertDescription>
        </Alert>

        <Alert
            v-if="usageLimit"
            class="border-primary/40 bg-primary/5 text-primary-foreground dark:text-primary-foreground"
        >
            <AlertTitle class="text-sm font-semibold text-primary">
                Limit reached
            </AlertTitle>
            <AlertDescription
                class="flex flex-col gap-3 text-sm text-primary/80 sm:flex-row sm:items-center sm:justify-between"
            >
                <span class="text-base font-medium text-foreground">
                    {{ usageLimit.message }}
                </span>
                <Button size="sm" as-child>
                    <Link :href="billingRoutes.edit.url()">
                        Upgrade account
                    </Link>
                </Button>
            </AlertDescription>
        </Alert>
    </div>
</template>
