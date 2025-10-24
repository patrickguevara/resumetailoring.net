<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import evaluationRoutes from '@/routes/evaluations';
import jobsRoutes from '@/routes/jobs';
import resumeRoutes from '@/routes/resumes';
import type { BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Briefcase,
    CalendarClock,
    FileText,
    ListChecks,
    Sparkles,
} from 'lucide-vue-next';

interface DashboardSummary {
    resumes_count: number;
    jobs_count: number;
    evaluations_count: number;
    tailored_resumes_count: number;
    last_activity_at?: string | null;
}

interface RecentResume {
    id: number;
    title?: string | null;
    slug: string;
    evaluations_count: number;
    tailored_count: number;
    updated_at?: string | null;
}

interface RecentJob {
    id: number;
    title?: string | null;
    company?: string | null;
    source_label?: string | null;
    is_manual: boolean;
    evaluations_count: number;
    tailored_count: number;
    updated_at?: string | null;
}

interface RecentEvaluation {
    id: number;
    status: string;
    headline?: string | null;
    model?: string | null;
    completed_at?: string | null;
    created_at?: string | null;
    resume: {
        title?: string | null;
        slug?: string | null;
    };
    job_description: {
        id?: number | null;
        title?: string | null;
        company?: string | null;
        url?: string | null;
        source_label?: string | null;
        is_manual: boolean;
    };
}

const props = defineProps<{
    summary: DashboardSummary;
    recent: {
        resumes: RecentResume[];
        jobs: RecentJob[];
        evaluations: RecentEvaluation[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const numberFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 0,
});
const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});
const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const formatNumber = (value: number) => numberFormatter.format(value);
const formatPlural = (count: number, singular: string, plural?: string) => {
    const noun = count === 1 ? singular : plural ?? `${singular}s`;
    return `${formatNumber(count)} ${noun}`;
};
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

const lastActivityLabel = computed(
    () => formatDateTime(props.summary.last_activity_at) ?? 'No activity recorded yet',
);

const summaryItems = computed(() => [
    {
        key: 'resumes',
        label: 'Resumes',
        count: props.summary.resumes_count,
        helper:
            props.summary.resumes_count === 1
                ? 'Saved resume'
                : 'Saved resumes',
        icon: FileText,
    },
    {
        key: 'jobs',
        label: 'Jobs',
        count: props.summary.jobs_count,
        helper:
            props.summary.jobs_count === 1 ? 'Tracked job' : 'Tracked jobs',
        icon: Briefcase,
    },
    {
        key: 'evaluations',
        label: 'Evaluations',
        count: props.summary.evaluations_count,
        helper:
            props.summary.evaluations_count === 1
                ? 'Evaluation run'
                : 'Evaluation runs',
        icon: ListChecks,
    },
    {
        key: 'tailored',
        label: 'Tailored resumes',
        count: props.summary.tailored_resumes_count,
        helper:
            props.summary.tailored_resumes_count === 1
                ? 'Generated version'
                : 'Generated versions',
        icon: Sparkles,
    },
]);

const tools = computed(() => [
    {
        key: 'resumes',
        title: 'Resume workspace',
        description:
            'Upload your base resume, request evaluations, and manage tailored versions in one flow.',
        href: resumeRoutes.index.url(),
        icon: FileText,
        stats: [
            formatPlural(props.summary.resumes_count, 'resume'),
            formatPlural(props.summary.evaluations_count, 'evaluation'),
        ],
        ctaLabel: 'Open resumes',
    },
    {
        key: 'jobs',
        title: 'Job tracker & research',
        description:
            'Collect job descriptions, run company research, and link them to tailored resumes.',
        href: jobsRoutes.index.url(),
        icon: Briefcase,
        stats: [
            formatPlural(props.summary.jobs_count, 'job'),
            formatPlural(
                props.summary.tailored_resumes_count,
                'tailored resume',
                'tailored resumes',
            ),
        ],
        ctaLabel: 'Open jobs',
    },
]);

const recentResumes = computed(() => props.recent.resumes ?? []);
const recentJobs = computed(() => props.recent.jobs ?? []);
const recentEvaluations = computed(() => props.recent.evaluations ?? []);

const resumeTitle = (resume: RecentResume) =>
    resume.title?.trim() || 'Untitled resume';

const jobTitle = (job: RecentJob) =>
    job.title?.trim() ||
    job.company?.trim() ||
    job.source_label?.trim() ||
    'Untitled role';

const jobCompany = (job: RecentJob) => job.company?.trim() ?? null;

const evaluationHeadline = (evaluation: RecentEvaluation) =>
    evaluation.headline?.trim() ||
    `Evaluation for ${evaluation.resume.title ?? 'resume'}`;

const evaluationStatusConfig: Record<
    string,
    { label: string; className: string }
> = {
    completed: {
        label: 'Completed',
        className: 'border-success/30 bg-success/10 text-success',
    },
    failed: {
        label: 'Failed',
        className: 'border-error/30 bg-error/10 text-error',
    },
    pending: {
        label: 'Pending',
        className: 'border-warning/30 bg-warning/10 text-warning',
    },
};

