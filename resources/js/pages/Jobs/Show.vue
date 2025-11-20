<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MarkdownViewer from '@/components/MarkdownViewer.vue';
import EvaluationFeedbackCard from '@/components/EvaluationFeedbackCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBilling } from '@/composables/useBilling';
import AppLayout from '@/layouts/AppLayout.vue';
import { userChannel } from '@/lib/realtime';
import billingRoutes from '@/routes/billing';
import evaluationRoutes from '@/routes/evaluations';
import jobsRoutes from '@/routes/jobs';
import resumeRoutes from '@/routes/resumes';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CalendarClock,
    ChevronDown,
    CircleCheck,
    CircleDashed,
    FileText,
    Loader2,
    ScrollText,
    Sparkles,
} from 'lucide-vue-next';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';

interface JobDetail {
    id: number;
    title?: string | null;
    company?: string | null;
    source_url?: string | null;
    source_label: string;
    is_manual: boolean;
    description_markdown?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    company_research: {
        summary?: string | null;
        last_ran_at?: string | null;
        model?: string | null;
        focus?: string | null;
    };
}

interface ResumeOption {
    id: number;
    title: string;
    slug: string;
}

interface Evaluation {
    id: number;
    status: string;
    headline?: string | null;
    model?: string | null;
    notes?: string | null;
    feedback_markdown?: string | null;
    feedback_data?: {
        sentiment?: string;
        highlights?: {
            matching_skills?: number;
            relevant_years?: number;
            key_gaps?: number;
        } | null;
        key_phrases?: string[];
        sections?: {
            summary?: string | null;
            relevant_experience?: string | null;
            gaps?: string | null;
            recommendations?: string | null;
        };
    } | null;
    error_message?: string | null;
    resume: {
        id?: number | null;
        title?: string | null;
        slug?: string | null;
    };
    completed_at?: string | null;
    created_at?: string | null;
}

interface TailoredResume {
    id: number;
    title?: string | null;
    model?: string | null;
    content_markdown?: string | null;
    evaluation_id?: number | null;
    created_at?: string | null;
    resume?: {
        id?: number | null;
        title?: string | null;
        slug?: string | null;
    } | null;
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
        resume?: {
            id?: number | null;
            title?: string | null;
            slug?: string | null;
        } | null;
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
        job_description?: {
            id: number;
        } | null;
        resume?: {
            id?: number | null;
            title?: string | null;
            slug?: string | null;
        } | null;
    } | null;
}

interface CompanyResearchUpdatedPayload {
    status: 'completed' | 'failed';
    job_id: number;
    company?: string | null;
    company_research?: {
        summary?: string | null;
        summary_is_truncated?: boolean;
        last_ran_at?: string | null;
        model?: string | null;
        focus?: string | null;
    } | null;
    error_message?: string | null;
}

const props = defineProps<{
    job: JobDetail;
    evaluations: Evaluation[];
    tailored_resumes: TailoredResume[];
    resumes: ResumeOption[];
}>();

const page = usePage<{
    errors: Record<string, string>;
    auth?: { user?: { id: number } | null } | null;
}>();

const defaultCompanyResearch = (
    value: JobDetail['company_research'] | null | undefined,
) => ({
    summary: value?.summary ?? null,
    last_ran_at: value?.last_ran_at ?? null,
    model: value?.model ?? 'gpt-5-mini',
    focus: value?.focus ?? '',
});

const cloneJob = (job: JobDetail): JobDetail => ({
    ...job,
    title: job.title ?? null,
    company: job.company ?? null,
    source_url: job.source_url ?? null,
    description_markdown: job.description_markdown ?? null,
    company_research: defaultCompanyResearch(job.company_research),
});

const job = ref<JobDetail>(cloneJob(props.job));

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jobs',
        href: jobsRoutes.index.url(),
    },
    {
        title: job.value.title ?? 'Job',
        href: jobsRoutes.show({ job: job.value.id }).url,
    },
];

const cloneEvaluation = (evaluation: Evaluation): Evaluation => ({
    ...evaluation,
    resume: {
        id: evaluation.resume?.id ?? null,
        title: evaluation.resume?.title ?? null,
        slug: evaluation.resume?.slug ?? null,
    },
});

const cloneTailored = (tailored: TailoredResume): TailoredResume => ({
    ...tailored,
    content_markdown: tailored.content_markdown ?? null,
    resume: tailored.resume
        ? {
              id: tailored.resume.id ?? null,
              title: tailored.resume.title ?? null,
              slug: tailored.resume.slug ?? null,
          }
        : null,
});

