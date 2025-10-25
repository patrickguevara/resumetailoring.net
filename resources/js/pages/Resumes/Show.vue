<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MarkdownViewer from '@/components/MarkdownViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBilling } from '@/composables/useBilling';
import { userChannel } from '@/lib/realtime';
import billingRoutes from '@/routes/billing';
import jobsRoutes from '@/routes/jobs';
import resumeRoutes from '@/routes/resumes';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import {
    AlertTriangle,
    ArrowUpRight,
    CalendarClock,
    CircleCheck,
    FileText,
    History,
    Loader2,
    ScrollText,
    Sparkles,
} from 'lucide-vue-next';

interface ResumeDetail {
    id: number;
    slug: string;
    title: string;
    description?: string | null;
    content_markdown: string;
    ingestion_status: string;
    ingestion_error?: string | null;
    ingested_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

interface JobDescriptionSummary {
    id: number;
    title?: string | null;
    url?: string | null;
    source_label: string;
    is_manual: boolean;
    company?: string | null;
}

interface Evaluation {
    id: number;
    status: string;
    headline?: string | null;
    model?: string | null;
    notes?: string | null;
    feedback_markdown?: string | null;
    error_message?: string | null;
    completed_at?: string | null;
    created_at?: string | null;
    job_description: JobDescriptionSummary;
    tailored_count: number;
}

interface TailoredResume {
    id: number;
    title?: string | null;
    model?: string | null;
    content_markdown?: string | null;
    evaluation_id?: number | null;
    created_at?: string | null;
    job_description?: JobDescriptionSummary | null;
}

interface ResumeEvaluationUpdatedPayload {
    evaluation: {
        id: number;
        status: string;
        headline?: string | null;
        model?: string | null;
        notes?: string | null;
        feedback_markdown?: string | null;
        feedback_is_truncated?: boolean;
        error_message?: string | null;
        completed_at?: string | null;
        created_at?: string | null;
        tailored_count?: number;
        job_description: JobDescriptionSummary | null;
    };
}

interface TailoredResumeUpdatedPayload {
    status: 'completed' | 'failed';
    evaluation_id: number;
    error_message?: string | null;
    tailored_resume?: {
        id: number;
        title?: string | null;
        model?: string | null;
        content_markdown?: string | null;
        content_is_truncated?: boolean;
        created_at?: string | null;
        evaluation_id?: number | null;
        job_description?: JobDescriptionSummary | null;
    } | null;
}

interface TailoredTargetSummary {
    job_id: number;
    job_title?: string | null;
    company?: string | null;
}

interface ResumeUpdatedPayload {
    resume: ResumeDetail & {
        evaluations_count?: number;
        tailored_count?: number;
        tailored_for?: TailoredTargetSummary[];
    };
}

const props = defineProps<{
    resume: ResumeDetail;
    evaluations: Evaluation[];
    tailored_resumes: TailoredResume[];
}>();

const page = usePage<{
    errors: Record<string, string>;
    auth?: { user?: { id: number } | null } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Resumes',
        href: resumeRoutes.index.url(),
    },
    {
        title: props.resume.title,
        href: resumeRoutes.show({ slug: props.resume.slug }).url,
    },
];

const evaluationForm = useForm({
    job_input_type: 'url',
    job_url: '',
    job_text: '',
    job_title: '',
    job_company: '',
    model: 'gpt-5-nano',
    notes: '',
});

const { hasSubscription, limitReached, remaining, planPrice } = useBilling();
const evaluationLimitReached = limitReached('evaluations');
const evaluationRemaining = remaining('evaluations');
const evaluationBlockedByLimit = computed(
    () => !hasSubscription.value && evaluationLimitReached.value,
);
const planPriceLabel = computed(() => planPrice.value ?? '$10/month');
const evaluationAllowanceCopy = computed(() => {
    if (hasSubscription.value) {
        return 'Unlimited evaluations are included with your plan.';
    }

    const remainingAllowance = evaluationRemaining.value ?? 0;

    if (remainingAllowance > 0) {
        return `${remainingAllowance} free evaluation${
            remainingAllowance === 1 ? '' : 's'
        } left in your preview.`;
    }

    return 'You have used the free evaluation included with your preview.';
});

const availableModels = [
    {
        id: 'gpt-5-nano',
        label: 'gpt-5-nano',
        helper: 'Fast, cost-effective checks',
    },
    {
        id: 'gpt-5-mini',
        label: 'gpt-5-mini',
        helper: 'Deeper analysis for critical roles',
    },
];

const cloneResume = (value: ResumeDetail): ResumeDetail => ({
    ...value,
    description: value.description ?? null,
    ingestion_error: value.ingestion_error ?? null,
    ingested_at: value.ingested_at ?? null,
});

