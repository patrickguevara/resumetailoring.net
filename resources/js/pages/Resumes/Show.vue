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

interface ResumeDetail {
    id: number;
    slug: string;
    title: string;
    description?: string | null;
    content_markdown: string;
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
    completed_at?: string | null;
    created_at?: string | null;
    job_description: JobDescriptionSummary;
    tailored_count: number;
}

interface TailoredResume {
    id: number;
    title?: string | null;
    model?: string | null;
    content_markdown: string;
    evaluation_id?: number | null;
    created_at?: string | null;
    job_description?: JobDescriptionSummary | null;
}

const props = defineProps<{
    resume: ResumeDetail;
    evaluations: Evaluation[];
    tailored_resumes: TailoredResume[];
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
    () => evaluationForm.job_input_type,
    (type) => {
        if (type === 'url') {
            evaluationForm.clearErrors('job_text');

            return;
        }

        evaluationForm.clearErrors('job_url');
    },
);

const hasEvaluations = computed(() => props.evaluations.length > 0);
const hasTailoredResumes = computed(() => props.tailored_resumes.length > 0);

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
    evaluationForm.post(
        resumeRoutes.evaluations.store.url({ resume: props.resume.slug }),
        {
            preserveScroll: true,
            onSuccess: () => evaluationForm.reset(),
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
    <Head :title="`Resume · ${resume.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-8 px-6 py-8">
            <section
                class="rounded-2xl border border-border/60 bg-gradient-to-br from-primary/10 via-background to-background p-6 shadow-sm"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4"
                >
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
                    class="mt-4 flex flex-wrap items-center gap-4 text-xs text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <CalendarClock class="size-3.5" />
                        Added {{ formatDate(resume.created_at) ?? '—' }}
                    </span>
                    <span>•</span>
                    <span>
                        Updated {{ formatDateTime(resume.updated_at) ?? '—' }}
                    </span>
                    <span>•</span>
                    <span>
                        {{ props.evaluations.length }} evaluation{{
                            props.evaluations.length === 1 ? '' : 's'
                        }}
                    </span>
                    <span>•</span>
                    <span>
                        {{ props.tailored_resumes.length }} tailored version{{
                            props.tailored_resumes.length === 1 ? '' : 's'
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
                                Compare this resume against a job by URL or by
                                pasting the description. Choose the model that
                                best fits your review depth.
                            </p>
                        </header>

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
                                :disabled="evaluationForm.processing"
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
                        <header class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-foreground">
                                    Evaluation history
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Review prior runs and switch between them to
                                    inspect feedback.
                                </p>
                            </div>
                            <Badge
                                class="border-border/60 bg-muted/40 text-muted-foreground"
                            >
                                {{ props.evaluations.length }} total
                            </Badge>
                        </header>

                        <div class="mt-4 space-y-3">
                            <template v-if="hasEvaluations">
                                <button
                                    v-for="evaluation in props.evaluations"
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
                                            Model:
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
                                        <span>•</span>
                                        <span>
                                            {{ evaluation.tailored_count }}
                                            tailored
                                        </span>
                                    </div>
                                </button>
                            </template>
                            <div
                                v-else
                                class="rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
                            >
                                No evaluations yet. Run your first comparison to
                                see the timeline populate.
                            </div>
                        </div>
                    </div>

                    <div
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
                            <Badge
                                class="border-border/60 bg-muted/40 text-muted-foreground"
                            >
                                {{ props.tailored_resumes.length }} total
                            </Badge>
                        </header>

                        <div class="mt-4 space-y-4">
                            <template v-if="hasTailoredResumes">
                                <article
                                    v-for="tailored in props.tailored_resumes"
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
                </section>

                <section class="space-y-6">
                    <div
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
                            <MarkdownViewer :content="resume.content_markdown" />
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
                                    Dive into the selected evaluation’s
                                    feedback, notes, and tailored outputs.
                                </p>
                            </div>
                            <Badge
                                v-if="activeEvaluation"
                                :class="
                                    evaluationStatusClass(activeEvaluation.status)
                                "
                            >
                                {{
                                    evaluationStatusLabel(
                                        activeEvaluation.status,
                                    )
                                }}
                            </Badge>
                        </header>

                        <div v-if="activeEvaluation" class="mt-4 space-y-6">
                            <div
                                class="rounded-xl border border-border/60 bg-background/80 p-4"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">
                                            Target role
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            {{
                                                activeEvaluation.job_description
                                                    .title ||
                                                activeEvaluation.job_description
                                                    .source_label
                                            }}
                                        </p>
                                        <p
                                            v-if="activeEvaluation.job_description.company"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                activeEvaluation.job_description.company
                                            }}
                                        </p>
                                    </div>
                                    <Badge
                                        class="border-secondary/40 bg-secondary/20 text-secondary-foreground"
                                    >
                                        {{ activeEvaluation.model || '—' }}
                                    </Badge>
                                </div>
                                <div
                                    class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <template
                                        v-if="
                                            activeEvaluation.job_description.url
                                        "
                                    >
                                        <a
                                            :href="
                                                activeEvaluation
                                                    .job_description.url
                                            "
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline"
                                        >
                                            <FileText class="size-3.5" />
                                            View posting
                                        </a>
                                        <span>•</span>
                                    </template>
                                    <Link
                                        v-if="activeEvaluation.job_description.id"
                                        :href="
                                            jobsRoutes.show({
                                                job: activeEvaluation
                                                    .job_description.id,
                                            }).url
                                        "
                                        class="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline"
                                    >
                                        Inspect job record
                                    </Link>
                                </div>
                            </div>

                            <div
                                v-if="activeEvaluation.notes"
                                class="rounded-xl border border-border/60 bg-background/80 p-4"
                            >
                                <p class="text-xs font-semibold uppercase text-muted-foreground">
                                    Analyst note
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
                                        Feedback summary
                                    </h3>
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            formatDateTime(
                                                activeEvaluation.completed_at,
                                            ) || 'Pending'
                                        }}
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
                                        {{ activeEvaluation.tailored_count }}
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
                                        placeholder="Senior PM · Tailored"
                                    />
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
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
                                    <span
                                        v-if="activeEvaluation.status !== 'completed'"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Wait for completion before tailoring.
                                    </span>
                                </div>
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
                                    Generate a tailored resume to see it appear
                                    here.
                                </p>
                            </div>
                        </div>
                        <div
                            v-else
                            class="rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
                        >
                            Select an evaluation from the history panel to view
                            its details.
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
