<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppLogoIcon from '@/components/AppLogoIcon.vue';

const currentYear = new Date().getFullYear();
</script>

<template>
    <Head title="Privacy Policy • Resume Tailoring" />

    <div
        class="min-h-screen bg-[#f9f7f3] px-6 py-10 text-[#1b1b18] antialiased dark:bg-[#0e1013] dark:text-[#ededec]"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-12">
            <header class="flex items-center justify-between">
                <Link
                    href="/"
                    class="flex items-center gap-3 text-sm font-semibold"
                >
                    <AppLogoIcon class="size-10" />
                    <span>Resume Tailoring</span>
                </Link>

                <Link
                    href="/"
                    class="text-sm font-medium text-[#545451] transition hover:text-[#0f172a] dark:text-[#b3b3ae] dark:hover:text-white"
                >
                    Back to home
                </Link>
            </header>

            <main
                class="space-y-8 rounded-3xl border border-[#ebeae6] bg-white/80 p-8 shadow-sm backdrop-blur-sm dark:border-[#1f2937] dark:bg-[#111827] dark:shadow-none"
            >
                <div class="space-y-3">
                    <p
                        class="text-xs font-semibold tracking-[0.28em] text-[#6b6b68] uppercase dark:text-[#9ca3af]"
                    >
                        Privacy Policy
                    </p>
                    <h1 class="text-3xl leading-tight font-semibold">
                        Your data stays your own.
                    </h1>
                    <p class="text-sm text-[#545451] dark:text-[#b3b3ae]">
                        Last updated: {{ currentYear }}
                    </p>
                </div>

                <div
                    class="space-y-4 text-base leading-relaxed text-[#3c3c38] dark:text-[#d1d5db]"
                >
                    <p>
                        We keep things simple. Resume Tailoring only gathers the
                        information we need to run the app and help you tailor
                        your resume.
                    </p>

                    <div>
                        <h2
                            class="text-lg font-semibold text-[#0f172a] dark:text-white"
                        >
                            What we store
                        </h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>Your account details so you can log in.</li>
                            <li>
                                Resumes, job descriptions, and evaluation
                                history you choose to upload.
                            </li>
                            <li>
                                Basic usage data that helps us keep the service
                                running smoothly.
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h2
                            class="text-lg font-semibold text-[#0f172a] dark:text-white"
                        >
                            How we use it
                        </h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>
                                Tailor resumes, generate feedback, and show you
                                company research.
                            </li>
                            <li>
                                Improve reliability, performance, and support.
                            </li>
                            <li>
                                Send essential account or product updates (no
                                spam, ever).
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h2
                            class="text-lg font-semibold text-[#0f172a] dark:text-white"
                        >
                            Your choices
                        </h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>
                                Request a full account export or deletion by
                                emailing
                                <a
                                    href="mailto:support@resumetailoring.net"
                                    class="underline decoration-[#38bdf8] decoration-2 underline-offset-4 hover:text-[#0f172a] dark:hover:text-white"
                                    >support@resumetailoring.net</a
                                >.
                            </li>
                        </ul>
                    </div>

                    <p>
                        If we ever change how we handle data, we’ll update this
                        page and let you know inside the app.
                    </p>
                </div>
            </main>

            <footer
                class="flex flex-col items-start gap-2 text-sm text-[#6b6b68] sm:flex-row sm:items-center sm:justify-between dark:text-[#9ca3af]"
            >
                <p>
                    Need something clarified?
                    <a
                        href="mailto:support@resumetailoring.net"
                        class="underline decoration-[#38bdf8] decoration-2 underline-offset-4 hover:text-[#0f172a] dark:hover:text-white"
                        >Email us</a
                    >.
                </p>
                <div class="flex gap-4">
                    <Link
                        href="/terms"
                        class="hover:text-[#0f172a] dark:hover:text-white"
                        >Terms of Service</Link
                    >
                    <Link
                        href="/"
                        class="hover:text-[#0f172a] dark:hover:text-white"
                        >Homepage</Link
                    >
                </div>
            </footer>
        </div>
    </div>
</template>
