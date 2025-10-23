<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import jobsRoutes from '@/routes/jobs';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BriefcaseBusiness,
    CircleCheck,
    FileText,
    Sparkles,
} from 'lucide-vue-next';

interface JobSummary {
    id: number;
    title?: string | null;
    company?: string | null;
    source_url?: string | null;
    source_label: string;
    is_manual: boolean;
    evaluations_count: number;
    tailored_resumes_count: number;
    has_tailored_resume: boolean;
    has_company_research: boolean;
    last_company_research_at?: string | null;
    last_evaluated_at?: string | null;
    last_tailored_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

const props = defineProps<{
    jobs: JobSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jobs',
        href: jobsRoutes.index.url(),
    },
];

const hasJobs = computed(() => props.jobs.length > 0);

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});

const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const formatDate = (value?: string | null) => {
    if (!value) {
        return null;
    }

    return dateFormatter.format(new Date(value));
};

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return null;
    }

    return dateTimeFormatter.format(new Date(value));
};

const extractHostname = (value?: string | null) => {
    if (!value) {
        return null;
    }

    try {
        return new URL(value).hostname.replace(/^www\./, '');
    } catch {
        return value;
    }
};
</script>

<template>
    <Head title="Jobs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-10 px-6 py-8">
            <section class="max-w-3xl space-y-3">
                <p
                    class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-primary"
                >
                    <span
                        class="inline-flex size-6 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <BriefcaseBusiness class="size-3.5" />
                    </span>
                    Opportunity tracker
                </p>
                <h1 class="text-3xl font-semibold text-foreground">
                    Roles you're targeting
                </h1>
                <p class="text-sm text-muted-foreground">
                    Centralise every role, surface tailored resume coverage, and
                    keep company research one click away.
                </p>
            </section>

            <section
                class="flex flex-col gap-5 rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
            >
                <header class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">
                            Job pipeline
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Track evaluation runs, tailored resumes, and
                            research readiness.
                        </p>
                    </div>
                    <span
                        class="rounded-full border border-border/60 bg-muted/40 px-3 py-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        {{ props.jobs.length }} job{{ props.jobs.length === 1 ? '' : 's' }}
                    </span>
                </header>

                <div
                    v-if="hasJobs"
                    class="-mx-4 overflow-hidden rounded-xl border border-border/60 md:mx-0"
                >
                    <table
                        class="min-w-full divide-y divide-border/60 text-sm"
                    >
                        <thead
                            class="bg-muted/40 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">
                                    Job Title
                                </th>
                                <th scope="col" class="px-4 py-3 text-left">
                                    Company
                                </th>
                                <th scope="col" class="px-4 py-3 text-left">
                                    Insights
                                </th>
                                <th scope="col" class="px-4 py-3 text-left">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60 bg-card/60">
                            <tr
                                v-for="job in props.jobs"
                                :key="job.id"
                                class="transition hover:bg-accent/10"
                            >
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-sm font-semibold text-foreground"
                                            >
                                                {{ job.title || 'Untitled role' }}
                                            </span>
                                            <Badge
                                                v-if="job.is_manual"
                                                variant="outline"
                                                class="border-muted/60 text-xs text-muted-foreground"
                                            >
                                                Manual
                                            </Badge>
                                        </div>
                                        <div
                                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <template v-if="job.source_url">
                                                <a
                                                    :href="job.source_url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline"
                                                >
                                                    <FileText class="size-3" />
                                                    {{ extractHostname(job.source_url) }}
                                                </a>
                                            </template>
                                            <span v-else>
                                                {{ job.source_label }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-col gap-1 text-sm">
                                        <span class="font-medium text-foreground">
                                            {{ job.company || '—' }}
                                        </span>
                                        <span
                                            v-if="formatDate(job.created_at)"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Added {{ formatDate(job.created_at) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Badge
                                            :class="
                                                job.has_tailored_resume
                                                    ? 'border-success/30 bg-success/10 text-success'
                                                    : 'border-muted/60 bg-muted/40 text-muted-foreground'
                                            "
                                        >
                                            <CircleCheck class="size-[13px]" />
                                            {{
                                                job.has_tailored_resume
                                                    ? 'Tailored resume'
                                                    : 'Base resume only'
                                            }}
                                        </Badge>
                                        <Badge
                                            :class="
                                                job.has_company_research
                                                    ? 'border-accent/40 bg-accent/15 text-foreground'
                                                    : 'border-muted/60 bg-muted/40 text-muted-foreground'
                                            "
                                        >
                                            <Sparkles class="size-[13px]" />
                                            {{
                                                job.has_company_research
                                                    ? 'Research ready'
                                                    : 'Research pending'
                                            }}
                                        </Badge>
                                        <Badge
                                            class="border-secondary/40 bg-secondary/20 text-secondary-foreground"
                                        >
                                            <FileText class="size-[13px]" />
                                            {{ job.evaluations_count }}
                                            evaluation{{ job.evaluations_count === 1 ? '' : 's' }}
                                        </Badge>
                                    </div>
                                    <div
                                        class="mt-2 flex flex-col gap-1 text-xs text-muted-foreground"
                                    >
                                        <span
                                            v-if="formatDateTime(job.last_company_research_at)"
                                        >
                                            Research
                                            {{ formatDateTime(job.last_company_research_at) }}
                                        </span>
                                        <span
                                            v-if="formatDateTime(job.last_evaluated_at)"
                                        >
                                            Last evaluation ·
                                            {{ formatDateTime(job.last_evaluated_at) }}
                                        </span>
                                        <span
                                            v-if="formatDateTime(job.last_tailored_at)"
                                        >
                                            Tailored {{ formatDateTime(job.last_tailored_at) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Button size="sm" as-child>
                                            <Link
                                                :href="
                                                    jobsRoutes.show({
                                                        job: job.id,
                                                    }).url
                                                "
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                        <Button
                                            v-if="job.source_url"
                                            size="sm"
                                            variant="outline"
                                            as-child
                                        >
                                            <a
                                                :href="job.source_url"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                View posting
                                            </a>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="flex flex-col items-start gap-5 rounded-xl border border-dashed border-border/60 bg-background/70 p-8 text-sm text-muted-foreground"
                >
                    <div class="flex items-center gap-3 text-primary">
                        <Sparkles class="size-5" />
                        <span class="text-sm font-semibold">
                            No jobs tracked yet
                        </span>
                    </div>
                    <p>
                        Evaluate a resume against a job description to have it
                        appear here. We’ll keep tabs on tailored resumes and
                        company research as you go.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