const resumeState = ref<ResumeDetail>(cloneResume(props.resume));
const resume = resumeState;

watch(
    () => props.resume,
    (value) => {
        resume.value = cloneResume(value);
    },
    { deep: true },
);

const expandedTailored = reactive<Record<number, boolean>>({});

const cloneEvaluation = (evaluation: Evaluation): Evaluation => ({
    ...evaluation,
    job_description: { ...evaluation.job_description },
});

const cloneTailored = (tailored: TailoredResume): TailoredResume => ({
    ...tailored,
    content_markdown: tailored.content_markdown ?? null,
    job_description: tailored.job_description
        ? { ...tailored.job_description }
        : null,
});

const evaluations = ref<Evaluation[]>(
    props.evaluations.map(cloneEvaluation),
);
const tailoredResumes = ref<TailoredResume[]>(
    props.tailored_resumes.map(cloneTailored),
);

watch(
    () => props.evaluations,
    (value) => {
        evaluations.value = value.map(cloneEvaluation);
    },
    { deep: true },
);

watch(
    () => props.tailored_resumes,
    (value) => {
        tailoredResumes.value = value.map(cloneTailored);
    },
    { deep: true },
);

const evaluationFetchInFlight = new Set<number>();
const fetchEvaluationDetails = async (evaluationId: number) => {
    if (evaluationFetchInFlight.has(evaluationId)) {
        return;
    }

    evaluationFetchInFlight.add(evaluationId);

    try {
        const response = await fetch(`/evaluations/${evaluationId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const existing = evaluations.value.find(
            (evaluation) => evaluation.id === evaluationId,
        );

        const jobDescription = data.job_description
            ? {
                  id: data.job_description.id,
                  title: data.job_description.title ?? null,
                  url: data.job_description.url ?? null,
                  source_label: data.job_description.source_label,
                  is_manual: data.job_description.is_manual,
                  company: data.job_description.company ?? null,
              }
            : existing?.job_description ?? {
                  id: evaluationId,
                  title: 'Job description',
                  url: null,
                  source_label: 'Job description',
                  is_manual: true,
                  company: null,
              };

        const normalized: Evaluation = {
            id: data.id,
            status: data.status,
            headline: data.headline ?? null,
            model: data.model ?? null,
            notes: data.notes ?? null,
            feedback_markdown: data.feedback_markdown ?? null,
            error_message: data.error_message ?? null,
            completed_at: data.completed_at ?? null,
            created_at: data.created_at ?? null,
            job_description: jobDescription,
            tailored_count: data.tailored_count ?? existing?.tailored_count ?? 0,
        };

        const existingIndex = evaluations.value.findIndex(
            (evaluation) => evaluation.id === normalized.id,
        );

        if (existingIndex >= 0) {
            evaluations.value.splice(existingIndex, 1, normalized);
        } else {
            evaluations.value = [normalized, ...evaluations.value];
        }
    } catch (error) {
        console.error('Failed to fetch evaluation details', error);
    } finally {
        evaluationFetchInFlight.delete(evaluationId);
    }
};

const tailoredFetchInFlight = new Set<number>();
const fetchTailoredResume = async (tailoredId: number) => {
    if (tailoredFetchInFlight.has(tailoredId)) {
        return;
    }

    tailoredFetchInFlight.add(tailoredId);

    try {
        const response = await fetch(`/tailored-resumes/${tailoredId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        const normalized: TailoredResume = {
            id: data.id,
            title: data.title ?? null,
            model: data.model ?? null,
            content_markdown: data.content_markdown ?? null,
            created_at: data.created_at ?? null,
            evaluation_id: data.evaluation_id ?? null,
            job_description: data.job_description
                ? {
                      id: data.job_description.id,
                      title: data.job_description.title ?? null,
                      url: data.job_description.url ?? null,
                      source_label: data.job_description.source_label,
                      is_manual: data.job_description.is_manual,
                      company: data.job_description.company ?? null,
                  }
                : null,
        };

        const existingIndex = tailoredResumes.value.findIndex(
            (item) => item.id === normalized.id,
        );

        if (existingIndex >= 0) {
            tailoredResumes.value.splice(existingIndex, 1, normalized);
        } else {
            tailoredResumes.value = [normalized, ...tailoredResumes.value];
        }
    } catch (error) {
        console.error('Failed to fetch tailored resume', error);
    } finally {
        tailoredFetchInFlight.delete(tailoredId);
    }
};

watch(
    () => tailoredResumes.value.map((tailored) => tailored.id),
    (tailoredIds) => {
        tailoredIds.forEach((id) => {
            if (expandedTailored[id] === undefined) {
                expandedTailored[id] = false;
            }
        });
    },
    { immediate: true },
);

watch(
    () => evaluationForm.job_input_type,
    (type) => {
        if (type === 'url') {
            evaluationForm.clearErrors('job_text');

            return;
        }

        evaluationForm.clearErrors('job_url');
    },
);

const hasEvaluations = computed(() => evaluations.value.length > 0);
const hasTailoredResumes = computed(() => tailoredResumes.value.length > 0);
const totalEvaluations = computed(() => evaluations.value.length);
const totalTailoredResumes = computed(() => tailoredResumes.value.length);
const hasResumeContent = computed(
    () =>
        resume.value.ingestion_status === 'completed' &&
        Boolean(resume.value.content_markdown?.trim()),
);
const isResumeProcessing = computed(
    () => resume.value.ingestion_status === 'processing',
);
const resumeProcessingFailed = computed(
    () => resume.value.ingestion_status === 'failed',
);
const ingestionErrorMessage = computed(
    () => resume.value.ingestion_error ?? null,
);
const canRunEvaluation = computed(() => {
    if (resume.value.ingestion_status !== 'completed') {
        return false;
    }

    if (evaluationBlockedByLimit.value) {
        return false;
    }

    return true;
});
const ingestionStatusLabel = computed(() => {
    switch (resume.value.ingestion_status) {
        case 'processing':
            return 'Processing upload';
        case 'failed':
            return 'Processing failed';
        default:
            return 'Ready';
    }
});
const ingestionStatusBadgeClass = computed(() => {
    if (resumeProcessingFailed.value) {
        return 'border-error/40 bg-error/10 text-error';
    }

    if (isResumeProcessing.value) {
        return 'border-warning/40 bg-warning/10 text-warning';
    }

    return 'border-success/40 bg-success/10 text-success';
});
const evaluationDisabledMessage = computed(() => {
    if (isResumeProcessing.value) {
        return 'Resume upload is still being processed. Hold tight for the final markdown.';
    }

    if (resumeProcessingFailed.value) {
        return (
            ingestionErrorMessage.value ??
            'Resume processing failed. Upload a new file or contact support.'
        );
    }

    if (evaluationBlockedByLimit.value) {
        return `Free evaluation used. Upgrade for ${planPriceLabel.value} to keep going.`;
    }

    return null;
});

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
    evaluationStatusConfig[status]?.label ?? 'Pending';

const evaluationStatusClass = (status: string) =>
    evaluationStatusConfig[status]?.className ??
    'border-muted/60 bg-muted/40 text-muted-foreground';

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

const scrollToSection = (sectionId: string) => {
    if (typeof window === 'undefined') {
        return;
    }

    const element = document.getElementById(sectionId);

    if (!element) {
        return;
    }

    const headerOffset = 96;
    const elementPosition =
        element.getBoundingClientRect().top + window.scrollY;
    const offsetPosition = Math.max(elementPosition - headerOffset, 0);

    window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth',
    });
};

