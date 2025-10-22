<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MarkdownViewer from '@/components/MarkdownViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import evaluationRoutes from '@/routes/evaluations';
import resumeRoutes from '@/routes/resumes';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

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
    notes: '',
});

const tailorTitles = reactive<Record<number, string>>({});
const tailorProcessing = reactive<Record<number, boolean>>({});

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

const statusVariant = (status: string) => {
    switch (status) {
        case 'completed':
            return 'default';
        case 'failed':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'completed':
            return 'Completed';
        case 'failed':
            return 'Failed';
        default:
            return 'Pending';
    }
};

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return null;
    }

    return dateFormatter.format(new Date(value));
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

const generateTailored = (evaluation: Evaluation) => {
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

const page = usePage<{
    errors: Record<string, string>;
}>();

const globalErrors = computed(() => page.props.errors ?? {});
</script>

<template>
    <Head :title="`Resume · ${resume.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 px-4 py-6">
            <section class="grid gap-6 lg:grid-cols-[1.2fr,1fr]">
                <div
                    class="flex flex-col gap-6 rounded-lg border border-border/60 bg-background/60 p-6 shadow-sm dark:border-border/40"
                >
                    <header class="space-y-2">
                        <h1 class="text-2xl font-semibold text-foreground">
                            {{ resume.title }}
                        </h1>
                        <p v-if="resume.description" class="text-sm text-muted-foreground">
                            {{ resume.description }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Last updated {{ formatDateTime(resume.updated_at) }}
                        </p>
                    </header>

                    <div class="space-y-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            Evaluate against a job description
                        </h2>
                        <form class="space-y-4" @submit.prevent="submitEvaluation">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-foreground">
                                    Job description source
                                </Label>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-md border border-border/60 bg-background/70 px-3 py-2 text-left shadow-sm transition hover:border-primary/70 focus-within:border-primary focus-within:outline-none dark:border-border/40"
                                    >
                                        <input
                                            v-model="evaluationForm.job_input_type"
                                            type="radio"
                                            name="job_input_type"
                                            value="url"
                                            class="mt-1 h-4 w-4 border-border/70 text-primary focus:ring-primary"
                                        />
                                        <span class="flex flex-col gap-1">
                                            <span class="text-sm font-medium text-foreground">
                                                Link to job posting
                                            </span>
                                            <span class="text-xs text-muted-foreground">
                                                Paste a public URL and we&rsquo;ll fetch the job description automatically.
                                            </span>
                                        </span>
                                    </label>
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-md border border-border/60 bg-background/70 px-3 py-2 text-left shadow-sm transition hover:border-primary/70 focus-within:border-primary focus-within:outline-none dark:border-border/40"
                                    >
                                        <input
                                            v-model="evaluationForm.job_input_type"
                                            type="radio"
                                            name="job_input_type"
                                            value="text"
                                            class="mt-1 h-4 w-4 border-border/70 text-primary focus:ring-primary"
                                        />
                                        <span class="flex flex-col gap-1">
                                            <span class="text-sm font-medium text-foreground">
                                                Paste description text
                                            </span>
                                            <span class="text-xs text-muted-foreground">
                                                Provide the job details directly if a link is unavailable.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div
                                v-if="evaluationForm.job_input_type === 'url'"
                                class="space-y-2"
                            >
                                <Label for="job_url">Job description URL</Label>
                                <Input
                                    id="job_url"
                                    v-model="evaluationForm.job_url"
                                    type="url"
                                    name="job_url"
                                    placeholder="https://company.com/careers/product-manager"
                                    :aria-invalid="!!evaluationForm.errors.job_url"
                                />
                                <InputError :message="evaluationForm.errors.job_url" />
                            </div>

                            <div
                                v-else
                                class="space-y-2"
                            >
                                <Label for="job_text">Job description text</Label>
                                <textarea
                                    id="job_text"
                                    v-model="evaluationForm.job_text"
                                    name="job_text"
                                    rows="6"
                                    class="w-full rounded-md border border-border/70 bg-background px-3 py-2 text-sm leading-relaxed text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 dark:border-border/40 dark:bg-background/40"
                                    placeholder="Paste the full job description here."
                                    :aria-invalid="!!evaluationForm.errors.job_text"
                                />
                                <InputError :message="evaluationForm.errors.job_text" />
                            </div>

                            <div class="space-y-2">
                                <Label for="job_title">Job title (optional)</Label>
                                <Input
                                    id="job_title"
                                    v-model="evaluationForm.job_title"
                                    type="text"
                                    name="job_title"
                                    placeholder="Senior Product Manager"
                                    :aria-invalid="!!evaluationForm.errors.job_title"
                                />
                                <InputError :message="evaluationForm.errors.job_title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Notes (optional)</Label>
                                <textarea
                                    id="notes"
                                    v-model="evaluationForm.notes"
                                    name="notes"
                                    rows="3"
                                    class="w-full rounded-md border border-border/70 bg-background px-3 py-2 text-sm leading-relaxed text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 dark:border-border/40 dark:bg-background/40"
                                    placeholder="Provide any context you'd like the evaluator to consider."
                                    :aria-invalid="!!evaluationForm.errors.notes"
                                />
                                <InputError :message="evaluationForm.errors.notes" />
                            </div>

                            <Button type="submit" :disabled="evaluationForm.processing">
                                Evaluate resume
                            </Button>
                        </form>
                    </div>
                </div>

                <article
                    class="h-full rounded-lg border border-border/60 bg-background/60 p-6 shadow-sm dark:border-border/40"
                >
                    <header class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-foreground">
                            Base resume (Markdown)
                        </h2>
                        <Badge variant="outline">Draft</Badge>
                    </header>
                    <MarkdownViewer
                        :content="resume.content_markdown"
                        content-class="max-h-[520px] w-full"
                    />
                </article>
            </section>

            <section class="flex flex-col gap-4">
                <header class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-foreground">
                            Evaluations
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Each evaluation is linked to the job description it references.
                        </p>
                    </div>
                    <span class="text-sm text-muted-foreground">
                        Total evaluations: {{ evaluations.length }}
                    </span>
                </header>

                <div v-if="evaluations.length" class="space-y-6">
                    <article
                        v-for="evaluation in evaluations"
                        :key="evaluation.id"
                        class="rounded-lg border border-border/60 bg-background/60 p-6 shadow-sm dark:border-border/40"
                    >
                        <header class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-col gap-1">
                                <h3 class="text-lg font-semibold text-foreground">
                                    {{
                                        evaluation.job_description.title ||
                                        evaluation.job_description.source_label
                                    }}
                                </h3>
                                <div class="text-sm text-muted-foreground">
                                    <Link
                                        v-if="evaluation.job_description.url"
                                        :href="evaluation.job_description.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ evaluation.job_description.url }}
                                    </Link>
                                    <span v-else>
                                        Manual job description provided directly by you.
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2 text-right">
                                <Badge :variant="statusVariant(evaluation.status)">
                                    {{ statusLabel(evaluation.status) }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatDateTime(evaluation.completed_at || evaluation.created_at) }}
                                </span>
                            </div>
                        </header>

                        <p
                            v-if="evaluation.headline"
                            class="mt-4 text-sm font-medium text-foreground"
                        >
                            {{ evaluation.headline }}
                        </p>

                        <p v-if="evaluation.notes" class="mt-2 text-sm text-muted-foreground">
                            {{ evaluation.notes }}
                        </p>

                        <div class="mt-4 space-y-3">
                            <h4 class="text-sm font-semibold text-muted-foreground">
                                Evaluation feedback
                            </h4>
                            <MarkdownViewer
                                :content="evaluation.feedback_markdown ?? ''"
                                content-class="max-h-[360px]"
                                empty-label="Feedback pending…"
                            />
                        </div>

                        <div
                            v-if="evaluation.status === 'completed'"
                            class="mt-6 space-y-3 rounded-md border border-border/60 bg-background/60 p-4 dark:border-border/40"
                        >
                            <h4 class="text-sm font-semibold text-muted-foreground">
                                Generate tailored resume
                            </h4>
                            <div class="grid gap-2 md:grid-cols-[1fr_auto] md:items-center">
                                <div class="space-y-1">
                                    <Label :for="`tailor-title-${evaluation.id}`">
                                        Optional title
                                    </Label>
                                    <Input
                                        :id="`tailor-title-${evaluation.id}`"
                                        v-model="tailorTitles[evaluation.id]"
                                        type="text"
                                        placeholder="Tailored resume for {{ evaluation.job_description.title || 'target role' }}"
                                    />
                                </div>

                                <Button
                                    type="button"
                                    :disabled="tailorProcessing[evaluation.id]"
                                    @click="generateTailored(evaluation)"
                                >
                                    <span v-if="tailorProcessing[evaluation.id]">
                                        Generating…
                                    </span>
                                    <span v-else>Generate tailored resume</span>
                                </Button>
                            </div>
                            <p
                                v-if="globalErrors.tailor"
                                class="text-sm text-destructive"
                            >
                                {{ globalErrors.tailor }}
                            </p>
                            <p
                                v-else-if="globalErrors.evaluation"
                                class="text-sm text-destructive"
                            >
                                {{ globalErrors.evaluation }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Tailored resumes created: {{ evaluation.tailored_count }}
                            </p>
                        </div>

                        <p
                            v-else-if="evaluation.status === 'failed'"
                            class="mt-4 text-sm text-destructive"
                        >
                            We were unable to generate feedback for this job description.
                            Try again or provide a different link.
                        </p>
                    </article>
                </div>
                <div
                    v-else
                    class="rounded-lg border border-dashed border-border/60 p-6 text-sm text-muted-foreground"
                >
                    No evaluations yet. Start by running your resume against a job
                    description using the form above.
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <header class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-foreground">
                            Tailored resumes
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Tailored outputs stay linked to the originating job evaluation.
                        </p>
                    </div>
                    <span class="text-sm text-muted-foreground">
                        Total tailored resumes: {{ tailored_resumes.length }}
                    </span>
                </header>

                <div v-if="tailored_resumes.length" class="space-y-6">
                    <article
                        v-for="tailored in tailored_resumes"
                        :key="tailored.id"
                        class="rounded-lg border border-border/60 bg-background/60 p-6 shadow-sm dark:border-border/40"
                    >
                        <header class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-foreground">
                                    {{ tailored.title || 'Tailored resume' }}
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    Linked evaluation: #
                                    {{ tailored.evaluation_id ?? '—' }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-sm text-muted-foreground">
                                <span>{{ tailored.model || 'OpenAI' }}</span>
                                <span>{{ formatDateTime(tailored.created_at) }}</span>
                            </div>
                        </header>

                        <div class="mt-4 space-y-2 text-sm text-muted-foreground">
                            <p v-if="tailored.job_description">
                                {{
                                    tailored.job_description.title ||
                                    tailored.job_description.source_label
                                }}
                                <template v-if="tailored.job_description.url">
                                    —
                                    <Link
                                        :href="tailored.job_description.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ tailored.job_description.url }}
                                    </Link>
                                </template>
                                <template v-else>
                                    <span class="text-xs text-muted-foreground">
                                        — Manual job description
                                    </span>
                                </template>
                            </p>
                        </div>

                        <div class="mt-4">
                            <MarkdownViewer
                                :content="tailored.content_markdown"
                                content-class="max-h-[480px]"
                            />
                        </div>
                    </article>
                </div>
                <div
                    v-else
                    class="rounded-lg border border-dashed border-border/60 p-6 text-sm text-muted-foreground"
                >
                    No tailored resumes yet. Generate one from a completed evaluation.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