const evaluationStatusLabel = (status: string) =>
    evaluationStatusConfig[status]?.label ?? 'Queued';

const evaluationStatusClass = (status: string) =>
    evaluationStatusConfig[status]?.className ??
    'border-muted/60 bg-muted/40 text-muted-foreground';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 px-6 pb-12 pt-6 md:px-8"
        >
            <div class="flex flex-col gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                    Workspace overview
                </h1>
                <p class="text-sm text-muted-foreground">
                    Keep tabs on resumes, job research, and evaluation runs in
                    one place.
                </p>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <CalendarClock class="size-3.5 shrink-0" />
                    <span>Last activity: {{ lastActivityLabel }}</span>
                </div>
            </div>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card
                    v-for="item in summaryItems"
                    :key="item.key"
                    class="px-6 transition-colors hover:border-primary/50 hover:bg-muted/40 hover:shadow-sm"
                >
                    <div
                        class="flex items-center justify-between text-sm font-medium text-muted-foreground"
                    >
                        <span>{{ item.label }}</span>
                        <component
                            :is="item.icon"
                            class="size-5 text-muted-foreground"
                        />
                    </div>
                    <div class="mt-4 text-3xl font-semibold tracking-tight">
                        {{ formatNumber(item.count) }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ item.helper }}
                    </p>
                </Card>
            </section>

            <section class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                <Card
                    v-for="tool in tools"
                    :key="tool.key"
                    class="px-6"
                >
                    <div class="flex h-full flex-col gap-5">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <component :is="tool.icon" class="size-5" />
                            </div>
                            <div class="space-y-1">
                                <h2 class="text-base font-semibold leading-tight">
                                    {{ tool.title }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ tool.description }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="stat in tool.stats"
                                :key="`${tool.key}-${stat}`"
                                variant="outline"
                                class="border-border/60 bg-muted/40 text-muted-foreground"
                            >
                                {{ stat }}
                            </Badge>
                        </div>
                        <div class="mt-auto flex flex-wrap gap-3">
                            <Button as-child>
                                <Link :href="tool.href">
                                    {{ tool.ctaLabel }}
                                </Link>
                            </Button>
                            <Link
                                :href="tool.href"
                                class="text-sm font-medium text-primary transition hover:text-primary/80"
                            >
                                Review progress →
                            </Link>
                        </div>
                    </div>
                </Card>
                <div
                    class="flex h-full flex-col justify-between gap-4 rounded-xl border border-dashed border-border/60 bg-muted/30 p-6 text-sm text-muted-foreground"
                >
                    <div class="flex items-center gap-3 text-primary">
                        <Sparkles class="size-5" />
                        <span class="text-sm font-semibold text-primary">
                            More tools on the way
                        </span>
                    </div>
                    <p>
                        We are exploring interview prep, networking trackers,
                        and application timelines next. Let us know what would
                        help your search the most.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            variant="outline"
                            class="border-primary/40 bg-primary/10 text-primary"
                        >
                            Ideas welcome
                        </Badge>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <Card class="px-0">
                    <CardHeader class="px-6 pb-4">
                        <CardTitle class="text-base font-semibold">
                            Recent resumes
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 px-6 pb-6">
                        <template v-if="recentResumes.length > 0">
                            <div
                                v-for="resume in recentResumes"
                                :key="resume.id"
                                class="flex items-start justify-between gap-3 rounded-lg border border-transparent p-3 transition hover:border-border/70 hover:bg-muted/40"
                            >
                                <div class="space-y-1">
                                    <Link
                                        :href="
                                            resumeRoutes.show({
                                                slug: resume.slug,
                                            }).url
                                        "
                                        class="text-sm font-medium leading-tight text-foreground hover:text-primary"
                                    >
                                        {{ resumeTitle(resume) }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        Updated
                                        {{
                                            formatDateTime(resume.updated_at) ??
                                            '—'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex flex-col items-end gap-1 text-xs text-muted-foreground"
                                >
                                    <span>
                                        {{
                                            formatPlural(
                                                resume.evaluations_count,
                                                'evaluation',
                                            )
                                        }}
                                    </span>
                                    <span>
                                        {{
                                            formatPlural(
                                                resume.tailored_count,
                                                'tailored resume',
                                                'tailored resumes',
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <p class="text-sm text-muted-foreground">
                                Upload a resume to start tracking evaluations
                                and tailored versions.
                            </p>
                        </template>
                    </CardContent>
                </Card>

                <Card class="px-0">
                    <CardHeader class="px-6 pb-4">
                        <CardTitle class="text-base font-semibold">
                            Recent jobs
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 px-6 pb-6">
                        <template v-if="recentJobs.length > 0">
                            <div
                                v-for="job in recentJobs"
                                :key="job.id"
                                class="flex items-start justify-between gap-3 rounded-lg border border-transparent p-3 transition hover:border-border/70 hover:bg-muted/40"
                            >
                                <div class="space-y-1">
                                    <Link
                                        :href="
                                            jobsRoutes.show({
                                                job: job.id,
                                            }).url
                                        "
                                        class="text-sm font-medium leading-tight text-foreground hover:text-primary"
                                    >
                                        {{ jobTitle(job) }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        <span v-if="jobCompany(job)">
                                            {{ jobCompany(job) }} ·
                                        </span>
                                        {{
                                            formatDateTime(job.updated_at) ??
                                            '—'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex flex-col items-end gap-2 text-xs text-muted-foreground"
                                >
                                    <Badge
                                        variant="outline"
                                        class="border-border/60 bg-muted/40 text-muted-foreground"
                                    >
                                        {{
                                            formatPlural(
                                                job.evaluations_count,
                                                'evaluation',
                                            )
                                        }}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        class="border-border/60 bg-muted/40 text-muted-foreground"
                                    >
                                        {{
                                            formatPlural(
                                                job.tailored_count,
                                                'tailored resume',
                                                'tailored resumes',
                                            )
                                        }}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        class="border-border/60 bg-muted/40 text-muted-foreground"
                                    >
                                        {{ job.is_manual ? 'Manual' : 'Imported' }}
                                    </Badge>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <p class="text-sm text-muted-foreground">
                                Add a job description to unlock research and
                                tailored resume insights.
                            </p>
                        </template>
                    </CardContent>
                </Card>

                <Card class="px-0">
                    <CardHeader class="px-6 pb-4">
                        <CardTitle class="text-base font-semibold">
                            Recent evaluations
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 px-6 pb-6">
                        <template v-if="recentEvaluations.length > 0">
                            <div
                                v-for="evaluation in recentEvaluations"
                                :key="evaluation.id"
                                class="flex flex-col gap-2 rounded-lg border border-transparent p-3 transition hover:border-border/70 hover:bg-muted/40"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <Link
                                            :href="
                                                evaluationRoutes.show({
                                                    evaluation: evaluation.id,
                                                }).url
                                            "
                                            class="text-sm font-medium leading-tight text-foreground hover:text-primary"
                                        >
                                            {{ evaluationHeadline(evaluation) }}
                                        </Link>
                                        <div
                                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <template
                                                v-if="evaluation.resume.title"
                                            >
                                                <span>
                                                    Resume ·
                                                    <Link
                                                        v-if="
                                                            evaluation.resume
                                                                .slug
                                                        "
                                                        :href="
                                                            resumeRoutes.show({
                                                                slug: evaluation
                                                                    .resume
                                                                    .slug,
                                                            }).url
                                                        "
                                                        class="hover:text-primary"
                                                    >
                                                        {{
                                                            evaluation.resume
                                                                .title
                                                        }}
                                                    </Link>
                                                    <span v-else>
                                                        {{
                                                            evaluation.resume
                                                                .title
                                                        }}
                                                    </span>
                                                </span>
                                            </template>
                                            <template
                                                v-if="
                                                    evaluation.job_description
                                                        .title ||
                                                    evaluation.job_description
                                                        .company
                                                "
                                            >
                                                <span>
                                                    Job ·
                                                    <Link
                                                        v-if="
                                                            evaluation
                                                                .job_description
                                                                .id
                                                        "
                                                        :href="
                                                            jobsRoutes.show({
                                                                job: evaluation
                                                                    .job_description
                                                                    .id,
                                                            }).url
                                                        "
                                                        class="hover:text-primary"
                                                    >
                                                        {{
                                                            evaluation
                                                                .job_description
                                                                .title ??
                                                            evaluation
                                                                .job_description
                                                                .company ??
                                                            evaluation
                                                                .job_description
                                                                .source_label
                                                        }}
                                                    </Link>
                                                    <span v-else>
                                                        {{
                                                            evaluation
                                                                .job_description
                                                                .title ??
                                                            evaluation
                                                                .job_description
                                                                .company ??
                                                            evaluation
                                                                .job_description
                                                                .source_label
                                                        }}
                                                    </span>
                                                </span>
                                            </template>
                                            <span>
                                                {{
                                                    formatDateTime(
                                                        evaluation.completed_at ??
                                                            evaluation.created_at,
                                                    ) ?? 'Queued'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <Badge
                                        class="border text-xs font-medium"
                                        :class="evaluationStatusClass(
                                            evaluation.status,
                                        )"
                                    >
                                        {{
                                            evaluationStatusLabel(
                                                evaluation.status,
                                            )
                                        }}
                                    </Badge>
                                </div>
                                <div
                                    v-if="evaluation.model"
                                    class="text-xs text-muted-foreground"
                                >
                                    Model · {{ evaluation.model }}
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <p class="text-sm text-muted-foreground">
                                Run your first evaluation from any resume to see
                                results here.
                            </p>
                        </template>
                    </CardContent>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
