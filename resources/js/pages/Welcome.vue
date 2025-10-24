<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { dashboard, login, register } from '@/routes';

const currentYear = new Date().getFullYear();

const features = [
    {
        title: 'Evaluate resumes with one click',
        description:
            'Upload your resume and a job description to get alignment feedback with actionable suggestions.',
        highlight: 'Uncover your resume\'s strengths and weaknesses.',
    },
    {
        title: 'Insightful company research',
        description:
            'Research company values, culture, and recent news to tailor your resume and prepare for interviews.',
        highlight: 'Understand your next employer better.',
    },
    {
        title: 'Tailored results that get noticed',
        description:
            'Receive a tailored resume version with highlighted changes and suggestions to improve alignment.',
        highlight: 'Make it fit like a well-tailored suit.',
    },
] as const;

const steps = [
    {
        title: 'Upload your base resume',
        copy: 'Import from PDF or paste Markdown. We automatically separate work history, projects, skills, and achievements.',
    },
    {
        title: 'Drop in a job description',
        copy: 'Paste a job description or link directly to the posting. We parse the role to understand priorities and required keywords.',
    },
    {
        title: 'Evaluate, research, and tailor',
        copy: 'Evaluate your alignment, get tailored suggestions, and research the company to prepare for interviews.',
    },
] as const;

// const stats = [
//     { value: '<7 min', label: 'Get a tailored resume fast' },
//     { value: '<7 min', label: 'Average time to create a versioned resume' },
//     { value: '40k+', label: 'Tailored resumes shipped this year' },
// ] as const;

const testimonials = [
    {
        quote:
            'I was already doing this in a disjointed way, but Resume Tailoring brings it all together in a single dashboard.',
        name: 'Patrick G.',
        role: 'Creator, Resume Tailoring',
    },
] as const;
</script>

