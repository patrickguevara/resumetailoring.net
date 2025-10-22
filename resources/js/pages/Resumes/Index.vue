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

interface ResumeSummary {
    id: number;
    slug: string;
    title: string;
    description?: string | null;
    evaluations_count: number;
    tailored_count: number;
    updated_at?: string | null;
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
        <div class="flex flex-col gap-6 px-4 py-6">
            <div class="grid gap-6 md:grid-cols-[2fr,1fr]">
                <section class="flex flex-col gap-4">
                    <header class="flex flex-col gap-1">
                        <h1 class="text-2xl font-semibold text-foreground">
                            Your resumes
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Manage the base resumes you want to evaluate
                            against job descriptions.
                        </p>
                    </header>

                    <div
                        v-if="hasResumes"
                        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="resume in resumes"
                            :key="resume.id"
                            class="flex h-full flex-col justify-between rounded-lg border border-border/60 bg-background/60 p-4 shadow-sm transition hover:border-primary/70 hover:shadow-md dark:border-border/40"
                        >
                            <div class="flex flex-col gap-2">
                                <h2 class="text-lg font-medium text-foreground">
                                    {{ resume.title }}
                                </h2>
                                <p
                                    v-if="resume.description"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ resume.description }}
                                </p>
                            </div>
                            <dl
                                class="mt-4 grid grid-cols-2 gap-3 text-sm text-muted-foreground"
                            >
                                <div>
                                    <dt class="font-medium text-foreground">
                                        Evaluations
                                    </dt>
                                    <dd>{{ resume.evaluations_count }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-foreground">
                                        Tailored
                                    </dt>
                                    <dd>{{ resume.tailored_count }}</dd>
                                </div>
                            </dl>
                            <Link
                                :href="resumeRoutes.show({ slug: resume.slug }).url"
                                class="mt-6 inline-flex items-center justify-center rounded-md border border-border/80 px-4 py-2 text-sm font-medium text-primary hover:border-primary"
                            >
                                View timeline
                            </Link>
                        </article>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-start gap-4 rounded-lg border border-dashed border-border/60 p-6 text-sm text-muted-foreground"
                    >
                        <h2 class="text-lg font-medium text-foreground">
                            You have not added a resume yet.
                        </h2>
                        <p>
                            Add your base resume in markdown using the form on
                            the right to get started.
                        </p>
                    </div>
                </section>

                <section
                    class="rounded-lg border border-border/60 bg-background/60 p-6 shadow-sm dark:border-border/40"
                >
                    <header class="mb-4">
                        <h2 class="text-lg font-semibold text-foreground">
                            Add a resume
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Provide a descriptive title and paste your markdown
                            resume content.
                        </p>
                    </header>

                    <form class="flex flex-col gap-4" @submit.prevent="submit">
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
                                class="min-h-[220px] w-full rounded-md border border-border/70 bg-background px-3 py-2 text-sm font-mono leading-relaxed text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 dark:border-border/40 dark:bg-background/40"
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
                </section>
            </div>

            <section
                class="rounded-lg border border-border/60 bg-background/60 p-6 shadow-sm dark:border-border/40"
            >
                <header class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">
                            Recent evaluations
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Latest analysis results across all of your resumes.
                        </p>
                    </div>
                    <span class="text-sm text-muted-foreground">
                        {{ props.recent_evaluations.length }} total
                    </span>
                </header>

                <div v-if="hasRecentEvaluations" class="flex flex-col gap-4">
                    <article
                        v-for="evaluation in props.recent_evaluations"
                        :key="evaluation.id"
                        class="flex flex-col gap-2 rounded-md border border-border/60 bg-background/70 p-4 shadow-sm transition hover:border-primary/70 hover:shadow-md dark:border-border/40"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    {{ evaluation.resume.title || 'Resume' }}
                                </p>
                                <Link
                                    v-if="evaluation.resume.slug"
                                    :href="resumeRoutes.show({ slug: evaluation.resume.slug }).url"
                                    class="text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    View timeline
                                </Link>
                            </div>
                            <span
                                class="rounded-full border border-border/60 px-3 py-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                {{ evaluation.status }}
                            </span>
                        </div>

                        <p
                            v-if="evaluation.headline"
                            class="text-sm font-medium text-foreground"
                        >
                            {{ evaluation.headline }}
                        </p>

                        <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>
                                {{
                                    evaluation.job_description.title ||
                                    evaluation.job_description.source_label
                                }}
                            </span>
                            <template v-if="evaluation.job_description.url">
                                <span>•</span>
                                <a
                                    :href="evaluation.job_description.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-primary underline-offset-4 hover:underline"
                                >
                                    View posting
                                </a>
                            </template>
                            <span v-else class="text-xs text-muted-foreground">
                                Manual input
                            </span>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            {{ evaluation.completed_at ? new Date(evaluation.completed_at).toLocaleString() : 'Pending' }}
                        </p>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-md border border-dashed border-border/60 p-6 text-sm text-muted-foreground"
                >
                    Run an evaluation to see it appear here.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
