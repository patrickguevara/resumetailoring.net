import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type UsageFeatureKey =
    | 'resume_uploads'
    | 'evaluations'
    | 'tailored_resumes'
    | 'company_research';

export interface UsageFeatureUsage {
    key: UsageFeatureKey;
    label: string;
    used: number;
    limit: number | null;
    remaining: number | null;
}

export interface UsageSummary {
    has_subscription: boolean;
    features: UsageFeatureUsage[];
}

export interface BillingPlan {
    name?: string | null;
    amount?: number | null;
    currency?: string | null;
    interval?: string | null;
    description?: string | null;
    features: string[];
}

export interface BillingFreeTier {
    label?: string | null;
    helper?: string | null;
    limits: Record<UsageFeatureKey, number>;
}

export interface BillingSubscription {
    status?: string | null;
    active: boolean;
    on_grace_period: boolean;
    renews_at?: string | null;
    ends_at?: string | null;
    trial_ends_at?: string | null;
}

export interface BillingContext {
    plan: BillingPlan | null;
    free_tier: BillingFreeTier | null;
    subscription: BillingSubscription | null;
    usage: UsageSummary | null;
}

export interface FlashMessage {
    type?: string | null;
    message: string;
}

export interface UsageLimitNotice {
    message: string;
    feature: UsageFeatureKey | string;
    limit: number | null;
}

export interface AdminContext {
    can_access_admin: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    admin: AdminContext;
    sidebarOpen: boolean;
    billing: BillingContext;
    flash?: FlashMessage | null;
    usageLimit?: UsageLimitNotice | null;
};

export interface SocialAccount {
    provider: string;
    provider_id: string;
    name: string | null;
    email: string | null;
    avatar: string | null;
    connected_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    has_linked_linkedin?: boolean;
}

export type BreadcrumbItemType = BreadcrumbItem;