const submitEvaluation = () => {
    if (!canRunEvaluation.value) {
        return;
    }

    evaluationForm.post(
        resumeRoutes.evaluations.store.url({ resume: resume.value.slug }),
        {
            preserveScroll: true,
            onSuccess: () => evaluationForm.reset(),
        },
    );
};

const toggleTailoredPreview = (id: number) => {
    expandedTailored[id] = !expandedTailored[id];

    if (expandedTailored[id]) {
        const tailored = tailoredResumes.value.find(
            (item) => item.id === id,
        );

        if (!tailored || !tailored.content_markdown) {
            void fetchTailoredResume(id);
        }
    }
};

const handleResumeEvaluationUpdated = (
    payload: ResumeEvaluationUpdatedPayload,
) => {
    const isTruncated = payload.evaluation.feedback_is_truncated ?? false;
    const jobDescription = payload.evaluation.job_description
        ? { ...payload.evaluation.job_description }
        : {
              id: payload.evaluation.id,
              title: 'Job description',
              url: null,
              source_label: 'Job description',
              is_manual: true,
              company: null,
          };

    const normalized: Evaluation = {
        id: payload.evaluation.id,
        status: payload.evaluation.status,
        headline: payload.evaluation.headline ?? null,
        model: payload.evaluation.model ?? null,
        notes: payload.evaluation.notes ?? null,
        feedback_markdown: payload.evaluation.feedback_markdown ?? null,
        error_message: payload.evaluation.error_message ?? null,
        completed_at: payload.evaluation.completed_at ?? null,
        created_at: payload.evaluation.created_at ?? null,
        job_description: jobDescription,
        tailored_count: payload.evaluation.tailored_count ?? 0,
    };

    const existingIndex = evaluations.value.findIndex(
        (evaluation) => evaluation.id === normalized.id,
    );

    if (existingIndex >= 0) {
        evaluations.value.splice(existingIndex, 1, normalized);
    } else {
        evaluations.value = [normalized, ...evaluations.value];
    }

    if (isTruncated) {
        void fetchEvaluationDetails(normalized.id);
    }
};

