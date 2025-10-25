<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useBilling } from '@/composables/useBilling';
import billingRoutes from '@/routes/billing';
import { dashboard, login, register } from '@/routes';
import type { AppPageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<AppPageProps>();
const isAuthenticated = computed(() => Boolean(page.props.auth.user));

const {
    planName,
    planPrice,
    billing,
    hasSubscription,
} = useBilling();

const plan = computed(() => billing.value.plan);
const planPriceLabel = computed(() => planPrice.value ?? '$10/month');
const planFeatureList = computed(() => plan.value?.features ?? [
    'Unlimited resume uploads',
    'Unlimited evaluations',
    'Unlimited tailored resumes',
    'Unlimited company research',
]);

const freeTierLimits = computed<Record<string, number>>(
    () => billing.value.free_tier?.limits ?? {},
);

const previewHighlights = computed(() => {
    const labels: Record<string, string> = {
        resume_uploads: 'Resume upload',
        evaluations: 'Evaluation run',
        tailored_resumes: 'Tailored resume',
        company_research: 'Company research request',
    };

    return Object.entries(labels).map(([key, label]) => {
        const count = freeTierLimits.value[key] ?? 0;
        if (count <= 0) {
            return `${label} · Upgrade to unlock`;
        }

        const noun = count === 1 ? label : `${label}s`;
        return `${count} ${noun} included`;
    });
});

type RouteHref = NonNullable<InertiaLinkProps['href']>;

interface CtaAction {
    href: RouteHref;
    method?: 'get' | 'post';
    label: string;
    asButton?: boolean;
}

const primaryCta = computed<CtaAction>(() => {
    if (!isAuthenticated.value) {
        return {
            href: register(),
            label: 'Start free preview',
        };
    }

    if (hasSubscription.value) {
        return {
            href: billingRoutes.portal(),
            method: 'post',
            label: 'Manage billing',
            asButton: true,
        };
    }

    return {
        href: billingRoutes.checkout(),
        method: 'post',
        label: `Upgrade for ${planPriceLabel.value}`,
        asButton: true,
    };
});

const secondaryCta = computed<CtaAction>(() => {
    if (!isAuthenticated.value) {
        return {
            href: login(),
            label: 'Log in',
        };
    }

    return {
        href: dashboard(),
        label: 'Back to dashboard',
    };
});

const sellingPoints = [
    {
        title: 'Predictable pricing',
        description:
            'One flat plan. No usage overages, no surprise invoices, and you keep every workflow unlocked.',
    },
    {
        title: 'Free preview included',
        description:
            'Upload once, evaluate once, and generate one tailored resume before you ever pay.',
    },
    {
        title: 'Cancel anytime',
        description:
            'Pause or cancel in one click inside the app. No emails or hoops to jump through.',
    },
];
</script>

<template>
    <Head title="Pricing" />

    <div
        class="relative min-h-screen bg-[#f9f7f3] text-[#1b1b18] antialiased dark:bg-[#05060a] dark:text-[#ededec]"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 -top-32 flex justify-center overflow-hidden blur-3xl"
        >
            <div
                class="h-72 w-[46rem] bg-gradient-to-tr from-[#6ee7b7]/70 via-[#c4b5fd]/50 to-[#f472b6]/40 opacity-60 dark:from-[#34d399]/30 dark:via-[#60a5fa]/20 dark:to-[#f472b6]/20"
            ></div>
        </div>

        <div class="relative mx-auto flex max-w-6xl flex-col px-6 pb-24 pt-8">
            <header class="flex items-center justify-between py-4">
                <Link :href="isAuthenticated ? dashboard() : register()" class="flex items-center gap-3">
                    <AppLogoIcon class="size-10" />
                    <span class="text-base font-semibold leading-tight">
                        Resume Tailoring
                    </span>
                </Link>

                <nav class="hidden items-center gap-6 text-sm font-medium md:flex">
                    <Link :href="secondaryCta.href" class="transition hover:text-[#0f172a] dark:hover:text-white">
                        {{ secondaryCta.label }}
                    </Link>
                </nav>

                <div class="md:hidden">
                    <Link
                        :href="secondaryCta.href"
                        class="rounded-md bg-[#0f172a] px-3 py-2 text-sm font-medium text-white transition hover:bg-[#0c1421] dark:bg-[#38bdf8] dark:text-[#0b1120] dark:hover:bg-[#0ea5e9]"
                    >
                        {{ secondaryCta.label }}
                    </Link>
                </div>
            </header>

            <main class="flex flex-1 flex-col">
                <section class="grid gap-10 py-16 md:grid-cols-[1.1fr,0.9fr] md:items-start">
                    <div class="space-y-6">
                        <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-[#6b6b68] dark:text-[#9ca3af]">
                            Simple pricing
                        </p>
                        <div>
                            <h1 class="text-4xl font-semibold leading-tight sm:text-5xl">
                                Unlock unlimited tailoring for {{ planPriceLabel }}.
                            </h1>
                            <p class="mt-4 text-lg leading-relaxed text-[#545451] dark:text-[#b3b3ae]">
                                Keep resume uploads, evaluations, tailored drafts, and company research all in
                                one workspace. Free preview lets you run the full flow once before you decide.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-4">
                            <Link
                                :href="primaryCta.href"
                                :method="primaryCta.method"
                                :as="primaryCta.method ? 'button' : 'a'"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-[#0f172a] px-6 py-3 text-base font-medium text-white transition hover:bg-[#0c1421] dark:bg-[#38bdf8] dark:text-[#0b1120] dark:hover:bg-[#0ea5e9]"
                                preserve-scroll
                            >
                                {{ primaryCta.label }}
                            </Link>
                            <Link
                                :href="secondaryCta.href"
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-[#d0d0cd] px-6 py-3 text-base font-medium text-[#1c1c19] transition hover:border-[#0f172a] hover:text-[#0f172a] dark:border-[#1f2937] dark:text-[#d1d5db] dark:hover:border-[#38bdf8] dark:hover:text-[#38bdf8]"
                            >
                                {{ secondaryCta.label }}
                            </Link>
                        </div>

                        <div class="rounded-2xl border border-[#deded8] bg-white/80 p-5 text-sm shadow-lg dark:border-[#1f2937] dark:bg-[#0f172a]/40">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#6b6b68] dark:text-[#9ca3af]">
                                Free preview includes
                            </p>
                            <ul class="mt-3 space-y-2">
                                <li
                                    v-for="highlight in previewHighlights"
                                    :key="highlight"
                                    class="flex items-center gap-3 text-[#1c1c19] dark:text-[#d1d5db]"
                                >
                                    <span class="inline-flex size-5 items-center justify-center rounded-full bg-[#0f172a] text-xs font-semibold text-white dark:bg-[#38bdf8] dark:text-[#0b1120]">
                                        •
                                    </span>
                                    {{ highlight }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-[#deded8] bg-white/90 p-8 shadow-2xl dark:border-[#1f2937] dark:bg-[#0b1120]/90">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#6b6b68] dark:text-[#9ca3af]">
                            {{ planName }} plan
                        </p>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-5xl font-semibold text-[#0f172a] dark:text-white">
                                {{ planPriceLabel.split('/')[0] }}
                            </span>
                            <span class="text-sm uppercase tracking-wide text-[#6b6b68] dark:text-[#9ca3af]">
                                / {{ planPriceLabel.includes('/') ? planPriceLabel.split('/')[1] : 'month' }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-[#545451] dark:text-[#b3b3ae]">
                            {{ plan?.description || 'Unlimited resume uploads, evaluations, tailoring, and research.' }}
                        </p>
                        <ul class="mt-6 space-y-3 text-sm">
                            <li
                                v-for="feature in planFeatureList"
                                :key="feature"
                                class="flex items-center gap-3 text-[#1c1c19] dark:text-[#d1d5db]"
                            >
                                <span class="inline-flex size-6 items-center justify-center rounded-full bg-[#ecfccb] text-sm font-semibold text-[#166534] dark:bg-[#064e3b]/40 dark:text-[#bbf7d0]">
                                    ✓
                                </span>
                                {{ feature }}
                            </li>
                        </ul>
                        <div class="mt-6 rounded-xl border border-dashed border-[#d0d0cd] bg-[#f7f5ef] p-4 text-xs text-[#4a4a45] dark:border-[#1f2937] dark:bg-[#111827]/60 dark:text-[#cbd5f5]">
                            Billing runs through Stripe. Upgrade instantly, downgrade anytime, and keep your workflow running with unlimited usage.
                        </div>
                    </div>
                </section>

                <section class="space-y-8">
                    <div class="space-y-3 text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#6b6b68] dark:text-[#9ca3af]">
                            Why it works
                        </p>
                        <h2 class="text-3xl font-semibold">
                            Designed for operators who are actively applying.
                        </h2>
                        <p class="text-base text-[#545451] dark:text-[#b3b3ae]">
                            The pricing stays flat so you can iterate quickly without worrying about metered costs.
                        </p>
                    </div>
                    <div class="grid gap-6 md:grid-cols-3">
                        <article
                            v-for="point in sellingPoints"
                            :key="point.title"
                            class="rounded-2xl border border-[#deded8] bg-white/90 p-6 text-left shadow-sm dark:border-[#1f2937] dark:bg-[#0b1120]/80"
                        >
                            <h3 class="text-lg font-semibold text-[#0f172a] dark:text-white">
                                {{ point.title }}
                            </h3>
                            <p class="mt-2 text-sm leading-relaxed text-[#545451] dark:text-[#b3b3ae]">
                                {{ point.description }}
                            </p>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
