<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useBilling } from '@/composables/useBilling';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import billingRoutes from '@/routes/billing';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Invoice {
    id: string;
    number?: string | null;
    total?: string | null;
    status?: string | null;
    date?: string | null;
    receipt_url?: string | null;
    invoice_pdf?: string | null;
}

const props = defineProps<{ invoices: Invoice[] }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Billing & usage',
        href: billingRoutes.edit.url(),
    },
];

const { billing, hasSubscription, usageFeatures, planPrice, planName } =
    useBilling();

const plan = computed(() => billing.value.plan);
const subscription = computed(() => billing.value.subscription);
const planPriceLabel = computed(() => planPrice.value ?? '$10/month');
const statusLabel = computed(() =>
    hasSubscription.value ? 'Active' : 'Free preview',
);
const statusVariant = computed(() =>
    hasSubscription.value ? 'default' : 'outline',
);
const renewalFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});
const renewalLabel = computed(() => {
    if (!subscription.value?.renews_at) {
        return null;
    }

    return renewalFormatter.format(new Date(subscription.value.renews_at));
});
const invoices = computed(() => props.invoices ?? []);
const hasInvoices = computed(() => invoices.value.length > 0);
const usageList = computed(() => usageFeatures.value);

const primaryAction = computed(() =>
    hasSubscription.value
        ? {
              href: billingRoutes.portal.url(),
              label: 'Manage via Stripe',
          }
        : {
              href: billingRoutes.checkout.url(),
              label: `Upgrade for ${planPriceLabel.value}`,
          },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Billing & usage" />

        <SettingsLayout>
            <section class="space-y-8">
                <div
                    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-wide text-primary uppercase"
                            >
                                Current plan
                            </p>
                            <h1 class="text-2xl font-semibold text-foreground">
                                {{ planName }}
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    plan?.description ||
                                    'Unlimited tailoring, research, and uploads.'
                                }}
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ planPriceLabel }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Credit card charges show as
                                "SAMUELDIGITALWORKSCOM".
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-3">
                            <Badge :variant="statusVariant">
                                {{ statusLabel }}
                            </Badge>
                            <div
                                v-if="renewalLabel"
                                class="text-xs text-muted-foreground"
                            >
                                Renews {{ renewalLabel }}
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <Button as-child>
                                    <Link
                                        :href="primaryAction.href"
                                        method="post"
                                        as="button"
                                        preserve-scroll
                                    >
                                        {{ primaryAction.label }}
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                >
                    <header
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">
                                Usage overview
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Preview how many free actions remain before
                                upgrading.
                            </p>
                        </div>
                    </header>
                    <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="feature in usageList"
                            :key="feature.key"
                            class="rounded-xl border border-border/60 bg-background/80 p-4"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {{ feature.label }}
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-foreground"
                            >
                                <template v-if="feature.limit === null">
                                    Unlimited
                                </template>
                                <template v-else>
                                    {{ feature.used }} / {{ feature.limit }}
                                </template>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="feature.limit === null">
                                    Included with subscription
                                </template>
                                <template v-else>
                                    {{ feature.remaining ?? 0 }} remaining
                                </template>
                            </p>
                        </article>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                >
                    <header
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">
                                Invoices
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Downloads become available after your first
                                payment.
                            </p>
                        </div>
                    </header>
                    <div v-if="hasInvoices" class="mt-5 space-y-3">
                        <article
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/80 px-4 py-3 text-sm"
                        >
                            <div>
                                <p class="font-semibold text-foreground">
                                    Invoice {{ invoice.number ?? invoice.id }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        invoice.date
                                            ? renewalFormatter.format(
                                                  new Date(invoice.date),
                                              )
                                            : 'Pending'
                                    }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-sm font-medium text-foreground"
                                >
                                    {{ invoice.total ?? '—' }}
                                </span>
                                <a
                                    v-if="invoice.invoice_pdf"
                                    :href="invoice.invoice_pdf"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-xs font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Download PDF
                                </a>
                            </div>
                        </article>
                    </div>
                    <div
                        v-else
                        class="mt-5 rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
                    >
                        No invoices yet. Charges will appear here after you
                        subscribe.
                    </div>
                </div>
            </section>
        </SettingsLayout>
    </AppLayout>
</template>