const evaluations = ref<Evaluation[]>(props.evaluations.map(cloneEvaluation));
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

        const normalized: Evaluation = {
            id: data.id,
            status: data.status,
            headline: data.headline ?? null,
            model: data.model ?? null,
            notes: data.notes ?? null,
            feedback_markdown: data.feedback_markdown ?? null,
            error_message: data.error_message ?? null,
            resume: {
                id: data.resume?.id ?? null,
                title: data.resume?.title ?? null,
                slug: data.resume?.slug ?? null,
            },
            completed_at: data.completed_at ?? null,
            created_at: data.created_at ?? null,
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
            resume: data.resume
                ? {
                      id: data.resume.id ?? null,
                      title: data.resume.title ?? null,
                      slug: data.resume.slug ?? null,
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

let jobReloadInFlight = false;
const refreshJobDetails = () => {
    if (jobReloadInFlight) {
        return;
    }

    jobReloadInFlight = true;

    router.reload({
        only: ['job'],
        preserveScroll: true,
        onFinish: () => {
            jobReloadInFlight = false;
        },
        onError: () => {
            jobReloadInFlight = false;
        },
    });
};

const initialResumeId = props.resumes.length > 0 ? props.resumes[0].id : null;

const evaluationForm = useForm({
    resume_id: initialResumeId as number | null,
    model: 'gpt-5-nano',
    notes: '',
    job_url_override: '',
});

const researchForm = useForm({
    company: job.value.company ?? '',
    model: job.value.company_research.model ?? 'gpt-5-mini',
    focus: job.value.company_research.focus ?? '',
});

watch(
    () => props.job,
    (value) => {
        job.value = cloneJob(value);
        companyResearchProcessing.value = false;
        companyResearchError.value = null;
    },
    { deep: true },
);

watch(
    () => job.value.company,
    (value) => {
        researchForm.company = value ?? '';
    },
    { immediate: true },
);

watch(
    () => job.value.company_research.model,
    (value) => {
        researchForm.model = value ?? 'gpt-5-mini';
    },
    { immediate: true },
);

watch(
    () => job.value.company_research.focus,
    (value) => {
        researchForm.focus = value ?? '';
    },
    { immediate: true },
);

const availableModels = [
    {
        id: 'gpt-5-nano',
        label: 'gpt-5-nano',
        helper: 'Quick comparison, lower cost',
    },
    {
        id: 'gpt-5-mini',
        label: 'gpt-5-mini',
        helper: 'Deeper, more detailed analysis',
    },
];

const { hasSubscription, limitReached, remaining, planPrice } = useBilling();
const evaluationLimitReached = limitReached('evaluations');
const evaluationRemaining = remaining('evaluations');
const evaluationBlockedByLimit = computed(
    () => !hasSubscription.value && evaluationLimitReached.value,
);
const companyResearchLimitReached = limitReached('company_research');
const companyResearchRemaining = remaining('company_research');
const researchBlocked = computed(
    () => !hasSubscription.value && companyResearchLimitReached.value,
);
const tailoringLimitReached = limitReached('tailored_resumes');
const tailoringRemaining = remaining('tailored_resumes');
const tailoringBlocked = computed(
    () => !hasSubscription.value && tailoringLimitReached.value,
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
const researchAllowanceCopy = computed(() => {
    if (hasSubscription.value) {
        return 'Unlimited company research is included with your plan.';
    }

    const remainingAllowance = companyResearchRemaining.value ?? 0;

    if (remainingAllowance > 0) {
        return `${remainingAllowance} free research run${
            remainingAllowance === 1 ? '' : 's'
        } left in your preview.`;
    }

    return 'Company research unlocks with Tailor Pro.';
});
const tailoringAllowanceCopy = computed(() => {
    if (hasSubscription.value) {
        return 'Unlimited tailored resumes are included with your plan.';
    }

    const remainingAllowance = tailoringRemaining.value ?? 0;

    if (remainingAllowance > 0) {
        return `${remainingAllowance} free tailored resume${
            remainingAllowance === 1 ? '' : 's'
        } left in your preview.`;
    }

    return 'You have used the free tailored resume included with your preview.';
});

const tailorTitles = reactive<Record<number, string>>({});
const tailorProcessing = reactive<Record<number, boolean>>({});
const tailorErrors = reactive<Record<number, string | null>>({});
const expandedTailored = reactive<Record<number, boolean>>({});
const showCompanyResearch = ref(false); // collapsed by default per redesign
const companyResearchProcessing = ref(false);
const showEvaluationForm = ref(false);
const showEvaluationHistory = ref(false); // collapsed by default
const showJobDescription = ref(false); // collapsed by default
const companyResearchError = ref<string | null>(null);
const isResearchRunning = computed(
    () => companyResearchProcessing.value || researchForm.processing,
);

const activeEvaluationId = ref<number | null>(
    evaluations.value.length > 0 ? evaluations.value[0].id : null,
);

watch(
    () => activeEvaluationId.value,
    (evaluationId) => {
        if (evaluationId === null) {
            return;
        }

        const evaluation = evaluations.value.find(
            (item) => item.id === evaluationId,
        );

        if (evaluation && !evaluation.feedback_markdown) {
            void fetchEvaluationDetails(evaluationId);
        }
    },
    { immediate: true },
);

watch(
    () => props.resumes.map((resume) => resume.id),
    (resumeIds) => {
        if (
            evaluationForm.resume_id === null ||
            !resumeIds.includes(evaluationForm.resume_id)
        ) {
            evaluationForm.resume_id = resumeIds[0] ?? null;
        }
    },
    { immediate: true },
);

watch(
    () => evaluations.value.map((evaluation) => evaluation.id),
    (evaluationIds) => {
        evaluationIds.forEach((id) => {
            if (tailorTitles[id] === undefined) {
                tailorTitles[id] = '';
            }

            if (tailorProcessing[id] === undefined) {
                tailorProcessing[id] = false;
            }

            if (tailorErrors[id] === undefined) {
                tailorErrors[id] = null;
            }
        });

        if (
            activeEvaluationId.value === null ||
            !evaluationIds.includes(activeEvaluationId.value)
        ) {
            activeEvaluationId.value = evaluationIds[0] ?? null;
        }
    },
    { immediate: true },
);

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

const hasEvaluations = computed(() => evaluations.value.length > 0);

const activeEvaluation = computed(
    () =>
        evaluations.value.find(
            (evaluation) => evaluation.id === activeEvaluationId.value,
        ) ?? null,
);

const activeEvaluationTailored = computed(() =>
    tailoredResumes.value.filter(
        (tailored) => tailored.evaluation_id === activeEvaluationId.value,
    ),
);

const totalTailoredResumes = computed(() => tailoredResumes.value.length);

const hasCompanyResearchSummary = computed(() =>
    Boolean(job.value.company_research.summary),
);

const hasJobDescription = computed(() =>
    Boolean(job.value.description_markdown),
);

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
    if (evaluationForm.resume_id === null) {
        return;
    }

    if (evaluationBlockedByLimit.value) {
        return;
    }

    evaluationForm.post(
        jobsRoutes.evaluations.store({ job: job.value.id }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                evaluationForm.reset('notes', 'job_url_override');
            },
        },
    );
};

const submitResearch = () => {
    if (researchBlocked.value) {
        return;
    }

    companyResearchProcessing.value = true;
    companyResearchError.value = null;

    researchForm.post(jobsRoutes.research.store({ job: job.value.id }).url, {
        preserveScroll: true,
        onError: () => {
            companyResearchProcessing.value = false;
        },
        onSuccess: () => {
            researchForm.reset('focus');
        },
    });
};

const generateTailored = (evaluation: Evaluation | null) => {
    if (!evaluation || tailoringBlocked.value) {
        return;
    }

    tailorProcessing[evaluation.id] = true;
    tailorErrors[evaluation.id] = null;

    router.post(
        evaluationRoutes.tailor.url({ evaluation: evaluation.id }),
        {
            title: tailorTitles[evaluation.id] ?? '',
        },
        {
            preserveScroll: true,
            onError: () => {
                tailorProcessing[evaluation.id] = false;
            },
            onSuccess: () => {
                tailorTitles[evaluation.id] = '';
            },
        },
    );
};

const toggleTailoredPreview = (id: number) => {
    expandedTailored[id] = !expandedTailored[id];

    if (expandedTailored[id]) {
        const tailored = tailoredResumes.value.find((item) => item.id === id);

        if (!tailored || !tailored.content_markdown) {
            void fetchTailoredResume(id);
        }
    }
};

const handleResumeEvaluationUpdated = (
    payload: ResumeEvaluationUpdatedPayload,
) => {
    const existing = evaluations.value.find(
        (evaluation) => evaluation.id === payload.evaluation.id,
    );

    const resumeInfo = existing?.resume ??
        payload.evaluation.resume ?? {
            id: null,
            title: null,
            slug: null,
        };

    const normalized: Evaluation = {
        id: payload.evaluation.id,
        status: payload.evaluation.status,
        headline: payload.evaluation.headline ?? null,
        model: payload.evaluation.model ?? null,
        notes: payload.evaluation.notes ?? null,
        feedback_markdown: payload.evaluation.feedback_markdown ?? null,
        error_message: payload.evaluation.error_message ?? null,
        resume: {
            id: resumeInfo.id ?? null,
            title: resumeInfo.title ?? null,
            slug: resumeInfo.slug ?? null,
        },
        completed_at: payload.evaluation.completed_at ?? null,
        created_at: payload.evaluation.created_at ?? null,
    };

    const existingIndex = evaluations.value.findIndex(
        (evaluation) => evaluation.id === normalized.id,
    );

    if (existingIndex >= 0) {
        evaluations.value.splice(existingIndex, 1, normalized);
    } else {
        evaluations.value = [normalized, ...evaluations.value];
    }

    if (tailorTitles[normalized.id] === undefined) {
        tailorTitles[normalized.id] = '';
    }

    if (tailorProcessing[normalized.id] === undefined) {
        tailorProcessing[normalized.id] = false;
    }

    if (tailorErrors[normalized.id] === undefined) {
        tailorErrors[normalized.id] = null;
    }

    if (
        activeEvaluationId.value === null ||
        !evaluations.value.some(
            (evaluation) => evaluation.id === activeEvaluationId.value,
        )
    ) {
        activeEvaluationId.value = evaluations.value[0]?.id ?? null;
    }

    if (payload.evaluation.feedback_is_truncated) {
        void fetchEvaluationDetails(normalized.id);
    }
};

const handleTailoredResumeUpdated = (payload: TailoredResumeUpdatedPayload) => {
    if (tailorProcessing[payload.evaluation_id] === undefined) {
        tailorProcessing[payload.evaluation_id] = false;
    }

    tailorProcessing[payload.evaluation_id] = false;

    if (payload.status === 'failed') {
        tailorErrors[payload.evaluation_id] =
            payload.error_message ?? 'Unable to create tailored resume.';

        return;
    }

    tailorErrors[payload.evaluation_id] = null;

    const data = payload.tailored_resume;

    if (!data) {
        return;
    }

    const isTruncated = data.content_is_truncated ?? false;

    const normalized: TailoredResume = {
        id: data.id,
        title: data.title ?? null,
        model: data.model ?? null,
        content_markdown: data.content_markdown ?? null,
        created_at: data.created_at ?? null,
        evaluation_id: data.evaluation_id ?? payload.evaluation_id,
        resume: data.resume
            ? {
                  id: data.resume.id ?? null,
                  title: data.resume.title ?? null,
                  slug: data.resume.slug ?? null,
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

    expandedTailored[normalized.id] ??= false;
    tailorTitles[payload.evaluation_id] = '';

    if (isTruncated) {
        void fetchTailoredResume(normalized.id);
    }
};

const handleCompanyResearchUpdated = (
    payload: CompanyResearchUpdatedPayload,
) => {
    if (payload.job_id !== job.value.id) {
        return;
    }

    companyResearchProcessing.value = false;

    if (payload.status === 'failed') {
        companyResearchError.value =
            payload.error_message ?? 'Company research failed to complete.';

        return;
    }

    companyResearchError.value = null;

    job.value = {
        ...job.value,
        company: payload.company ?? job.value.company ?? null,
        company_research: defaultCompanyResearch(payload.company_research),
    };

    if (payload.company_research?.summary_is_truncated) {
        refreshJobDetails();
    }
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
    channel.stopListening('.CompanyResearchUpdated');

    channel.listen('.ResumeEvaluationUpdated', handleResumeEvaluationUpdated);
    channel.listen('.TailoredResumeUpdated', handleTailoredResumeUpdated);
    channel.listen('.CompanyResearchUpdated', handleCompanyResearchUpdated);

    realtimeChannel.value = channel;
};

onMounted(() => {
    registerRealtimeHandlers();
});

onBeforeUnmount(() => {
    if (realtimeChannel.value) {
        realtimeChannel.value.stopListening('.ResumeEvaluationUpdated');
        realtimeChannel.value.stopListening('.TailoredResumeUpdated');
        realtimeChannel.value.stopListening('.CompanyResearchUpdated');
    }
});

const globalErrors = computed(() => page.props.errors ?? {});
</script>

<template>
    <Head :title="`Job · ${job.title ?? 'Job'}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mr-auto w-full max-w-6xl space-y-8 px-6 py-8 xl:max-w-7xl xl:pr-16"
        >
            <section
                class="rounded-2xl border border-border/60 bg-gradient-to-br from-accent/20 via-background to-background p-6 shadow-sm lg:sticky lg:top-6 lg:z-30"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-2">
                        <p
                            class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-primary uppercase"
                        >
                            <Sparkles class="size-3.5" />
                            Job overview
                        </p>
                        <h1 class="text-3xl font-semibold text-foreground">
                            {{ job.title || 'Untitled role' }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ job.company || 'Company not specified yet.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="job.source_url"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <a
                                :href="job.source_url"
                                target="_blank"
                                rel="noopener"
                            >
                                View posting
                            </a>
                        </Button>
                        <Badge
                            v-if="job.is_manual"
                            class="border-muted/60 bg-muted/30 text-muted-foreground"
                        >
                            Manual entry
                        </Badge>
                    </div>
                </div>
                <div
                    class="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <CalendarClock class="size-3.5" />
                        Added {{ formatDate(job.created_at) ?? '—' }}
                    </span>
                    <span class="hidden text-muted-foreground sm:inline"
                        >•</span
                    >
                    <span class="flex items-center gap-1 text-muted-foreground">
                        Updated {{ formatDateTime(job.updated_at) ?? '—' }}
                    </span>
                    <span class="hidden text-muted-foreground sm:inline"
                        >•</span
                    >
                    <span class="flex items-center gap-1 text-muted-foreground">
                        {{ evaluations.length }} evaluation{{
                            evaluations.length === 1 ? '' : 's'
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
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Evaluations
                                    </p>
                                    <p
                                        class="text-base font-semibold text-foreground"
                                    >
                                        {{ evaluations.length }}
                                        <span
                                            class="ml-1 text-sm font-normal text-muted-foreground"
                                        >
                                            total
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
                            @click="scrollToSection('evaluation-details')"
                        >
                            <div class="flex items-center gap-3">
                                <FileText class="size-5 text-primary" />
                                <div>
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Tailored resumes
                                    </p>
                                    <p
                                        class="text-base font-semibold text-foreground"
                                    >
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
                                hasCompanyResearchSummary
                                    ? 'border-success/50 bg-success/10 hover:border-success/60 hover:bg-success/15'
                                    : 'border-border/60 bg-background/60 hover:border-primary/60 hover:bg-primary/5',
                            ]"
                            @click="scrollToSection('company-research')"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="
                                        hasCompanyResearchSummary
                                            ? CircleCheck
                                            : CircleDashed
                                    "
                                    class="size-5"
                                    :class="
                                        hasCompanyResearchSummary
                                            ? 'text-success'
                                            : 'text-muted-foreground'
                                    "
                                />
                                <div>
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Company research
                                    </p>
                                    <p
                                        class="text-base font-semibold"
                                        :class="
                                            hasCompanyResearchSummary
                                                ? 'text-success'
                                                : 'text-foreground'
                                        "
                                    >
                                        {{
                                            hasCompanyResearchSummary
                                                ? 'Completed'
                                                : 'Not started'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <ArrowUpRight
                                :class="[
                                    'size-4 transition',
                                    hasCompanyResearchSummary
                                        ? 'text-success'
                                        : 'text-muted-foreground group-hover:text-foreground',
                                ]"
                            />
                        </button>
                        <button
                            type="button"
                            class="group flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-background/60 px-4 py-3 text-left transition hover:border-primary/60 hover:bg-primary/5"
                            @click="scrollToSection('job-description')"
                        >
                            <div class="flex items-center gap-3">
                                <ScrollText class="size-5 text-primary" />
                                <div>
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Job description
                                    </p>
                                    <p
                                        class="text-base font-semibold text-foreground"
                                    >
                                        {{
                                            hasJobDescription
                                                ? 'Ready to review'
                                                : 'No content yet'
                                        }}
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

            <div class="mx-auto w-full max-w-4xl space-y-6">
                <div
                    id="run-evaluation"
                    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                >
                    <header class="flex items-start justify-between gap-4">
                            <div class="space-y-1 flex-1">
                                <h2 class="text-lg font-semibold text-foreground">
                                    Run a new evaluation
                                </h2>
                                <p
                                    v-if="!showEvaluationForm"
                                    class="text-sm text-muted-foreground"
                                >
                                    Select a resume and model to compare against
                                    this job.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="showEvaluationForm = !showEvaluationForm"
                            >
                                {{ showEvaluationForm ? 'Cancel' : 'New Evaluation' }}
                            </Button>
                        </header>

                        <div v-if="showEvaluationForm">
                            <div
                                v-if="evaluationBlockedByLimit"
                                class="mt-4 rounded-xl border border-primary/40 bg-primary/10 p-4 text-sm text-primary"
                            >
                            <p class="font-semibold text-foreground">
                                Free evaluation used
                            </p>
                            <p class="mt-1 text-xs text-primary/80">
                                Upgrade for {{ planPriceLabel }} to unlock
                                unlimited evaluations.
                            </p>
                            <Button
                                size="sm"
                                class="mt-3"
                                variant="secondary"
                                as-child
                            >
                                <Link :href="billingRoutes.edit.url()">
                                    Upgrade account
                                </Link>
                            </Button>
                        </div>
                        <div
                            v-else
                            class="mt-4 rounded-lg border border-border/50 bg-muted/40 p-3 text-xs text-muted-foreground"
                        >
                            {{ evaluationAllowanceCopy }}
                        </div>

                        <form
                            class="mt-4 flex flex-col gap-5"
                            @submit.prevent="submitEvaluation"
                        >
                            <div class="space-y-2">
                                <Label for="resume_id">Select resume</Label>
                                <select
                                    id="resume_id"
                                    v-model.number="evaluationForm.resume_id"
                                    name="resume_id"
                                    class="w-full rounded-lg border border-border/70 bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none"
                                    :aria-invalid="
                                        !!evaluationForm.errors.resume_id
                                    "
                                >
                                    <option
                                        v-for="resumeOption in props.resumes"
                                        :key="resumeOption.id"
                                        :value="resumeOption.id"
                                    >
                                        {{ resumeOption.title }}
                                    </option>
                                </select>
                                <InputError
                                    :message="evaluationForm.errors.resume_id"
                                />
                                <p
                                    v-if="props.resumes.length === 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    Add a resume first to evaluate this job.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label>Model</Label>
                                <div class="grid gap-2 md:grid-cols-2">
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
                                <Label for="job_url_override"
                                    >Refresh job from URL (optional)</Label
                                >
                                <Input
                                    id="job_url_override"
                                    v-model="evaluationForm.job_url_override"
                                    name="job_url_override"
                                    type="url"
                                    placeholder="https://company.com/careers/updated-role"
                                    :aria-invalid="
                                        !!(
                                            evaluationForm.errors
                                                .job_url_override ||
                                            globalErrors.job_url_override
                                        )
                                    "
                                />
                                <InputError
                                    :message="
                                        evaluationForm.errors
                                            .job_url_override ||
                                        globalErrors.job_url_override
                                    "
                                />
                                <p class="text-xs text-muted-foreground">
                                    Provide a new URL if the posting has been
                                    updated or moved.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="evaluation_notes"
                                    >Notes (optional)</Label
                                >
                                <textarea
                                    id="evaluation_notes"
                                    v-model="evaluationForm.notes"
                                    name="notes"
                                    rows="3"
                                    class="w-full max-w-full resize-y rounded-lg border border-border/70 bg-background px-3 py-3 text-sm text-foreground focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none"
                                    placeholder="Why are you re-running this evaluation?"
                                />
                                <InputError
                                    :message="evaluationForm.errors.notes"
                                />
                            </div>

                            <Button
                                type="submit"
                                :disabled="
                                    evaluationForm.processing ||
                                    evaluationForm.resume_id === null ||
                                    evaluationBlockedByLimit
                                "
                                class="justify-center"
                            >
                                <CircleCheck class="mr-2 size-4" />
                                Run evaluation
                            </Button>
                        </form>
                        </div>
                    </div>

                    <div
                        id="evaluation-details"
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <h2
                                    class="text-lg font-semibold text-foreground"
                                >
                                    Evaluation detail
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Inspect the selected evaluation’s feedback
                                    and tailored outputs.
                                </p>
                            </div>
                            <Badge
                                v-if="activeEvaluation"
                                :class="
                                    evaluationStatusClass(
                                        activeEvaluation.status,
                                    )
                                "
                            >
                                <Loader2
                                    v-if="activeEvaluation.status === 'pending'"
                                    class="mr-1 size-3 animate-spin"
                                />
                                {{
                                    evaluationStatusLabel(
                                        activeEvaluation.status,
                                    )
                                }}
                            </Badge>
                        </header>

                        <div v-if="activeEvaluation" class="mt-4 space-y-6">
                            <div
                                v-if="
                                    activeEvaluation.status === 'failed' &&
                                    activeEvaluation.error_message
                                "
                                class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error"
                            >
                                {{ activeEvaluation.error_message }}
                            </div>
                            <div
                                v-else-if="
                                    activeEvaluation.status === 'pending'
                                "
                                class="flex items-center gap-2 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning"
                            >
                                <Loader2 class="size-4 animate-spin" />
                                <span>
                                    Evaluation is running. This section will
                                    update automatically once it completes.
                                </span>
                            </div>
                            <div
                                class="rounded-xl border border-border/60 bg-background/80 p-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <p
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            Resume used
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                activeEvaluation.resume.title ||
                                                'Resume'
                                            }}
                                        </p>
                                    </div>
                                    <Button
                                        v-if="activeEvaluation.resume.slug"
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link
                                            :href="
                                                resumeRoutes.show({
                                                    slug: activeEvaluation
                                                        .resume.slug as string,
                                                }).url
                                            "
                                        >
                                            Open resume
                                        </Link>
                                    </Button>
                                </div>
                                <div
                                    class="mt-3 inline-flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span>
                                        {{
                                            formatDateTime(
                                                activeEvaluation.completed_at,
                                            ) || 'Pending'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="activeEvaluation.notes"
                                class="rounded-xl border border-border/60 bg-background/80 p-4"
                            >
                                <p
                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Notes
                                </p>
                                <p class="mt-2 text-sm text-foreground">
                                    {{ activeEvaluation.notes }}
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-foreground">
                                        Feedback
                                    </h3>
                                    <span class="text-xs text-muted-foreground">
                                        {{ activeEvaluation.model || '—' }}
                                    </span>
                                </div>
                                <EvaluationFeedbackCard
                                    :feedback-data="activeEvaluation.feedback_data"
                                    :fallback-markdown="activeEvaluation.feedback_markdown"
                                />
                            </div>

                            <div
                                class="space-y-4 rounded-xl border border-border/60 bg-background/80 p-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <h3
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Generate tailored resume
                                    </h3>
                                    <Badge
                                        class="border-border/60 bg-muted/50 text-muted-foreground"
                                    >
                                        {{ activeEvaluationTailored.length }}
                                        existing
                                    </Badge>
                                </div>
                                <div
                                    v-if="tailoringBlocked"
                                    class="rounded-lg border border-primary/40 bg-primary/10 p-3 text-xs text-primary"
                                >
                                    <p class="font-semibold text-foreground">
                                        Free tailored resume used
                                    </p>
                                    <p class="mt-1 text-primary/80">
                                        Upgrade for {{ planPriceLabel }} to keep
                                        generating tailored resumes.
                                    </p>
                                    <Button
                                        size="sm"
                                        class="mt-3"
                                        variant="secondary"
                                        as-child
                                    >
                                        <Link :href="billingRoutes.edit.url()">
                                            Upgrade account
                                        </Link>
                                    </Button>
                                </div>
                                <div
                                    v-else
                                    class="rounded-lg border border-border/50 bg-muted/40 p-3 text-xs text-muted-foreground"
                                >
                                    {{ tailoringAllowanceCopy }}
                                </div>
                                <div class="space-y-2">
                                    <Label
                                        :for="`tailored-title-${activeEvaluation.id}`"
                                    >
                                        Title
                                    </Label>
                                    <Input
                                        :id="`tailored-title-${activeEvaluation.id}`"
                                        v-model="
                                            tailorTitles[activeEvaluation.id]
                                        "
                                        type="text"
                                        placeholder="Tailored resume title"
                                    />
                                </div>

                                <Button
                                    size="sm"
                                    :disabled="
                                        tailorProcessing[activeEvaluation.id] ||
                                        activeEvaluation.status !==
                                            'completed' ||
                                        tailoringBlocked
                                    "
                                    @click="generateTailored(activeEvaluation)"
                                >
                                    <Sparkles class="mr-2 size-4" />
                                    {{
                                        activeEvaluation.status === 'completed'
                                            ? 'Generate tailored version'
                                            : 'Available after completion'
                                    }}
                                </Button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <h3
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Tailored outputs from this run
                                    </h3>
                                    <span class="text-xs text-muted-foreground">
                                        {{ activeEvaluationTailored.length }}
                                        total
                                    </span>
                                </div>

                                <template
                                    v-if="activeEvaluationTailored.length"
                                >
                                    <article
                                        v-for="tailored in activeEvaluationTailored"
                                        :key="tailored.id"
                                        class="rounded-xl border border-border/60 bg-background/70 p-4"
                                    >
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <p
                                                class="text-sm font-semibold text-foreground"
                                            >
                                                {{
                                                    tailored.title ||
                                                    'Tailored resume'
                                                }}
                                            </p>
                                            <Badge
                                                class="border-secondary/40 bg-secondary/20 text-secondary-foreground"
                                            >
                                                {{
                                                    tailored.model ||
                                                    'gpt-5-mini'
                                                }}
                                            </Badge>
                                        </div>
                                        <p
                                            class="mt-2 text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDateTime(
                                                    tailored.created_at,
                                                ) || '—'
                                            }}
                                        </p>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="mt-3 justify-start"
                                            @click="
                                                toggleTailoredPreview(
                                                    tailored.id,
                                                )
                                            "
                                        >
                                            <FileText class="mr-2 size-4" />
                                            {{
                                                expandedTailored[tailored.id]
                                                    ? 'Hide preview'
                                                    : 'View preview'
                                            }}
                                        </Button>
                                        <div
                                            v-if="expandedTailored[tailored.id]"
                                            class="mt-3 rounded-lg border border-border/60 bg-background/80 p-3"
                                        >
                                            <MarkdownViewer
                                                :content="
                                                    tailored.content_markdown ??
                                                    ''
                                                "
                                            />
                                        </div>
                                    </article>
                                </template>
                                <p
                                    v-else
                                    class="rounded-xl border border-dashed border-border/60 bg-background/70 p-4 text-sm text-muted-foreground"
                                >
                                    Generate a tailored resume from this
                                    evaluation to see it here.
                                </p>
                            </div>
                        </div>
                        <div
                            v-else
                            class="rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
                        >
                            Select an evaluation from the history panel to view
                            details.
                        </div>
                    </div>

                    <div
                        id="job-description"
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="mb-4">
                            <h2 class="text-lg font-semibold text-foreground">
                                Job description
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Reference the source description that current
                                evaluations use.
                            </p>
                        </header>
                        <div
                            class="rounded-xl border border-border/60 bg-background/80 p-4"
                        >
                            <MarkdownViewer
                                :content="
                                    job.description_markdown ??
                                    '*No description stored yet.*'
                                "
                            />
                        </div>
                    </div>

                    <div
                        id="company-research"
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="flex items-center justify-between gap-3">
                            <div>
                                <h2
                                    class="text-lg font-semibold text-foreground"
                                >
                                    Company research
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Generate a briefing to prepare for outreach
                                    and interviews. Update the company name and
                                    focus areas as needed.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="gap-1 text-sm font-medium text-muted-foreground hover:text-foreground"
                                :aria-expanded="showCompanyResearch"
                                @click="
                                    showCompanyResearch = !showCompanyResearch
                                "
                            >
                                <span>
                                    {{ showCompanyResearch ? 'Hide' : 'Show' }}
                                </span>
                                <ChevronDown
                                    class="size-4 transition-transform duration-200"
                                    :class="
                                        showCompanyResearch ? 'rotate-180' : ''
                                    "
                                />
                            </Button>
                        </header>

                        <div v-if="showCompanyResearch" class="mt-4 space-y-6">
                            <div
                                v-if="job.company_research.summary"
                                class="space-y-3 rounded-xl border border-border/60 bg-background/80 p-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <h3
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Latest briefing
                                    </h3>
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            formatDateTime(
                                                job.company_research
                                                    .last_ran_at,
                                            ) || '—'
                                        }}
                                    </span>
                                </div>
                                <MarkdownViewer
                                    :content="job.company_research.summary"
                                />
                            </div>

                            <form
                                class="flex flex-col gap-5"
                                @submit.prevent="submitResearch"
                            >
                                <div
                                    v-if="researchBlocked"
                                    class="rounded-xl border border-primary/40 bg-primary/10 p-4 text-sm text-primary"
                                >
                                    <p class="font-semibold text-foreground">
                                        Company research is a paid feature
                                    </p>
                                    <p class="mt-1 text-xs text-primary/80">
                                        Upgrade for {{ planPriceLabel }} to run
                                        unlimited research briefings.
                                    </p>
                                    <Button
                                        size="sm"
                                        class="mt-3"
                                        variant="secondary"
                                        as-child
                                    >
                                        <Link :href="billingRoutes.edit.url()">
                                            Upgrade account
                                        </Link>
                                    </Button>
                                </div>
                                <div
                                    v-else
                                    class="rounded-xl border border-border/50 bg-muted/40 p-3 text-xs text-muted-foreground"
                                >
                                    {{ researchAllowanceCopy }}
                                </div>

                                <fieldset
                                    :disabled="researchBlocked"
                                    class="flex flex-col gap-5"
                                >
                                    <div class="space-y-2">
                                        <Label for="company_name"
                                            >Company name</Label
                                        >
                                        <Input
                                            id="company_name"
                                            v-model="researchForm.company"
                                            name="company"
                                            type="text"
                                            placeholder="Acme Robotics"
                                            :aria-invalid="
                                                !!(
                                                    researchForm.errors
                                                        .company ||
                                                    globalErrors.company
                                                )
                                            "
                                        />
                                        <InputError
                                            :message="
                                                researchForm.errors.company ||
                                                globalErrors.company
                                            "
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label>Model</Label>
                                        <div class="grid gap-2 md:grid-cols-2">
                                            <button
                                                v-for="model in availableModels"
                                                :key="model.id"
                                                type="button"
                                                :class="[
                                                    'flex flex-col gap-1 rounded-lg border px-3 py-2 text-left transition',
                                                    researchForm.model ===
                                                    model.id
                                                        ? 'border-primary bg-primary/10 text-foreground shadow-sm'
                                                        : 'border-border/60 bg-background/70 text-muted-foreground hover:border-primary/60 hover:bg-primary/5',
                                                ]"
                                                @click="
                                                    researchForm.model =
                                                        model.id
                                                "
                                            >
                                                <span
                                                    class="text-sm font-medium"
                                                >
                                                    {{ model.label }}
                                                </span>
                                                <span class="text-xs">
                                                    {{ model.helper }}
                                                </span>
                                            </button>
                                        </div>
                                        <InputError
                                            :message="researchForm.errors.model"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="research_focus"
                                            >Focus areas (optional)</Label
                                        >
                                        <textarea
                                            id="research_focus"
                                            v-model="researchForm.focus"
                                            name="focus"
                                            rows="3"
                                            class="w-full max-w-full resize-y rounded-lg border border-border/70 bg-background px-3 py-3 text-sm text-foreground focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none"
                                            placeholder="Upcoming product launch, regional market dynamics, hiring initiatives..."
                                        />
                                        <InputError
                                            :message="researchForm.errors.focus"
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        :disabled="
                                            isResearchRunning || researchBlocked
                                        "
                                        class="justify-center"
                                    >
                                        <Loader2
                                            v-if="isResearchRunning"
                                            class="mr-2 size-4 animate-spin"
                                        />
                                        <Sparkles v-else class="mr-2 size-4" />
                                        <span v-if="isResearchRunning">
                                            Running company research…
                                        </span>
                                        <span v-else>
                                            Run company research
                                        </span>
                                    </Button>
                                    <p
                                        v-if="companyResearchError"
                                        class="text-xs text-error"
                                    >
                                        {{ companyResearchError }}
                                    </p>
                                    <p
                                        v-else-if="isResearchRunning"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Sit tight—we'll update the briefing as
                                        soon as it finishes.
                                    </p>
                                </fieldset>
                            </form>
                        </div>
                    </div>

                    <div
                        id="evaluation-history"
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="flex items-center justify-between gap-3">
                            <div>
                                <h2
                                    class="text-lg font-semibold text-foreground"
                                >
                                    Evaluation history
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Compare how each resume performed against
                                    this job over time.
                                </p>
                            </div>
                            <Badge
                                class="border-border/60 bg-muted/40 text-muted-foreground"
                            >
                                {{ evaluations.length }} total
                            </Badge>
                        </header>

                        <div class="mt-4 space-y-3">
                            <template v-if="hasEvaluations">
                                <button
                                    v-for="evaluation in evaluations"
                                    :key="evaluation.id"
                                    type="button"
                                    :class="[
                                        'w-full rounded-xl border p-4 text-left transition',
                                        activeEvaluationId === evaluation.id
                                            ? 'border-primary bg-primary/10 shadow-sm'
                                            : 'border-border/60 bg-background/70 hover:border-primary/60 hover:bg-primary/5',
                                    ]"
                                    @click="activeEvaluationId = evaluation.id"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div class="space-y-1">
                                            <p
                                                class="text-sm font-semibold text-foreground"
                                            >
                                                {{
                                                    evaluation.resume.title ||
                                                    'Resume'
                                                }}
                                            </p>
                                            <p
                                                v-if="evaluation.headline"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ evaluation.headline }}
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
                                                v-if="
                                                    evaluation.status ===
                                                    'pending'
                                                "
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
                                        <span>
                                            {{ evaluation.model || '—' }}
                                        </span>
                                        <span>•</span>
                                        <span>
                                            {{
                                                formatDateTime(
                                                    evaluation.completed_at,
                                                ) || 'Pending'
                                            }}
                                        </span>
                                    </div>
                                </button>
                            </template>
                            <div
                                v-else
                                class="rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
                            >
                                Evaluations will appear here once you compare a
                                resume with this job.
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </AppLayout>
</template>
