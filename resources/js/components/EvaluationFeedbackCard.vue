<script setup lang="ts">
import MarkdownViewer from '@/components/MarkdownViewer.vue';
import SentimentBadge from '@/components/SentimentBadge.vue';
import HighlightStats from '@/components/HighlightStats.vue';
import KeyPhrasesList from '@/components/KeyPhrasesList.vue';
import { computed } from 'vue';

interface FeedbackData {
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
}

const props = defineProps<{
    feedbackData: FeedbackData | null;
    fallbackMarkdown?: string | null;
}>();

const hasStructuredData = computed(() => {
    return props.feedbackData?.sections?.summary !== undefined;
});

const sentiment = computed(() => props.feedbackData?.sentiment ?? 'good_match');
const highlights = computed(() => props.feedbackData?.highlights ?? null);
const keyPhrases = computed(() => props.feedbackData?.key_phrases ?? []);
const sections = computed(() => props.feedbackData?.sections ?? {});

const summaryGradient = computed(() => {
    const gradients = {
        strong_match: 'from-success/20 via-background to-background',
        good_match: 'from-accent/20 via-background to-background',
        partial_match: 'from-warning/20 via-background to-background',
        weak_match: 'from-muted/30 via-background to-background',
    };
    return (
        gradients[sentiment.value as keyof typeof gradients] ??
        gradients.good_match
    );
});
</script>

<template>
    <!-- Structured Layout -->
    <div v-if="hasStructuredData" class="space-y-6">
        <!-- Summary Section (Full Width) -->
        <section
            :class="[
                'rounded-2xl border border-border/60 p-6 shadow-sm',
                'bg-gradient-to-br',
                summaryGradient,
            ]"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h3 class="text-lg font-semibold text-foreground">
                    Summary of Fit
                </h3>
                <SentimentBadge :sentiment="sentiment" />
            </div>

            <HighlightStats
                v-if="highlights"
                :highlights="highlights"
                class="mb-4"
            />

            <KeyPhrasesList
                v-if="keyPhrases.length > 0"
                :phrases="keyPhrases"
                class="mb-4"
            />

            <MarkdownViewer
                :content="sections.summary ?? '_No summary available._'"
            />
        </section>

        <!-- Split Panel: Experience & Gaps (50/50 on Desktop) -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Relevant Experience -->
            <section
                v-if="sections.relevant_experience"
                class="rounded-2xl border border-border/60 bg-gradient-to-br from-background via-background to-accent/5 p-6 shadow-sm"
            >
                <h3 class="text-lg font-semibold text-foreground mb-4">
                    Relevant Experience
                </h3>
                <MarkdownViewer :content="sections.relevant_experience" />
            </section>

            <!-- Gaps -->
            <section
                v-if="sections.gaps"
                class="rounded-2xl border border-border/60 bg-gradient-to-br from-background via-background to-muted/10 p-6 shadow-sm"
            >
                <h3 class="text-lg font-semibold text-foreground mb-4">
                    Gaps
                </h3>
                <MarkdownViewer :content="sections.gaps" />
            </section>
        </div>

        <!-- Recommendations Section (Full Width) -->
        <section
            v-if="sections.recommendations"
            class="rounded-2xl border border-border/60 bg-gradient-to-br from-primary/5 via-background to-background p-6 shadow-sm"
        >
            <h3 class="text-lg font-semibold text-foreground mb-4">
                Recommendations
            </h3>
            <MarkdownViewer :content="sections.recommendations" />
        </section>
    </div>

    <!-- Fallback: Legacy Markdown Display -->
    <div
        v-else
        class="rounded-xl border border-border/60 bg-background/80 p-4"
    >
        <MarkdownViewer
            :content="
                fallbackMarkdown ??
                '_Feedback is still processing or unavailable._'
            "
        />
    </div>
</template>
