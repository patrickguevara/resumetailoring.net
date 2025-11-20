<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBilling } from '@/composables/useBilling';
import AppLayout from '@/layouts/AppLayout.vue';
import billingRoutes from '@/routes/billing';
import resumeRoutes from '@/routes/resumes';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface TailoredTarget {
    job_id: number;
    job_title?: string | null;
    company?: string | null;
}

interface ResumeSummary {
    id: number;
    slug: string;
    title: string;
    description?: string | null;
    ingestion_status: string;
    ingestion_error?: string | null;
    ingested_at?: string | null;
    uploaded_at?: string | null;
    updated_at?: string | null;
    last_evaluated_at?: string | null;
    evaluations_count: number;
    tailored_count: number;
    tailored_for: TailoredTarget[];
}

interface RecentEvaluation {
    id: number;
    status: string;
    headline?: string | null;
    resume: {
        title?: string | null;
        slug?: string | null;
    };
    job_description: {
        title?: string | null;
        url?: string | null;
        source_label: string;
        is_manual: boolean;
    };
    completed_at?: string | null;
}

const props = defineProps<{
    resumes: ResumeSummary[];
    recent_evaluations: RecentEvaluation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Resumes',
        href: resumeRoutes.index.url(),
    },
];

const form = useForm<{
    title: string;
    description: string;
    input_type: 'markdown' | 'pdf';
    content_markdown: string;
    resume_file: File | null;
}>({
    title: '',
    description: '',
    input_type: 'markdown',
    content_markdown: '',
    resume_file: null,
});

const hasResumes = computed(() => props.resumes.length > 0);
const hasRecentEvaluations = computed(
    () => props.recent_evaluations.length > 0,
);

const fileInput = ref<HTMLInputElement | null>(null);
const isPdfUpload = computed(() => form.input_type === 'pdf');

const { hasSubscription, limitReached, remaining, planPrice } = useBilling();
const resumeLimitReached = limitReached('resume_uploads');
const resumeRemaining = remaining('resume_uploads');
const resumeFormLocked = computed(
    () => !hasSubscription.value && resumeLimitReached.value,
);
const planPriceLabel = computed(() => planPrice.value ?? '$10/month');
const resumeAllowanceCopy = computed(() => {
    if (hasSubscription.value) {
        return 'Unlimited resume uploads included with your plan.';
    }

    const remainingAllowance = resumeRemaining.value;

    if (remainingAllowance && remainingAllowance > 0) {
        return `Preview includes ${remainingAllowance} more resume ${
            remainingAllowance === 1 ? 'upload' : 'uploads'
        }.`;
    }

    return 'You have used your free resume upload.';
});

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement | null;
    const file = target?.files?.[0] ?? null;

    form.resume_file = file ?? null;
};

watch(
    () => form.input_type,
    (type) => {
        if (type === 'markdown') {
            form.resume_file = null;

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        } else {
            form.content_markdown = '';
        }

        form.clearErrors('content_markdown', 'resume_file');
    },
);

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

const resumeStatusConfig: Record<string, { label: string; className: string }> =
    {
        completed: {
            label: 'Ready',
            className: 'border-success/40 bg-success/10 text-success',
        },
        processing: {
            label: 'Processing',
            className: 'border-warning/40 bg-warning/10 text-warning',
        },
        failed: {
            label: 'Failed',
            className: 'border-error/40 bg-error/10 text-error',
        },
    };

const resumeStatusLabel = (status: string) =>
    resumeStatusConfig[status]?.label ?? 'Pending';

const resumeStatusClass = (status: string) =>
    resumeStatusConfig[status]?.className ??
    'border-muted/60 bg-muted/40 text-muted-foreground';