const handleTailoredResumeUpdated = (
    payload: TailoredResumeUpdatedPayload,
) => {
    if (payload.status === 'failed') {
        if (payload.error_message) {
            console.error(payload.error_message);
        }

        return;
    }

    const data = payload.tailored_resume;

    if (!data) {
        return;
    }

    const normalized: TailoredResume = {
        id: data.id,
        title: data.title ?? null,
        model: data.model ?? null,
        content_markdown: data.content_markdown ?? null,
        created_at: data.created_at ?? null,
        evaluation_id: data.evaluation_id ?? payload.evaluation_id,
        job_description: data.job_description
            ? { ...data.job_description }
            : null,
    };

    const existingIndex = tailoredResumes.value.findIndex(
        (item) => item.id === normalized.id,
    );

    if (existingIndex >= 0) {
        tailoredResumes.value.splice(existingIndex, 1, normalized);
    } else {
        tailoredResumes.value = [normalized, ...tailoredResumes.value];
    }

    expandedTailored[normalized.id] ??= false;

    if (data.content_is_truncated) {
        void fetchTailoredResume(normalized.id);
    }
};

const handleResumeUpdated = (payload: ResumeUpdatedPayload) => {
    if (payload.resume.id !== resume.value.id) {
        return;
    }

    resume.value = cloneResume(payload.resume);
};

const realtimeChannel = ref<ReturnType<typeof userChannel> | null>(null);

const registerRealtimeHandlers = () => {
    const userId = page.props.auth?.user?.id;

    if (!userId) {
        return;
    }

    const channel = userChannel(userId);

    if (!channel) {
        return;
    }

    channel.stopListening('.ResumeEvaluationUpdated');
    channel.stopListening('.TailoredResumeUpdated');
    channel.stopListening('.ResumeUpdated');

    channel.listen('.ResumeEvaluationUpdated', handleResumeEvaluationUpdated);
    channel.listen('.TailoredResumeUpdated', handleTailoredResumeUpdated);
    channel.listen('.ResumeUpdated', handleResumeUpdated);

    realtimeChannel.value = channel;
};

onMounted(() => {
    registerRealtimeHandlers();
});

onBeforeUnmount(() => {
    if (realtimeChannel.value) {
        realtimeChannel.value.stopListening('.ResumeEvaluationUpdated');
        realtimeChannel.value.stopListening('.TailoredResumeUpdated');
        realtimeChannel.value.stopListening('.ResumeUpdated');
    }
});

const globalErrors = computed(() => page.props.errors ?? {});
</script>