<template>
    <Head title="Resume Tailoring">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div
        class="relative min-h-screen bg-[#f9f7f3] text-[#1b1b18] antialiased dark:bg-[#0e1013] dark:text-[#ededec]"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 -top-40 flex justify-center overflow-hidden blur-3xl"
        >
            <div
                class="h-80 w-[50rem] bg-gradient-to-tr from-[#6ee7b7]/60 via-[#93c5fd]/40 to-[#facc15]/40 opacity-50 dark:from-[#34d399]/30 dark:via-[#60a5fa]/20 dark:to-[#f472b6]/20"
            ></div>
        </div>

        <div class="relative mx-auto flex max-w-6xl flex-col px-6 pb-24 pt-8">
            <header class="flex items-center justify-between py-4">
                <Link
                    :href="$page.props.auth.user ? dashboard() : register()"
                    class="flex items-center gap-2"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-md bg-[#0f172a] text-xl font-semibold text-white shadow-sm dark:bg-[#38bdf8]/20 dark:text-[#38bdf8]"
                    >
                        RT
                    </span>
                    <span class="text-base font-semibold leading-tight">
                        Resume Tailoring
                    </span>
                </Link>

                <nav class="hidden items-center gap-8 text-sm font-medium md:flex">
                    <!-- <a href="#features" class="transition hover:text-[#0f172a] dark:hover:text-white">
                        Features
                    </a>
                    <a href="#how-it-works" class="transition hover:text-[#0f172a] dark:hover:text-white">
                        How it works
                    </a>
                    <a href="#testimonials" class="transition hover:text-[#0f172a] dark:hover:text-white">
                        Proof
                    </a> -->
                    <Link
                        :href="login()"
                        class="rounded-md border border-transparent px-4 py-2 transition hover:border-[#d0d0cd] hover:bg-white/70 dark:hover:border-[#1f2937] dark:hover:bg-white/5"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="rounded-md bg-[#0f172a] px-4 py-2 text-white transition hover:bg-[#0c1421] dark:bg-[#38bdf8] dark:text-[#0b1120] dark:hover:bg-[#0ea5e9]"
                    >
                        Start tailoring
                    </Link>
                </nav>

                <div class="md:hidden">
                    <Link
                        :href="$page.props.auth.user ? dashboard() : register()"
                        class="rounded-md bg-[#0f172a] px-3 py-2 text-sm font-medium text-white transition hover:bg-[#0c1421] dark:bg-[#38bdf8] dark:text-[#0b1120] dark:hover:bg-[#0ea5e9]"
                    >
                        {{ $page.props.auth.user ? 'Dashboard' : 'Try for free' }}
                    </Link>
                </div>
            </header>

            <main class="flex flex-1 flex-col">
                <section class="grid gap-12 py-16 md:grid-cols-[1fr,0.9fr] md:items-center">
                    <div class="space-y-6">
                        <!-- <div class="inline-flex items-center gap-3 rounded-full border border-[#d0d0cd] bg-white/70 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-[#6b6b68] dark:border-[#1f2937] dark:bg-[#111827] dark:text-[#9ca3af]">
                            <span class="size-2 rounded-full bg-[#22c55e]"></span>
                            Tailor resumes in minutes
                        </div> -->
                        <h1 class="text-4xl font-semibold leading-tight sm:text-5xl lg:text-6xl">
                            Tailor every resume to the job without rewriting from scratch.
                        </h1>
                        <p class="text-lg leading-relaxed text-[#545451] dark:text-[#b3b3ae]">
                            Resume Tailor evaluates your resume against any job description and provides actionable feedback and performs company research to help you craft the perfect resume and prepare for your interview.
                        </p>
                        <div class="flex flex-wrap items-center gap-4">
                            <Link
                                v-if="!$page.props.auth.user"
                                :href="register()"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-[#0f172a] px-6 py-3 text-base font-medium text-white transition hover:bg-[#0c1421] dark:bg-[#38bdf8] dark:text-[#0b1120] dark:hover:bg-[#0ea5e9]"
                            >
                                Start free trial
                            </Link>
                            <Link
                                v-else
                                :href="dashboard()"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-[#0f172a] px-6 py-3 text-base font-medium text-white transition hover:bg-[#0c1421] dark:bg-[#38bdf8] dark:text-[#0b1120] dark:hover:bg-[#0ea5e9]"
                            >
                                Go to dashboard
                            </Link>
                            <!-- <Link
                                :href="login()"
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-[#d0d0cd] px-6 py-3 text-base font-medium transition hover:border-[#0f172a] hover:text-[#0f172a] dark:border-[#1f2937] dark:hover:border-[#38bdf8] dark:hover:text-[#38bdf8]"
                            >
                                Watch a demo
                            </Link> -->
                        </div>
                        <!-- Stats cards -->
                        <!-- <div class="grid gap-6 pt-8 sm:grid-cols-3">
                            <div
                                v-for="stat in stats"
                                :key="stat.label"
                                class="rounded-xl border border-[#ebeae6] bg-white/80 px-4 py-5 text-sm shadow-sm backdrop-blur-sm dark:border-[#1f2937] dark:bg-[#0f172a]/30"
                            >
                                <div class="text-2xl font-semibold text-[#0f172a] dark:text-white">
                                    {{ stat.value }}
                                </div>
                                <p class="mt-2 text-[#6b6b68] dark:text-[#9ca3af]">
                                    {{ stat.label }}
                                </p>
                            </div>
                        </div> -->
                    </div>
                    <div class="relative">
                        <div
                            class="absolute -left-6 top-10 h-[420px] w-[420px] rounded-full bg-gradient-to-tr from-[#4ade80]/40 via-[#38bdf8]/40 to-[#facc15]/30 blur-3xl opacity-50 dark:from-[#34d399]/10 dark:via-[#38bdf8]/10 dark:to-[#f472b6]/10"
                        ></div>
                        <div
                            class="relative overflow-hidden rounded-2xl border border-[#ebeae6] bg-white shadow-xl shadow-[#0f172a0d] dark:border-[#1f2937] dark:bg-[#111827]"
                        >
                            <div class="border-b border-[#ebeae6] bg-[#f3f4f6] px-6 py-4 text-sm font-semibold text-[#111827] dark:border-[#1f2937] dark:bg-[#0f172a] dark:text-[#e5e7eb]">
                                Tailored version preview
                            </div>
                            <div class="space-y-6 px-6 py-8 text-sm leading-relaxed">
                                <div>
                                    <div class="flex items-center justify-between text-xs uppercase tracking-[0.18em] text-[#6b6b68] dark:text-[#9ca3af]">
                                        Updated
                                    </div>
                                    <h2 class="mt-3 text-lg font-semibold">Senior Product Designer · Figma</h2>
                                    <p class="mt-3 text-[#545451] dark:text-[#d1d5db]">
                                        Reframed collaboration wins, expanded AI initiatives, and raised priority metrics highlighted in the job specification.
                                    </p>
                                </div>
                                <div class="space-y-3 rounded-xl border border-[#ebeae6] bg-[#f9f9f7] p-5 text-sm dark:border-[#1f2937] dark:bg-[#0f172a]/30">
                                    <p class="font-medium text-[#0f172a] dark:text-white">
                                        Suggested updates
                                    </p>
                                    <ul class="space-y-3 text-[#545451] dark:text-[#d1d5db]">
                                        <li>
                                            <span class="mr-2 inline-flex size-5 items-center justify-center rounded-full bg-[#22c55e]/10 text-[#15803d] dark:bg-[#22c55e]/20 dark:text-[#86efac]">
                                                +
                                            </span>
                                            Align bullet points with product-led growth metrics highlighted in the role.
                                        </li>
                                        <li>
                                            <span class="mr-2 inline-flex size-5 items-center justify-center rounded-full bg-[#38bdf8]/10 text-[#0369a1] dark:bg-[#38bdf8]/20 dark:text-[#bae6fd]">
                                                +
                                            </span>
                                            Surface collaboration with data science partners to match culture notes.
                                        </li>
                                        <li>
                                            <span class="mr-2 inline-flex size-5 items-center justify-center rounded-full bg-[#f97316]/10 text-[#c2410c] dark:bg-[#f97316]/20 dark:text-[#fdba74]">
                                                ✱
                                            </span>
                                            Replace generic “improved conversion” metrics with the 28% uplift from experimentation.
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="features" class="space-y-12 py-16">
                    <div class="max-w-3xl space-y-4">
                        <h2 class="text-3xl font-semibold sm:text-4xl">
                            Everything you need to align and prepare.
                        </h2>
                        <p class="text-lg text-[#545451] dark:text-[#b3b3ae]">
                            Resume Tailor was built to automate the way I prepare, research, and align to career opportunities.
                        </p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-3">
                        <article
                            v-for="feature in features"
                            :key="feature.title"
                            class="flex flex-col rounded-2xl border border-[#ebeae6] bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-[#0f172a0d] dark:border-[#1f2937] dark:bg-[#111827]"
                        >
                            <h3 class="text-xl font-semibold text-[#0f172a] dark:text-white">
                                {{ feature.title }}
                            </h3>
                            <p class="mt-4 text-sm leading-relaxed text-[#545451] dark:text-[#d1d5db]">
                                {{ feature.description }}
                            </p>
                            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.22em] text-[#6b6b68] dark:text-[#9ca3af]">
                                {{ feature.highlight }}
                            </p>
                        </article>
                    </div>
                </section>

                <section
                    id="how-it-works"
                    class="mt-8 flex flex-col gap-12 rounded-3xl border border-[#ebeae6] bg-white/90 px-8 py-12 shadow-lg shadow-[#0f172a0f] backdrop-blur-sm dark:border-[#1f2937] dark:bg-[#0f172a]/40"
                >
                    <div class="max-w-2xl space-y-4">
                        <h2 class="text-3xl font-semibold sm:text-4xl">
                            Tailoring should feel effortless.
                        </h2>
                        <p class="text-lg text-[#545451] dark:text-[#d1d5db]">
                            We removed the blank page, added recruiter-backed scoring, and built a workflow you can run between meetings.
                        </p>
                    </div>

                    <ol class="grid gap-8 lg:grid-cols-3">
                        <li
                            v-for="(step, index) in steps"
                            :key="step.title"
                            class="flex flex-col gap-4 rounded-2xl border border-dashed border-[#d0d0cd] bg-white/80 p-6 text-sm shadow-sm dark:border-[#1f2937] dark:bg-transparent"
                        >
                            <span class="inline-flex size-10 items-center justify-center rounded-full bg-[#0f172a] text-base font-semibold text-white dark:bg-[#38bdf8] dark:text-[#0b1120]">
                                {{ index + 1 }}
                            </span>
                            <h3 class="text-lg font-semibold text-[#0f172a] dark:text-white">
                                {{ step.title }}
                            </h3>
                            <p class="leading-relaxed text-[#545451] dark:text-[#d1d5db]">
                                {{ step.copy }}
                            </p>
                        </li>
                    </ol>
                </section>

                <section id="testimonials" class="space-y-12 py-20">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div class="max-w-3xl space-y-3">
                            <h2 class="text-3xl font-semibold sm:text-4xl">
                                Built to help me during my job search.
                            </h2>
                            <p class="text-lg text-[#545451] dark:text-[#b3b3ae]">
                                Resume Tailoring was built to solve my own frustrations with tailoring resumes for each job application. Here's what early users have to say.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <blockquote
                            v-for="testimonial in testimonials"
                            :key="testimonial.name"
                            class="flex h-full flex-col justify-between gap-6 rounded-2xl border border-[#ebeae6] bg-white p-8 text-left shadow-sm dark:border-[#1f2937] dark:bg-[#111827]"
                        >
                            <p class="text-lg leading-relaxed text-[#1b1b18] dark:text-[#f9fafb]">
                                “{{ testimonial.quote }}”
                            </p>
                            <footer class="text-sm font-medium text-[#545451] dark:text-[#d1d5db]">
                                {{ testimonial.name }}
                                <span class="font-normal text-[#6b6b68] dark:text-[#9ca3af]">
                                    · {{ testimonial.role }}
                                </span>
                            </footer>
                        </blockquote>
                    </div>
                </section>
            </main>

            <footer class="mt-16 border-t border-[#ebeae6] pt-8 text-xs text-[#6b6b68] dark:border-[#1f2937] dark:text-[#9ca3af]">
                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                    <p>© {{ currentYear }} Resume Tailoring. Crafted for purposeful applicants. Site by <a href="https://samueldigitalworks.com" target="_blank">Samuel Digital Works</a>.</p>
                    <div class="flex gap-6">
                        <Link :href="login()" class="hover:text-[#0f172a] dark:hover:text-white">
                            Privacy
                        </Link>
                        <Link :href="login()" class="hover:text-[#0f172a] dark:hover:text-white">
                            Terms
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>
