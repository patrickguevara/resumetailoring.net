<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import resumeRoutes from '@/routes/resumes';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Sparkles } from 'lucide-vue-next';

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

const form = useForm({
    title: '',
    description: '',
    content_markdown: '',
});

const hasResumes = computed(() => props.resumes.length > 0);
const hasRecentEvaluations = computed(
    () => props.recent_evaluations.length > 0,
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

const submit = () => {
    form.post(resumeRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
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
                    class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-primary"
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
                    <header class="flex flex-wrap items-center justify-between gap-4">
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
                                class="bg-muted/40 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
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
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-sm font-semibold text-foreground"
                                                >
                                                    {{ resume.title }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full border border-muted/60 bg-muted/30 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground"
                                                >
                                                    {{ resume.evaluations_count }}
                                                    eval{{ resume.evaluations_count === 1 ? '' : 's' }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="resume.description"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ resume.description }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ resume.tailored_count }} tailored
                                                version{{ resume.tailored_count === 1 ? '' : 's' }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-muted-foreground">
                                        <div class="flex flex-col gap-1">
                                            <span>
                                                {{ formatDate(resume.uploaded_at) || '—' }}
                                            </span>
                                            <span
                                                v-if="formatDateTime(resume.last_evaluated_at)"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Last evaluation ·
                                                {{ formatDateTime(resume.last_evaluated_at) }}
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
                                                <p class="font-medium text-foreground">
                                                    {{ target.job_title || 'Untitled role' }}
                                                </p>
                                                <p
                                                    v-if="target.company"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ target.company }}
                                                </p>
                                            </div>
                                            <p
                                                v-if="resume.tailored_count > resume.tailored_for.length"
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                +{{
                                                    resume.tailored_count -
                                                    resume.tailored_for.length
                                                }}
                                                more tailored
                                                version{{
                                                    resume.tailored_count -
                                                        resume.tailored_for.length ===
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
                                                size="sm"
                                                variant="outline"
                                                as-child
                                            >
                                                <Link
                                                    :href="`${resumeRoutes.show({
                                                        slug: resume.slug,
                                                    }).url}#evaluate`"
                                                >
                                                    Evaluate
                                                </Link>
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
                            Upload your base resume to start tailoring for
                            roles. Use the form on the right to add your first
                            version.
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
                            Give it a clear name and paste your markdown resume
                            content.
                        </p>
                    </header>

                    <form class="flex flex-col gap-5" @submit.prevent="submit">
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
                                Include the role or specialization so you can
                                find it quickly later.
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
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="space-y-2">
                            <Label for="content_markdown">Resume markdown</Label>
                            <textarea
                                id="content_markdown"
                                v-model="form.content_markdown"
                                name="content_markdown"
                                rows="12"
                                class="min-h-[240px] w-full rounded-lg border border-border/70 bg-background px-3 py-3 text-sm font-mono leading-relaxed text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                                placeholder="# Summary&#10;&#10;Detail your experience..."
                                :aria-invalid="!!form.errors.content_markdown"
                            />
                            <InputError :message="form.errors.content_markdown" />
                        </div>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="justify-center"
                        >
                            Save resume
                        </Button>
                    </form>
                </aside>
            </div>

            <section
                class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
            >
                <header class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">
                            Recent evaluations
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Latest analysis results across all of your resumes.
                        </p>
                    </div>
                    <span
                        class="rounded-full border border-border/60 bg-muted/40 px-3 py-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
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
                                <p class="text-sm font-semibold text-foreground">
                                    {{ evaluation.resume.title || 'Resume' }}
                                </p>
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide',
                                        evaluationStatusClass(evaluation.status),
                                    ]"
                                >
                                    {{ evaluationStatusLabel(evaluation.status) }}
                                </span>
                            </div>
                            <p
                                v-if="evaluation.headline"
                                class="text-sm text-muted-foreground"
                            >
                                {{ evaluation.headline }}
                            </p>
                        </div>

                        <div class="min-w-[200px] flex-1 text-sm text-muted-foreground">
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

                        <div class="flex flex-col items-end gap-2 text-xs text-muted-foreground">
                            <span>
                                {{ formatDateTime(evaluation.completed_at) || 'Pending' }}
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