const submit = () => {
    if (resumeFormLocked.value) {
        return;
    }

    if (isPdfUpload.value && !form.resume_file) {
        form.setError('resume_file', 'Please select a PDF resume to upload.');
        return;
    }

    form.post(resumeRoutes.store.url(), {
        preserveScroll: true,
        forceFormData: isPdfUpload.value,
        onSuccess: () => {
            form.reset();
            form.input_type = 'markdown';
            form.resume_file = null;
            form.clearErrors();

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};
</script>

<template>
    <Head title="Resumes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-10 px-6 py-8">
            <section class="max-w-3xl space-y-3">
                <p
                    class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-primary uppercase"
                >
                    <span
                        class="inline-flex size-6 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Sparkles class="size-3.5" />
                    </span>
                    Control center
                </p>
                <h1 class="text-3xl font-semibold text-foreground">
                    Your resume library
                </h1>
                <p class="text-sm text-muted-foreground">
                    Keep master resumes polished, track tailoring targets, and
                    kick off evaluations whenever new roles arrive.
                </p>
            </section>

            <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr),360px]">
                <section
                    class="flex flex-col gap-5 rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                >
                    <header
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">
                                Resume catalog
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Track upload dates, tailored versions, and quick
                                actions.
                            </p>
                        </div>
                    </header>

                    <div
                        v-if="hasResumes"
                        class="-mx-4 overflow-hidden rounded-xl border border-border/60 md:mx-0"
                    >
                        <table
                            class="min-w-full divide-y divide-border/60 text-sm"
                        >
                            <thead
                                class="bg-muted/40 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left">
                                        Resume Name
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left">
                                        Upload Date
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left">
                                        Tailored For
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60 bg-card/60">
                                <tr
                                    v-for="resume in props.resumes"
                                    :key="resume.id"
                                    class="transition hover:bg-accent/10"
                                >
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-col gap-1">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    class="text-sm font-semibold text-foreground"
                                                >
                                                    {{ resume.title }}
                                                </span>
                                                <Badge
                                                    :class="[
                                                        'border px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                                                        resumeStatusClass(
                                                            resume.ingestion_status,
                                                        ),
                                                    ]"
                                                >
                                                    {{
                                                        resumeStatusLabel(
                                                            resume.ingestion_status,
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                            <p
                                                v-if="resume.description"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ resume.description }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ resume.tailored_count }}
                                                tailored version{{
                                                    resume.tailored_count === 1
                                                        ? ''
                                                        : 's'
                                                }}
                                            </p>
                                            <div
                                                class="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground"
                                            >
                                                <span>
                                                    {{
                                                        resume.evaluations_count
                                                    }}
                                                    evaluation{{
                                                        resume.evaluations_count ===
                                                        1
                                                            ? ''
                                                            : 's'
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        resume.ingestion_status ===
                                                        'processing'
                                                    "
                                                    class="hidden sm:inline"
                                                >
                                                    •
                                                </span>
                                                <span
                                                    v-if="
                                                        resume.ingestion_status ===
                                                        'processing'
                                                    "
                                                    class="text-warning"
                                                >
                                                    Processing upload…
                                                </span>
                                            </div>
                                            <p
                                                v-if="
                                                    resume.ingestion_status ===
                                                        'failed' &&
                                                    resume.ingestion_error
                                                "
                                                class="text-xs text-error"
                                            >
                                                {{ resume.ingestion_error }}
                                            </p>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-4 align-top text-sm text-muted-foreground"
                                    >
                                        <div class="flex flex-col gap-1">
                                            <span>
                                                {{
                                                    formatDate(
                                                        resume.uploaded_at,
                                                    ) || '—'
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    resume.ingested_at &&
                                                    resume.ingestion_status ===
                                                        'completed'
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                Converted ·
                                                {{
                                                    formatDateTime(
                                                        resume.ingested_at,
                                                    ) || '—'
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    formatDateTime(
                                                        resume.last_evaluated_at,
                                                    )
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                Last evaluation ·
                                                {{
                                                    formatDateTime(
                                                        resume.last_evaluated_at,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div
                                            v-if="resume.tailored_for.length"
                                            class="flex flex-col gap-2"
                                        >
                                            <div
                                                v-for="target in resume.tailored_for"
                                                :key="target.job_id"
                                                class="rounded-lg border border-border/60 bg-background/80 px-3 py-2 text-sm shadow-sm"
                                            >
                                                <p
                                                    class="font-medium text-foreground"
                                                >
                                                    {{
                                                        target.job_title ||
                                                        'Untitled role'
                                                    }}
                                                </p>
                                                <p
                                                    v-if="target.company"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ target.company }}
                                                </p>
                                            </div>
                                            <p
                                                v-if="
                                                    resume.tailored_count >
                                                    resume.tailored_for.length
                                                "
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                +{{
                                                    resume.tailored_count -
                                                    resume.tailored_for.length
                                                }}
                                                more tailored version{{
                                                    resume.tailored_count -
                                                        resume.tailored_for
                                                            .length ===
                                                    1
                                                        ? ''
                                                        : 's'
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            v-else
                                            class="text-sm text-muted-foreground"
                                        >
                                            <p>No tailored versions yet.</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <Button size="sm" as-child>
                                                <Link
                                                    :href="
                                                        resumeRoutes.show({
                                                            slug: resume.slug,
                                                        }).url
                                                    "
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                            <Button
                                                v-if="
                                                    resume.ingestion_status ===
                                                    'completed'
                                                "
                                                size="sm"
                                                variant="outline"
                                                as-child
                                            >
                                                <Link
                                                    :href="`${
                                                        resumeRoutes.show({
                                                            slug: resume.slug,
                                                        }).url
                                                    }#evaluate`"
                                                >
                                                    Evaluate
                                                </Link>
                                            </Button>
                                            <Button
                                                v-else
                                                size="sm"
                                                variant="outline"
                                                type="button"
                                                disabled
                                                class="cursor-not-allowed text-muted-foreground"
                                            >
                                                Processing…
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
                                No resumes yet
                            </span>
                        </div>
                        <p>
                            Upload a PDF or paste your markdown to start
                            tailoring for roles. Use the form on the right to
                            add your first version.
                        </p>
                    </div>
                </section>

                <aside
                    class="flex flex-col gap-4 rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
                >
                    <header class="space-y-1">
                        <h2 class="text-lg font-semibold text-foreground">
                            Add a resume
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Give it a clear name, then either paste your
                            markdown or upload a PDF. We’ll normalize the
                            content automatically.
                        </p>
                    </header>

                    <div
                        v-if="resumeFormLocked"
                        class="rounded-xl border border-primary/30 bg-primary/10 p-4 text-sm text-primary"
                    >
                        <p class="font-semibold text-foreground">
                            Free upload used
                        </p>
                        <p class="mt-1 text-xs text-primary/80">
                            Upgrade for {{ planPriceLabel }} to add unlimited
                            resumes.
                        </p>
                        <Button
                            size="sm"
                            class="mt-3"
                            variant="secondary"
                            as-child
                        >
                            <Link :href="billingRoutes.edit.url()">
                                Upgrade for {{ planPriceLabel }}
                            </Link>
                        </Button>
                    </div>
                    <div
                        v-else
                        class="rounded-xl border border-border/50 bg-muted/40 p-3 text-xs text-muted-foreground"
                    >
                        {{ resumeAllowanceCopy }}
                    </div>

                    <form class="flex flex-col gap-5" @submit.prevent="submit">
                        <fieldset
                            :disabled="resumeFormLocked"
                            class="flex flex-col gap-5"
                        >
                            <div class="space-y-2">
                                <Label for="title">Title</Label>
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    name="title"
                                    type="text"
                                    placeholder="Product Manager Resume"
                                    :aria-invalid="!!form.errors.title"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Include the role or specialization so you
                                    can find it quickly later.
                                </p>
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Summary</Label>
                                <Input
                                    id="description"
                                    v-model="form.description"
                                    name="description"
                                    type="text"
                                    placeholder="Primary resume used for PM roles"
                                    :aria-invalid="!!form.errors.description"
                                />
                                <InputError
                                    :message="form.errors.description"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label class="text-xs font-semibold uppercase">
                                    Resume source
                                </Label>
                                <div class="grid grid-cols-2 gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="
                                            form.input_type === 'markdown'
                                                ? 'default'
                                                : 'outline'
                                        "
                                        class="justify-center"
                                        @click="form.input_type = 'markdown'"
                                    >
                                        Paste markdown
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="
                                            form.input_type === 'pdf'
                                                ? 'default'
                                                : 'outline'
                                        "
                                        class="justify-center"
                                        @click="form.input_type = 'pdf'"
                                    >
                                        Upload PDF
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="form.input_type === 'markdown'"
                                class="space-y-2"
                            >
                                <Label for="content_markdown"
                                    >Resume markdown</Label
                                >
                                <textarea
                                    id="content_markdown"
                                    v-model="form.content_markdown"
                                    name="content_markdown"
                                    rows="12"
                                    class="min-h-[240px] w-full max-w-full resize-y rounded-lg border border-border/70 bg-background px-3 py-3 font-mono text-sm leading-relaxed text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none"
                                    placeholder="# Summary&#10;&#10;Detail your experience..."
                                    :aria-invalid="
                                        !!form.errors.content_markdown
                                    "
                                />
                                <InputError
                                    :message="form.errors.content_markdown"
                                />
                            </div>

                            <div v-else class="space-y-2">
                                <Label for="resume_file">PDF resume</Label>
                                <Input
                                    id="resume_file"
                                    ref="fileInput"
                                    name="resume_file"
                                    type="file"
                                    accept="application/pdf"
                                    :aria-invalid="!!form.errors.resume_file"
                                    @change="handleFileChange"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Upload up to 5&nbsp;MB. We’ll convert the
                                    PDF to markdown and delete the file after
                                    processing.
                                </p>
                                <InputError
                                    :message="form.errors.resume_file"
                                />
                            </div>

                            <Button
                                type="submit"
                                :disabled="form.processing || resumeFormLocked"
                                class="justify-center"
                            >
                                Save resume
                            </Button>
                        </fieldset>
                    </form>
                </aside>
            </div>

            <section
                class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
            >
                <header
                    class="flex flex-wrap items-center justify-between gap-4"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">
                            Recent evaluations
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Latest analysis results across all of your resumes.
                        </p>
                    </div>
                    <span
                        class="rounded-full border border-border/60 bg-muted/40 px-3 py-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ props.recent_evaluations.length }} total
                    </span>
                </header>

                <div
                    v-if="hasRecentEvaluations"
                    class="mt-4 divide-y divide-border/60"
                >
                    <article
                        v-for="evaluation in props.recent_evaluations"
                        :key="evaluation.id"
                        class="flex flex-wrap items-start justify-between gap-4 py-4 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-[220px] flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ evaluation.resume.title || 'Resume' }}
                                </p>
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase',
                                        evaluationStatusClass(
                                            evaluation.status,
                                        ),
                                    ]"
                                >
                                    {{
                                        evaluationStatusLabel(evaluation.status)
                                    }}
                                </span>
                            </div>
                            <p
                                v-if="evaluation.headline"
                                class="text-sm text-muted-foreground"
                            >
                                {{ evaluation.headline }}
                            </p>
                        </div>

                        <div
                            class="min-w-[200px] flex-1 text-sm text-muted-foreground"
                        >
                            <p class="font-medium text-foreground">
                                {{
                                    evaluation.job_description.title ||
                                    evaluation.job_description.source_label
                                }}
                            </p>
                            <div
                                class="inline-flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <template v-if="evaluation.job_description.url">
                                    <a
                                        :href="evaluation.job_description.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        View posting
                                    </a>
                                </template>
                                <span v-else>Manual input</span>
                            </div>
                        </div>

                        <div
                            class="flex flex-col items-end gap-2 text-xs text-muted-foreground"
                        >
                            <span>
                                {{
                                    formatDateTime(evaluation.completed_at) ||
                                    'Pending'
                                }}
                            </span>
                            <Link
                                v-if="evaluation.resume.slug"
                                :href="
                                    resumeRoutes.show({
                                        slug: evaluation.resume.slug,
                                    }).url
                                "
                                class="text-xs font-medium text-primary underline-offset-4 hover:underline"
                            >
                                View timeline
                            </Link>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-xl border border-dashed border-border/60 bg-background/80 p-8 text-sm text-muted-foreground"
                >
                    Run an evaluation to see it appear here.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
