<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MarkdownViewer from '@/components/MarkdownViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import evaluationRoutes from '@/routes/evaluations';
import jobsRoutes from '@/routes/jobs';
import resumeRoutes from '@/routes/resumes';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import {
    CalendarClock,
    CircleCheck,
    FileText,
    Sparkles,
} from 'lucide-vue-next';

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
    content_markdown: string;
    evaluation_id?: number | null;
    created_at?: string | null;
    resume?: {
        id?: number | null;
        title?: string | null;
        slug?: string | null;
    } | null;
}

const props = defineProps<{
    job: JobDetail;
    evaluations: Evaluation[];
    tailored_resumes: TailoredResume[];
    resumes: ResumeOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jobs',
        href: jobsRoutes.index.url(),
    },
    {
        title: props.job.title ?? 'Job',
        href: jobsRoutes.show({ job: props.job.id }).url,
    },
];

const initialResumeId =
    props.resumes.length > 0 ? props.resumes[0].id : null;

const evaluationForm = useForm({
    resume_id: initialResumeId as number | null,
    model: 'gpt-5-nano',
    notes: '',
    job_url_override: '',
});

const researchForm = useForm({
    company: props.job.company ?? '',
    model: props.job.company_research.model ?? 'gpt-5-mini',
    focus: '',
});

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

const tailorTitles = reactive<Record<number, string>>({});
const tailorProcessing = reactive<Record<number, boolean>>({});
const expandedTailored = reactive<Record<number, boolean>>({});

const activeEvaluationId = ref<number | null>(
    props.evaluations.length > 0 ? props.evaluations[0].id : null,
);

