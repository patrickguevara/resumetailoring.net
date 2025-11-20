import type {
    AppPageProps,
    BillingContext,
    UsageFeatureKey,
    UsageFeatureUsage,
} from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export const useBilling = () => {
    const page = usePage<AppPageProps>();

    const billing = computed<BillingContext>(() => page.props.billing);

    const usageFeatures = computed<UsageFeatureUsage[]>(
        () => billing.value?.usage?.features ?? [],
    );

    const usageByKey = computed<
        Record<UsageFeatureKey, UsageFeatureUsage | undefined>
    >(() => {
        const entries: Record<UsageFeatureKey, UsageFeatureUsage | undefined> =
            {
                resume_uploads: undefined,
                evaluations: undefined,
                tailored_resumes: undefined,
                company_research: undefined,
            };

        usageFeatures.value.forEach((feature) => {
            entries[feature.key as UsageFeatureKey] = feature;
        });

        return entries;
    });

    const hasSubscription = computed(
        () => billing.value?.usage?.has_subscription ?? false,
    );

    const featureUsage = (key: UsageFeatureKey) =>
        computed(() => usageByKey.value[key]);

    const remaining = (key: UsageFeatureKey) =>
        computed(() => {
            const usage = usageByKey.value[key];

            if (!usage || usage.limit === null || usage.limit === undefined) {
                return null;
            }

            return Math.max(usage.limit - usage.used, 0);
        });

    const limitReached = (key: UsageFeatureKey) =>
        computed(() => {
            const usage = usageByKey.value[key];

            if (!usage || usage.limit === null || usage.limit === undefined) {
                return false;
            }

            return usage.used >= usage.limit;
        });

    const planName = computed(() => billing.value?.plan?.name ?? 'Tailor Pro');

    const planPrice = computed(() => {
        const amount = billing.value?.plan?.amount;
        const currency = billing.value?.plan?.currency ?? 'usd';
        const interval = billing.value?.plan?.interval ?? 'month';

        if (amount === null || amount === undefined) {
            return null;
        }

        const formatter = new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
        });

        return `${formatter.format(amount / 100)}/${interval}`;
    });

    return {
        billing,
        usageFeatures,
        usageByKey,
        hasSubscription,
        featureUsage,
        remaining,
        limitReached,
        planName,
        planPrice,
    };
};

export type BillingHelper = ReturnType<typeof useBilling>;