<template>
    <Head :title="`Resume · ${resume.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mr-auto w-full max-w-5xl space-y-8 px-6 py-8 xl:max-w-6xl xl:pr-16"
        >
            <section
                class="rounded-2xl border border-border/60 bg-gradient-to-br from-primary/10 via-background to-background p-6 shadow-sm lg:sticky lg:top-6 lg:z-30"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-2">
                        <p
                            class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-primary"
                        >
                            <Sparkles class="size-3.5" />
                            Resume overview
                        </p>
                        <h1 class="text-3xl font-semibold text-foreground">
                            {{ resume.title }}
                        </h1>
                        <p
                            v-if="resume.description"
                            class="max-w-3xl text-sm text-muted-foreground"
                        >
                            {{ resume.description }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm">
                            Duplicate
                        </Button>
                        <Button variant="secondary" size="sm">
                            Export markdown
                        </Button>
                    </div>
                </div>
                <div
                    class="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <CalendarClock class="size-3.5" />
                        Added {{ formatDate(resume.created_at) ?? '—' }}
                    </span>
                    <span class="hidden text-muted-foreground sm:inline">•</span>
                    <span class="flex items-center gap-1 text-muted-foreground">
                        Updated {{ formatDateTime(resume.updated_at) ?? '—' }}
                    </span>
                    <span class="hidden text-muted-foreground sm:inline">•</span>
                    <span
                        :class="[
                            'inline-flex items-center gap-2 rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
                            ingestionStatusBadgeClass,
                        ]"
                    >
                        <Loader2
                            v-if="isResumeProcessing"
                            class="size-3 animate-spin"
                        />
                        <AlertTriangle
                            v-else-if="resumeProcessingFailed"
                            class="size-3"
                        />
                        <CircleCheck v-else class="size-3" />
                        <span>{{ ingestionStatusLabel }}</span>
                    </span>
                    <span class="hidden text-muted-foreground sm:inline">•</span>
                    <span class="flex items-center gap-1 text-muted-foreground">
                        {{ totalEvaluations }}
                        evaluation{{ totalEvaluations === 1 ? '' : 's' }}
                    </span>
                    <span class="hidden text-muted-foreground sm:inline">•</span>
                    <span class="flex items-center gap-1 text-muted-foreground">
                        {{ totalTailoredResumes }}
                        tailored version{{
                            totalTailoredResumes === 1 ? '' : 's'
                        }}
                    </span>
                </div>
                <div class="mt-6 flex flex-col gap-3">
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <button
                            type="button"
                            class="group flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-background/60 px-4 py-3 text-left transition hover:border-primary/60 hover:bg-primary/5"
                            @click="scrollToSection('run-evaluation')"
                        >
                            <div class="flex items-center gap-3">
                                <Sparkles class="size-5 text-primary" />
                                <div>
                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        Run evaluation
                                    </p>
                                    <p class="text-base font-semibold text-foreground">
                                        {{ totalEvaluations }}
                                        <span
                                            class="ml-1 text-sm font-normal text-muted-foreground"
                                        >
                                            runs
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <ArrowUpRight
                                class="size-4 text-muted-foreground transition group-hover:text-foreground"
                            />
                        </button>
                        <button
                            type="button"
                            class="group flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-background/60 px-4 py-3 text-left transition hover:border-primary/60 hover:bg-primary/5"
                            @click="scrollToSection('tailored-resumes')"
                        >
                            <div class="flex items-center gap-3">
                                <FileText class="size-5 text-primary" />
                                <div>
                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        Tailored resumes
                                    </p>
                                    <p class="text-base font-semibold text-foreground">
                                        {{ totalTailoredResumes }}
                                        <span
                                            class="ml-1 text-sm font-normal text-muted-foreground"
                                        >
                                            saved
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <ArrowUpRight
                                class="size-4 text-muted-foreground transition group-hover:text-foreground"
                            />
                        </button>
                        <button
                            type="button"
                            :class="[
                                'group flex items-center justify-between gap-4 rounded-xl border px-4 py-3 text-left transition',
                                resumeProcessingFailed
                                    ? 'border-error/60 bg-error/10 hover:border-error/70 hover:bg-error/15'
                                    : isResumeProcessing
                                      ? 'border-warning/60 bg-warning/10 hover:border-warning/70 hover:bg-warning/15'
                                      : hasResumeContent
                                          ? 'border-success/50 bg-success/10 hover:border-success/60 hover:bg-success/15'
                                          : 'border-border/60 bg-background/60 hover:border-primary/60 hover:bg-primary/5',
                            ]"
                            @click="scrollToSection('resume-preview')"
                        >
                            <div class="flex items-center gap-3">
                                <ScrollText
                                    class="size-5"
                                    :class="
                                        resumeProcessingFailed
                                            ? 'text-error'
                                            : isResumeProcessing
                                              ? 'text-warning'
                                              : hasResumeContent
                                                  ? 'text-success'
                                                  : 'text-muted-foreground'
                                    "
                                />
                                <div>
                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        Resume markdown
                                    </p>
                                    <p
                                        class="text-base font-semibold"
                                        :class="
                                            resumeProcessingFailed
                                                ? 'text-error'
                                                : isResumeProcessing
                                                  ? 'text-warning'
                                                  : hasResumeContent
                                                      ? 'text-success'
                                                      : 'text-foreground'
                                        "
                                    >
                                        {{
                                            resumeProcessingFailed
                                                ? 'Processing failed'
                                                : isResumeProcessing
                                                  ? 'Processing upload'
                                                  : hasResumeContent
                                                      ? 'Ready to review'
                                                      : 'Add content'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <ArrowUpRight
                                :class="[
                                    'size-4 transition',
                                    resumeProcessingFailed
                                        ? 'text-error'
                                        : isResumeProcessing
                                          ? 'text-warning'
                                          : hasResumeContent
                                              ? 'text-success'
                                              : 'text-muted-foreground group-hover:text-foreground',
                                ]"
                            />
                        </button>
                        <button
                            type="button"
                            class="group flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-background/60 px-4 py-3 text-left transition hover:border-primary/60 hover:bg-primary/5"
                            @click="scrollToSection('evaluation-history')"
                        >
                            <div class="flex items-center gap-3">
                                <History class="size-5 text-primary" />
                                <div>
                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        Evaluation history
                                    </p>
                                    <p class="text-base font-semibold text-foreground">
                                        {{ totalEvaluations }}
                                        <span
                                            class="ml-1 text-sm font-normal text-muted-foreground"
                                        >
                                            records
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <ArrowUpRight
                                class="size-4 text-muted-foreground transition group-hover:text-foreground"
                            />
                        </button>
                    </div>
                </div>
            </section>

            <div
                class="grid gap-8 lg:grid-cols-[minmax(0,1fr),minmax(320px,360px)]"
            >
                <aside class="space-y-6">
                    <div
                        id="run-evaluation"
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                <header class="space-y-1">
                    <h2 class="text-lg font-semibold text-foreground">
                        Run a new evaluation
                    </h2>
                    <p class="text-sm text-muted-foreground">
                                Compare this resume against a job by URL or by
                        pasting the description. Choose the model that
                        best fits your review depth.
                    </p>
                </header>

                <div
                    v-if="!evaluationBlockedByLimit"
                    class="mt-3 rounded-lg border border-border/50 bg-muted/40 p-3 text-xs text-muted-foreground"
                >
                    {{ evaluationAllowanceCopy }}
                </div>

                <div
                    v-if="!canRunEvaluation"
                    class="mt-4 rounded-xl border border-border/60 bg-background/70 p-4 text-sm"
                >
                    <div class="flex items-start gap-3">
                        <Loader2
                            v-if="isResumeProcessing"
                            class="mt-0.5 size-4 animate-spin text-warning"
                        />
                        <AlertTriangle
                            v-else
                            class="mt-0.5 size-4 text-error"
                        />
                        <div class="space-y-2">
                            <p
                                :class="
                                    isResumeProcessing
                                        ? 'font-medium text-warning'
                                        : 'font-medium text-error'
                                "
                            >
                                {{ evaluationDisabledMessage }}
                            </p>
                            <div v-if="evaluationBlockedByLimit" class="pt-2">
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    as-child
                                >
                                    <Link :href="billingRoutes.edit.url()">
                                        Upgrade for {{ planPriceLabel }}
                                    </Link>
                                </Button>
                            </div>
                            <p
                                v-if="
                                    resumeProcessingFailed &&
                                    ingestionErrorMessage
                                "
                                class="text-xs text-muted-foreground"
                            >
                                Error: {{ ingestionErrorMessage }}
                            </p>
                        </div>
                    </div>
                </div>

                <form
                    class="mt-4 flex flex-col gap-5"
                    @submit.prevent="submitEvaluation"
                >
                            <div>
                                <Label class="text-xs font-semibold uppercase">
                                    Job description source
                                </Label>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="
                                            evaluationForm.job_input_type === 'url'
                                                ? 'default'
                                                : 'outline'
                                        "
                                        class="justify-center"
                                        @click="
                                            evaluationForm.job_input_type = 'url'
                                        "
                                    >
                                        Job URL
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="
                                            evaluationForm.job_input_type === 'text'
                                                ? 'default'
                                                : 'outline'
                                        "
                                        class="justify-center"
                                        @click="
                                            evaluationForm.job_input_type = 'text'
                                        "
                                    >
                                        Paste description
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="job_title"
                                        >Role title</Label
                                    >
                                    <Input
                                        id="job_title"
                                        v-model="evaluationForm.job_title"
                                        name="job_title"
                                        type="text"
                                        placeholder="Senior Product Manager"
                                        :aria-invalid="
                                            !!evaluationForm.errors.job_title
                                        "
                                    />
                                    <InputError
                                        :message="evaluationForm.errors.job_title"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="job_company">Company</Label>
                                    <Input
                                        id="job_company"
                                        v-model="evaluationForm.job_company"
                                        name="job_company"
                                        type="text"
                                        placeholder="Acme Robotics"
                                        :aria-invalid="
                                            !!evaluationForm.errors.job_company
                                        "
                                    />
                                    <InputError
                                        :message="
                                            evaluationForm.errors.job_company
                                        "
                                    />
                                </div>
                            </div>

                            <div
                                v-if="evaluationForm.job_input_type === 'url'"
                                class="space-y-2"
                            >
                                <Label for="job_url">Job posting URL</Label>
                                <Input
                                    id="job_url"
                                    v-model="evaluationForm.job_url"
                                    name="job_url"
                                    type="url"
                                    placeholder="https://company.com/careers/role"
                                    :aria-invalid="
                                        !!(evaluationForm.errors.job_url || globalErrors.job_url)
                                    "
                                />
                                <InputError
                                    :message="
                                        evaluationForm.errors.job_url ||
                                        globalErrors.job_url
                                    "
                                />
                            </div>

                            <div
                                v-else
                                class="space-y-2"
                            >
                                <Label for="job_text"
                                    >Job description (markdown
                                    supported)</Label
                                >
                                <textarea
                                    id="job_text"
                                    v-model="evaluationForm.job_text"
                                    name="job_text"
                                    rows="8"
                                    class="min-h-[200px] w-full max-w-full resize-y rounded-lg border border-border/70 bg-background px-3 py-3 text-sm text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    placeholder="## About the role&#10;&#10;Paste the responsibilities and qualifications..."
                                    :aria-invalid="
                                        !!(evaluationForm.errors.job_text || globalErrors.job_text)
                                    "
                                />
                                <InputError
                                    :message="
                                        evaluationForm.errors.job_text ||
                                        globalErrors.job_text
                                    "
                                />
                            </div>

                            <div class="space-y-2">
                                <Label>Model</Label>
                                <div class="grid gap-2">
                                    <button
                                        v-for="model in availableModels"
                                        :key="model.id"
                                        type="button"
                                        :class="[
                                            'flex flex-col gap-1 rounded-lg border px-3 py-2 text-left transition',
                                            evaluationForm.model === model.id
                                                ? 'border-primary bg-primary/10 text-foreground shadow-sm'
                                                : 'border-border/60 bg-background/70 text-muted-foreground hover:border-primary/60 hover:bg-primary/5',
                                        ]"
                                        @click="evaluationForm.model = model.id"
                                    >
                                        <span class="text-sm font-medium">
                                            {{ model.label }}
                                        </span>
                                        <span class="text-xs">
                                            {{ model.helper }}
                                        </span>
                                    </button>
                                </div>
                                <InputError
                                    :message="evaluationForm.errors.model"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="notes"
                                    >Notes for this run (optional)</Label
                                >
                                <textarea
                                    id="notes"
                                    v-model="evaluationForm.notes"
                                    name="notes"
                                    rows="3"
                                    class="w-full max-w-full resize-y rounded-lg border border-border/70 bg-background px-3 py-3 text-sm text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    placeholder="Remind yourself why you ran this evaluation."
                                />
                                <InputError :message="evaluationForm.errors.notes" />
                            </div>

                        <Button
                            type="submit"
                            :disabled="
                                evaluationForm.processing || !canRunEvaluation
                            "
                            :aria-disabled="!canRunEvaluation"
                            class="justify-center"
                        >
                                <CircleCheck class="mr-2 size-4" />
                                Run evaluation
                            </Button>
                        </form>
                    </div>

                    <div
                        id="tailored-resumes"
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-foreground">
                                    Tailored resumes
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Every tailored version generated from this
                                    resume across your evaluations.
                                </p>
                            </div>
                        </header>

                        <div class="mt-4 space-y-4">
                            <template v-if="hasTailoredResumes">
                                <article
                                    v-for="tailored in tailoredResumes"
                                    :key="tailored.id"
                                    class="rounded-xl border border-border/60 bg-background/70 p-4"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p class="text-sm font-semibold text-foreground">
                                                {{
                                                    tailored.title ||
                                                    'Tailored resume'
                                                }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{
                                                    formatDateTime(
                                                        tailored.created_at,
                                                    ) || '—'
                                                }}
                                            </p>
                                        </div>
                                        <Badge
                                            class="border-secondary/40 bg-secondary/20 text-secondary-foreground"
                                        >
                                            {{ tailored.model || 'gpt-5-mini' }}
                                        </Badge>
                                    </div>
                                    <p
                                        v-if="tailored.job_description"
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            tailored.job_description.title ||
                                            tailored.job_description.source_label
                                        }}
                                    </p>
                                    <div class="mt-3 space-y-2">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="justify-start"
                                            @click="toggleTailoredPreview(tailored.id)"
                                        >
                                            <FileText class="mr-2 size-4" />
                                            {{
                                                expandedTailored[tailored.id]
                                                    ? 'Hide markdown'
                                                    : 'View markdown'
                                            }}
                                        </Button>
                                        <div
                                            v-if="expandedTailored[tailored.id]"
                                            class="rounded-lg border border-border/60 bg-background/80 p-3"
                                        >
                                            <MarkdownViewer
                                                :content="tailored.content_markdown"
                                            />
                                        </div>
                                    </div>
                                </article>
                            </template>
                            <div
                                v-else
                                class="rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
                            >
                                Tailored resumes will appear here once you
                                generate them from a completed evaluation.
                            </div>
                        </div>
                    </div>
                </aside>

                <aside class="space-y-6">
                    <div
                        id="resume-preview"
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="mb-4">
                            <h2 class="text-lg font-semibold text-foreground">
                                Resume preview
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Review the current markdown that will be used
                                for every evaluation and tailored variation.
                            </p>
                        </header>
                    <div
                        class="rounded-xl border border-border/60 bg-background/80 p-4"
                    >
                        <div
                            v-if="isResumeProcessing"
                            class="flex items-center gap-3 text-sm text-muted-foreground"
                        >
                            <Loader2 class="size-5 animate-spin text-warning" />
                            <span>
                                Processing your PDF upload. Markdown preview will appear shortly.
                            </span>
                        </div>
                        <div v-else-if="resumeProcessingFailed" class="space-y-2">
                            <p class="text-sm font-semibold text-error">
                                We couldn't process this PDF upload.
                            </p>
                            <p
                                v-if="ingestionErrorMessage"
                                class="text-xs text-muted-foreground"
                            >
                                Error: {{ ingestionErrorMessage }}
                            </p>
                        </div>
                        <MarkdownViewer
                            v-else
                            :content="resume.content_markdown"
                        />
                    </div>
                    </div>

                    <div
                        id="evaluation-history"
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-foreground">
                                    Evaluation history
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Review prior runs and jump to the job record for deeper context.
                                </p>
                            </div>
                            <Badge
                                class="border-border/60 bg-muted/40 text-muted-foreground"
                            >
                                {{ totalEvaluations }} total
                            </Badge>
                        </header>

                        <div class="mt-4 space-y-3">
                            <template v-if="hasEvaluations">
                                <template
                                    v-for="evaluation in evaluations"
                                    :key="evaluation.id"
                                >
                                    <Link
                                        v-if="evaluation.job_description.id"
                                        :href="
                                            jobsRoutes.show({
                                                job: evaluation.job_description.id,
                                            }).url
                                        "
                                        class="group block rounded-xl border border-border/60 bg-background/70 p-4 text-left transition hover:border-primary/60 hover:bg-primary/5"
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-3"
                                        >
                                            <div class="space-y-1">
                                                <p class="text-sm font-semibold text-foreground">
                                                    {{
                                                        evaluation.job_description.title ||
                                                        evaluation.job_description.source_label
                                                    }}
                                                </p>
                                                <p
                                                    v-if="evaluation.headline"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ evaluation.headline }}
                                                </p>
                                                <p
                                                    v-if="evaluation.job_description.company"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ evaluation.job_description.company }}
                                                </p>
                                            </div>
                                            <Badge
                                                :class="
                                                    evaluationStatusClass(
                                                        evaluation.status,
                                                    )
                                                "
                                            >
                                                <Loader2
                                                    v-if="evaluation.status === 'pending'"
                                                    class="mr-1 size-3 animate-spin"
                                                />
                                                {{
                                                    evaluationStatusLabel(
                                                        evaluation.status,
                                                    )
                                                }}
                                            </Badge>
                                        </div>
                                        <div
                                            class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <span>{{ evaluation.model || '—' }}</span>
                                            <span>•</span>
                                            <span>
                                                {{
                                                    formatDateTime(
                                                        evaluation.completed_at,
                                                    ) || 'Pending'
                                                }}
                                            </span>
                                            <span>•</span>
                                            <span>
                                                {{ evaluation.tailored_count }}
                                                tailored
                                            </span>
                                        </div>
                                    </Link>
                                    <div
                                        v-else
                                        class="rounded-xl border border-border/60 bg-background/70 p-4 text-sm text-muted-foreground"
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-3"
                                        >
                                            <div class="space-y-1">
                                                <p class="text-sm font-semibold text-foreground">
                                                    {{
                                                        evaluation.job_description.title ||
                                                        evaluation.job_description.source_label
                                                    }}
                                                </p>
                                                <p
                                                    v-if="evaluation.job_description.company"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ evaluation.job_description.company }}
                                                </p>
                                            </div>
                                            <Badge
                                                :class="
                                                    evaluationStatusClass(
                                                        evaluation.status,
                                                    )
                                                "
                                            >
                                                <Loader2
                                                    v-if="evaluation.status === 'pending'"
                                                    class="mr-1 size-3 animate-spin"
                                                />
                                                {{
                                                    evaluationStatusLabel(
                                                        evaluation.status,
                                                    )
                                                }}
                                            </Badge>
                                        </div>
                                        <p class="mt-3 text-xs text-muted-foreground">
                                            Manual job description — no linked record yet.
                                        </p>
                                    </div>
                                </template>
                            </template>
                            <div
                                v-else
                                class="rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
                            >
                                No evaluations yet. Run your first comparison to populate history.
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
