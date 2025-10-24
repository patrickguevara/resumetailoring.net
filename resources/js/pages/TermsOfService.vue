<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppLogoIcon from '@/components/AppLogoIcon.vue';

const currentYear = new Date().getFullYear();
</script>

<template>
    <Head title="Terms of Service • Resume Tailoring" />

    <div class="min-h-screen bg-[#f9f7f3] px-6 py-10 text-[#1b1b18] antialiased dark:bg-[#0e1013] dark:text-[#ededec]">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-12">
            <header class="flex items-center justify-between">
                <Link href="/" class="flex items-center gap-3 text-sm font-semibold">
                    <AppLogoIcon class="size-10" />
                    <span>Resume Tailoring</span>
                </Link>

                <Link href="/" class="text-sm font-medium text-[#545451] transition hover:text-[#0f172a] dark:text-[#b3b3ae] dark:hover:text-white">
                    Back to home
                </Link>
            </header>

            <main class="space-y-8 rounded-3xl border border-[#ebeae6] bg-white/80 p-8 shadow-sm backdrop-blur-sm dark:border-[#1f2937] dark:bg-[#111827] dark:shadow-none">
                <div class="space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#6b6b68] dark:text-[#9ca3af]">
                        Terms of Service
                    </p>
                    <h1 class="text-3xl font-semibold leading-tight">Use Resume Tailoring responsibly.</h1>
                    <p class="text-sm text-[#545451] dark:text-[#b3b3ae]">Effective: {{ currentYear }}</p>
                </div>

                <div class="space-y-4 text-base leading-relaxed text-[#3c3c38] dark:text-[#d1d5db]">
                    <p>These terms explain how Resume Tailoring works and what we expect from anyone using the product. No dense legal language.</p>

                    <div>
                        <h2 class="text-lg font-semibold text-[#0f172a] dark:text-white">When you create an account</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>You confirm that the content you upload is yours to share.</li>
                            <li>You keep your login secure and let us know right away if you suspect unauthorized access.</li>
                            <li>You agree to let us process your content so we can tailor resumes and provide feedback.</li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold text-[#0f172a] dark:text-white">What we provide</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>Access to the Resume Tailoring app and its features as described on the site.</li>
                            <li>Reasonable support for technical issues via <a href="mailto:support@resumetailoring.net" class="underline decoration-[#38bdf8] decoration-2 underline-offset-4 hover:text-[#0f172a] dark:hover:text-white">support@resumetailoring.net</a>.</li>
                            <li>Upfront notice if we make meaningful changes to the product or these terms.</li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold text-[#0f172a] dark:text-white">Ground rules</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>Don’t misuse the platform—no scraping, reverse engineering, or trying to break security.</li>
                            <li>We may suspend accounts that abuse the service or put other users at risk.</li>
                            <li>Resume Tailoring is provided "as is." We work hard to keep it available, but can't guarantee uninterrupted access.</li>
                        </ul>
                    </div>

                    <p>By using Resume Tailoring you agree to these terms. If you ever disagree, you can stop using the service and request deletion of your account.</p>
                </div>
            </main>

            <footer class="flex flex-col items-start gap-2 text-sm text-[#6b6b68] dark:text-[#9ca3af] sm:flex-row sm:items-center sm:justify-between">
                <p>Questions about the fine print? <a href="mailto:support@resumetailoring.net" class="underline decoration-[#38bdf8] decoration-2 underline-offset-4 hover:text-[#0f172a] dark:hover:text-white">Email us</a>.</p>
                <div class="flex gap-4">
                    <Link href="/privacy" class="hover:text-[#0f172a] dark:hover:text-white">Privacy Policy</Link>
                    <Link href="/" class="hover:text-[#0f172a] dark:hover:text-white">Homepage</Link>
                </div>
            </footer>
        </div>
    </div>
</template>