watch(
    () => props.evaluations.map((evaluation) => evaluation.id),
    (evaluationIds) => {
        evaluationIds.forEach((id) => {
            if (tailorTitles[id] === undefined) {
                tailorTitles[id] = '';
            }

            if (tailorProcessing[id] === undefined) {
                tailorProcessing[id] = false;
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
    () => props.tailored_resumes.map((tailored) => tailored.id),
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

const hasEvaluations = computed(() => props.evaluations.length > 0);
const activeEvaluation = computed(
    () =>
        props.evaluations.find(
            (evaluation) => evaluation.id === activeEvaluationId.value,
        ) ?? null,
);

const activeEvaluationTailored = computed(() =>
    props.tailored_resumes.filter(
        (tailored) => tailored.evaluation_id === activeEvaluationId.value,
    ),
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

const submitEvaluation = () => {
    if (evaluationForm.resume_id === null) {
        return;
    }

    evaluationForm.post(
        jobsRoutes.evaluations.store({ job: props.job.id }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                evaluationForm.reset('notes', 'job_url_override');
            },
        },
    );
};

const submitResearch = () => {
    researchForm.post(
        jobsRoutes.research.store({ job: props.job.id }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                researchForm.reset('focus');
            },
        },
    );
};

const generateTailored = (evaluation: Evaluation | null) => {
    if (!evaluation) {
        return;
    }

    tailorProcessing[evaluation.id] = true;

    router.post(
        evaluationRoutes.tailor.url({ evaluation: evaluation.id }),
        {
            title: tailorTitles[evaluation.id] ?? '',
        },
        {
            preserveScroll: true,
            onFinish: () => {
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
};

const page = usePage<{
    errors: Record<string, string>;
}>();

const globalErrors = computed(() => page.props.errors ?? {});
</script>

<template>
    <Head :title="`Job · ${job.title ?? 'Job'}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-8 px-6 py-8">
            <section
                class="rounded-2xl border border-border/60 bg-gradient-to-br from-accent/20 via-background to-background p-6 shadow-sm"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4"
                >
                    <div class="space-y-2">
                        <p
                            class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-primary"
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
                    class="mt-4 flex flex-wrap items-center gap-4 text-xs text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <CalendarClock class="size-3.5" />
                        Added {{ formatDate(job.created_at) ?? '—' }}
                    </span>
                    <span>•</span>
                    <span>
                        Updated {{ formatDateTime(job.updated_at) ?? '—' }}
                    </span>
                    <span>•</span>
                    <span>
                        {{ evaluations.length }} evaluation{{
                            evaluations.length === 1 ? '' : 's'
                        }}
                    </span>
                </div>
            </section>

            <div class="grid gap-8 xl:grid-cols-[minmax(0,420px),1fr]">
                <section class="space-y-6">
                    <div
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="space-y-1">
                            <h2 class="text-lg font-semibold text-foreground">
                                Run a new evaluation
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Select a resume and model to compare against
                                this job. Optionally refresh the posting via a
                                new URL.
                            </p>
                        </header>

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
                                    class="w-full rounded-lg border border-border/70 bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
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
                                            evaluationForm.errors.job_url_override ||
                                            globalErrors.job_url_override
                                        )
                                    "
                                />
                                <InputError
                                    :message="
                                        evaluationForm.errors.job_url_override ||
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
                                    class="w-full rounded-lg border border-border/70 bg-background px-3 py-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    placeholder="Why are you re-running this evaluation?"
                                />
                                <InputError :message="evaluationForm.errors.notes" />
                            </div>

                            <Button
                                type="submit"
                                :disabled="
                                    evaluationForm.processing ||
                                    evaluationForm.resume_id === null
                                "
                                class="justify-center"
                            >
                                <CircleCheck class="mr-2 size-4" />
                                Run evaluation
                            </Button>
                        </form>
                    </div>

                    <div
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="space-y-1">
                            <h2 class="text-lg font-semibold text-foreground">
                                Company research
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Generate a briefing to prepare for outreach and
                                interviews. Update the company name and focus
                                areas as needed.
                            </p>
                        </header>

                        <div
                            v-if="job.company_research.summary"
                            class="mt-4 space-y-3 rounded-xl border border-border/60 bg-background/80 p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <h3 class="text-sm font-semibold text-foreground">
                                    Latest briefing
                                </h3>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        formatDateTime(
                                            job.company_research.last_ran_at,
                                        ) || '—'
                                    }}
                                </span>
                            </div>
                            <MarkdownViewer
                                :content="job.company_research.summary"
                            />
                        </div>

                        <form
                            class="mt-6 flex flex-col gap-5"
                            @submit.prevent="submitResearch"
                        >
                            <div class="space-y-2">
                                <Label for="company_name">Company name</Label>
                                <Input
                                    id="company_name"
                                    v-model="researchForm.company"
                                    name="company"
                                    type="text"
                                    placeholder="Acme Robotics"
                                    :aria-invalid="
                                        !!(
                                            researchForm.errors.company ||
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
                                <div class="grid gap-2">
                                    <button
                                        v-for="model in availableModels"
                                        :key="model.id"
                                        type="button"
                                        :class="[
                                            'flex flex-col gap-1 rounded-lg border px-3 py-2 text-left transition',
                                            researchForm.model === model.id
                                                ? 'border-primary bg-primary/10 text-foreground shadow-sm'
                                                : 'border-border/60 bg-background/70 text-muted-foreground hover:border-primary/60 hover:bg-primary/5',
                                        ]"
                                        @click="researchForm.model = model.id"
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
                                    class="w-full rounded-lg border border-border/70 bg-background px-3 py-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    placeholder="Upcoming product launch, regional market dynamics, hiring initiatives..."
                                />
                                <InputError :message="researchForm.errors.focus" />
                            </div>

                            <Button
                                type="submit"
                                :disabled="researchForm.processing"
                                class="justify-center"
                            >
                                <Sparkles class="mr-2 size-4" />
                                Run company research
                            </Button>
                        </form>
                    </div>
                </section>

                <section class="space-y-6">
                    <div
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-foreground">
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
                                            <p class="text-sm font-semibold text-foreground">
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

                    <div
                        class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                    >
                        <header class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-foreground">
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
                                    evaluationStatusClass(activeEvaluation.status)
                                "
                            >
                                {{
                                    evaluationStatusLabel(activeEvaluation.status)
                                }}
                            </Badge>
                        </header>

                        <div v-if="activeEvaluation" class="mt-4 space-y-6">
                            <div
                                class="rounded-xl border border-border/60 bg-background/80 p-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <p class="text-sm font-semibold text-foreground">
                                            Resume used
                                        </p>
                                        <p class="text-sm text-muted-foreground">
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
                                <p class="text-xs font-semibold uppercase text-muted-foreground">
                                    Notes
                                </p>
                                <p class="mt-2 text-sm text-foreground">
                                    {{ activeEvaluation.notes }}
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <h3 class="text-sm font-semibold text-foreground">
                                        Feedback
                                    </h3>
                                    <span class="text-xs text-muted-foreground">
                                        {{ activeEvaluation.model || '—' }}
                                    </span>
                                </div>
                                <div
                                    class="rounded-xl border border-border/60 bg-background/80 p-4"
                                >
                                    <MarkdownViewer
                                        :content="
                                            activeEvaluation.feedback_markdown ||
                                            '_Feedback is still processing or unavailable._'
                                        "
                                    />
                                </div>
                            </div>

                            <div
                                class="space-y-4 rounded-xl border border-border/60 bg-background/80 p-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <h3 class="text-sm font-semibold text-foreground">
                                        Generate tailored resume
                                    </h3>
                                    <Badge
                                        class="border-border/60 bg-muted/50 text-muted-foreground"
                                    >
                                        {{
                                            activeEvaluationTailored.length
                                        }}
                                        existing
                                    </Badge>
                                </div>
                                <div class="space-y-2">
                                    <Label
                                        :for="`tailored-title-${activeEvaluation.id}`"
                                    >
                                        Title
                                    </Label>
                                    <Input
                                        :id="`tailored-title-${activeEvaluation.id}`"
                                        v-model="tailorTitles[activeEvaluation.id]"
                                        type="text"
                                        placeholder="Tailored resume title"
                                    />
                                </div>

                                <Button
                                    size="sm"
                                    :disabled="
                                        tailorProcessing[activeEvaluation.id] ||
                                        activeEvaluation.status !== 'completed'
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
                                    <h3 class="text-sm font-semibold text-foreground">
                                        Tailored outputs from this run
                                    </h3>
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            activeEvaluationTailored.length
                                        }}
                                        total
                                    </span>
                                </div>

                                <template v-if="activeEvaluationTailored.length">
                                    <article
                                        v-for="tailored in activeEvaluationTailored"
                                        :key="tailored.id"
                                        class="rounded-xl border border-border/60 bg-background/70 p-4"
                                    >
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <p class="text-sm font-semibold text-foreground">
                                                {{
                                                    tailored.title ||
                                                    'Tailored resume'
                                                }}
                                            </p>
                                            <Badge
                                                class="border-secondary/40 bg-secondary/20 text-secondary-foreground"
                                            >
                                                {{ tailored.model || 'gpt-5-mini' }}
                                            </Badge>
                                        </div>
                                        <p class="mt-2 text-xs text-muted-foreground">
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
                                            @click="toggleTailoredPreview(tailored.id)"
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
                                                :content="tailored.content_markdown"
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
                                :content="job.description_markdown ?? '*No description stored yet.*'"
                            />
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
