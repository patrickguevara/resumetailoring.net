# Evaluation Feedback Visual Refresh - Design Document

**Date:** November 20, 2025
**Status:** Design Complete
**Goal:** Transform text-heavy job evaluation feedback into a visually structured, scannable format with clear visual hierarchy

---

## Problem Statement

Current evaluation feedback is displayed as a wall of markdown text, making it difficult for users to:
- Quickly assess overall job fit
- Identify key strengths vs. gaps
- Extract actionable recommendations
- Parse dense information efficiently

## Solution Overview

Transform the evaluation feedback display into a structured split-panel layout with:
1. **Sentiment-driven summary** with visual indicators and quick stats
2. **Side-by-side comparison** of strengths (Relevant Experience) vs. areas to improve (Gaps)
3. **Full-width actionable recommendations** section
4. **Structured JSON response** from AI for reliable parsing and visual enhancements

---

## Visual Layout

### Desktop Layout
```
┌─────────────────────────────────────────────────────────────┐
│                    SUMMARY OF FIT                           │
│  [Sentiment Badge]  [8 skills] [5 years exp] [2 gaps]      │
│  • Key phrase highlighting                                  │
│  • Overall assessment prose                                 │
│  [Sentiment-driven gradient background]                     │
└─────────────────────────────────────────────────────────────┘
┌──────────────────────────┬──────────────────────────────────┐
│   RELEVANT EXPERIENCE    │            GAPS                  │
│   [Success accent]       │      [Neutral/Info accent]       │
│   • Matching skills      │      • Missing qualifications    │
│   • Relevant background  │      • Areas to develop          │
│   • Aligned experience   │      • Skill gaps                │
└──────────────────────────┴──────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                    RECOMMENDATIONS                          │
│   [Primary accent]                                          │
│   1. Actionable next steps                                  │
│   2. Suggested improvements                                 │
│   3. Application strategy                                   │
└─────────────────────────────────────────────────────────────┘
```

### Mobile Layout (Stacked Vertically)
```
┌─────────────────┐
│  SUMMARY OF FIT │
├─────────────────┤
│    RELEVANT     │
│   EXPERIENCE    │
├─────────────────┤
│      GAPS       │
├─────────────────┤
│ RECOMMENDATIONS │
└─────────────────┘
```

---

## Data Structure

### AI Response Format (JSON)

The AI will return structured JSON instead of plain markdown:

```json
{
  "sentiment": "strong_match" | "good_match" | "partial_match" | "weak_match",
  "highlights": {
    "matching_skills": 8,
    "relevant_years": 5,
    "key_gaps": 2
  },
  "key_phrases": [
    "Strong technical background in React and TypeScript",
    "Missing AWS certification",
    "Excellent communication skills demonstrated"
  ],
  "sections": {
    "summary": "## Summary of Fit\n\nThe candidate presents a strong match...",
    "relevant_experience": "## Relevant Experience\n\n- 5 years of frontend development...",
    "gaps": "## Gaps\n\n- AWS certification is required but not held...",
    "recommendations": "## Recommendations\n\n1. Obtain AWS Solutions Architect certification..."
  }
}
```

### Sentiment Mapping

| Sentiment | Visual Treatment | Badge Text | Icon | Gradient |
|-----------|-----------------|------------|------|----------|
| `strong_match` | Success | "Strong Match" | CheckCircle | `from-success/20 via-background to-background` |
| `good_match` | Accent | "Good Match" | ThumbsUp | `from-accent/20 via-background to-background` |
| `partial_match` | Warning | "Partial Match" | Info | `from-warning/20 via-background to-background` |
| `weak_match` | Muted | "Needs Improvement" | AlertTriangle | `from-muted/30 via-background to-background` |

### Highlights Display

Quick stat cards within the summary section:
- **Matching Skills**: Number with checkmark icon
- **Relevant Years**: Number with briefcase icon
- **Key Gaps**: Number with target icon

### Key Phrases

Extract 3-5 short, impactful phrases from the evaluation to display prominently in the summary section with visual emphasis (pills, badges, or highlighted text).

---

## Database Schema

### Migration: Add Structured Feedback Column

```php
Schema::table('resume_evaluations', function (Blueprint $table) {
    $table->json('feedback_structured')->nullable()->after('feedback_markdown');
});
```

### Data Storage Strategy

1. **Store JSON** in `feedback_structured` column
2. **Generate and store markdown** in `feedback_markdown` column (concatenate all sections)
3. **Benefit**: Backward compatibility + flexibility + fallback rendering

### Model Updates (`app/Models/ResumeEvaluation.php`)

```php
protected $casts = [
    'feedback_structured' => 'array',
    // ... existing casts
];

/**
 * Smart accessor that returns structured data or falls back to legacy format
 */
public function getFeedbackDataAttribute(): array
{
    if ($this->feedback_structured) {
        return $this->feedback_structured;
    }

    // Fallback for old evaluations without structured data
    return [
        'sentiment' => 'good_match',
        'highlights' => null,
        'key_phrases' => [],
        'sections' => [
            'summary' => $this->feedback_markdown ?? '',
            'relevant_experience' => null,
            'gaps' => null,
            'recommendations' => null,
        ]
    ];
}
```

---

## Backend Implementation

### 1. Update System Prompt (`config/resume_intelligence.php`)

**Current prompt** (lines 10-18):
```php
'system_prompt' => env(
    'OPENAI_ANALYSIS_SYSTEM_PROMPT',
    <<<'PROMPT'
You are an experienced career coach and resume analyst helping a candidate decide if a job is a strong fit.

Review the provided job description and the candidate's resume. Highlight how well the candidate matches the role,
identify the most relevant experience, and point out any gaps or missing qualifications. Recommend clear, actionable
steps the candidate can take to strengthen their candidacy.

Return the evaluation results in Markdown format with clear sections: Summary of Fit, Relevant Experience, Gaps, and Recommendations. Separate the sections with appropriate headings.
PROMPT
),
```

**New prompt** (keep the persona and instructions, update response format):
```php
'system_prompt' => env(
    'OPENAI_ANALYSIS_SYSTEM_PROMPT',
    <<<'PROMPT'
You are an experienced career coach and resume analyst helping a candidate decide if a job is a strong fit.

Review the provided job description and the candidate's resume. Highlight how well the candidate matches the role,
identify the most relevant experience, and point out any gaps or missing qualifications. Recommend clear, actionable
steps the candidate can take to strengthen their candidacy.

Return your response as a JSON object with the following structure:

{
  "sentiment": "strong_match" | "good_match" | "partial_match" | "weak_match",
  "highlights": {
    "matching_skills": <number of clearly matching skills or qualifications>,
    "relevant_years": <years of relevant experience>,
    "key_gaps": <number of significant gaps or missing qualifications>
  },
  "key_phrases": [
    "<2-5 short impactful phrases that capture key strengths or gaps>",
    "Example: Strong technical background in React and TypeScript",
    "Example: Missing required AWS certification"
  ],
  "sections": {
    "summary": "<Markdown content for Summary of Fit section>",
    "relevant_experience": "<Markdown content for Relevant Experience section>",
    "gaps": "<Markdown content for Gaps section>",
    "recommendations": "<Markdown content for Recommendations section>"
  }
}

Sentiment guidelines:
- strong_match: Candidate exceeds or strongly meets most requirements
- good_match: Candidate meets core requirements with minor gaps
- partial_match: Candidate has relevant background but significant gaps exist
- weak_match: Candidate lacks multiple key qualifications

Ensure each section uses markdown formatting with bullet points, numbered lists, and emphasis where appropriate.
PROMPT
),
```

### 2. Update Response Parsing (`app/Services/ResumeIntelligenceService.php`)

**Modify `evaluate()` method** (lines 25-64):

```php
public function evaluate(
    Resume $resume,
    JobDescription $job,
    ResumeEvaluation $evaluation,
    ?string $jobUrlOverride = null,
    ?string $modelOverride = null
): array {
    $jobText = $this->resolveJobDescriptionText($job, $jobUrlOverride);
    $model = $modelOverride ?: config('resume_intelligence.analysis.model');
    $systemPrompt = config('resume_intelligence.analysis.system_prompt');

    $prompt = $this->buildAnalysisPrompt(
        $jobText,
        $resume->content_markdown,
        $this->jobSourceSummary($job, $jobUrlOverride)
    );

    $promptLog = $this->logPrompt(
        $evaluation->user_id,
        $evaluation,
        AiPrompt::CATEGORY_EVALUATION,
        $model,
        $systemPrompt,
        $prompt
    );

    $payload = $this->callOpenAI(
        $model,
        $systemPrompt,
        $prompt
    );

    // NEW: Parse JSON response
    $structured = $this->parseStructuredFeedback($payload);

    // NEW: Generate markdown from structured sections for backward compatibility
    $markdown = $this->generateMarkdownFromStructured($structured);

    return [
        'model' => $model,
        'content' => $markdown,
        'structured' => $structured,
        'system_prompt' => $systemPrompt,
        'prompt' => $prompt,
        'prompt_log' => $promptLog,
    ];
}
```

**Add new helper methods**:

```php
/**
 * Parse and validate structured JSON feedback from AI response
 */
private function parseStructuredFeedback(string $payload): array
{
    // Try to parse JSON
    $decoded = json_decode($payload, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        // Fallback: treat as plain markdown
        return [
            'sentiment' => 'good_match',
            'highlights' => null,
            'key_phrases' => [],
            'sections' => [
                'summary' => $payload,
                'relevant_experience' => null,
                'gaps' => null,
                'recommendations' => null,
            ],
        ];
    }

    // Validate required fields
    $structured = [
        'sentiment' => $decoded['sentiment'] ?? 'good_match',
        'highlights' => $decoded['highlights'] ?? null,
        'key_phrases' => $decoded['key_phrases'] ?? [],
        'sections' => [
            'summary' => $decoded['sections']['summary'] ?? '',
            'relevant_experience' => $decoded['sections']['relevant_experience'] ?? null,
            'gaps' => $decoded['sections']['gaps'] ?? null,
            'recommendations' => $decoded['sections']['recommendations'] ?? null,
        ],
    ];

    return $structured;
}

/**
 * Generate flattened markdown from structured sections
 */
private function generateMarkdownFromStructured(array $structured): string
{
    $sections = $structured['sections'] ?? [];

    $parts = array_filter([
        $sections['summary'] ?? null,
        $sections['relevant_experience'] ?? null,
        $sections['gaps'] ?? null,
        $sections['recommendations'] ?? null,
    ]);

    return implode("\n\n---\n\n", $parts);
}
```

### 3. Update Job Processing (`app/Jobs/ProcessResumeEvaluation.php`)

**Modify the job to store both formats**:

```php
public function handle(ResumeIntelligenceService $intelligence): void
{
    // ... existing code ...

    try {
        $result = $intelligence->evaluate(
            $resume,
            $job,
            $evaluation,
            $this->jobUrlOverride,
            $this->modelOverride
        );

        $evaluation->update([
            'status' => ResumeEvaluation::STATUS_COMPLETED,
            'model' => $result['model'],
            'feedback_markdown' => $result['content'],
            'feedback_structured' => $result['structured'], // NEW
            'completed_at' => now(),
        ]);

        // ... rest of existing code ...
    }
}
```

---

## Frontend Implementation

### 1. New Vue Components

#### `resources/js/components/EvaluationFeedbackCard.vue`

Main container component that orchestrates the split-panel layout:

```vue
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
    return gradients[sentiment.value as keyof typeof gradients] ?? gradients.good_match;
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
                summaryGradient
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
            :content="fallbackMarkdown ?? '_Feedback is still processing or unavailable._'"
        />
    </div>
</template>
```

#### `resources/js/components/SentimentBadge.vue`

Visual sentiment indicator with icon:

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    CheckCircle,
    ThumbsUp,
    Info,
    AlertTriangle,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    sentiment: string;
}>();

const config = computed(() => {
    const configs = {
        strong_match: {
            label: 'Strong Match',
            icon: CheckCircle,
            className: 'border-success/30 bg-success/10 text-success',
        },
        good_match: {
            label: 'Good Match',
            icon: ThumbsUp,
            className: 'border-accent/30 bg-accent/10 text-accent-foreground',
        },
        partial_match: {
            label: 'Partial Match',
            icon: Info,
            className: 'border-warning/30 bg-warning/10 text-warning',
        },
        weak_match: {
            label: 'Needs Improvement',
            icon: AlertTriangle,
            className: 'border-muted/60 bg-muted/40 text-muted-foreground',
        },
    };

    return configs[props.sentiment as keyof typeof configs] ?? configs.good_match;
});
</script>

<template>
    <Badge :class="config.className">
        <component :is="config.icon" class="mr-1 size-3" />
        {{ config.label }}
    </Badge>
</template>
```

#### `resources/js/components/HighlightStats.vue`

Quick stat cards for highlights:

```vue
<script setup lang="ts">
import { CheckCircle, Briefcase, Target } from 'lucide-vue-next';

defineProps<{
    highlights: {
        matching_skills?: number;
        relevant_years?: number;
        key_gaps?: number;
    };
}>();
</script>

<template>
    <div class="flex flex-wrap gap-3">
        <div
            v-if="highlights.matching_skills !== undefined"
            class="flex items-center gap-2 rounded-lg border border-border/50 bg-background/60 px-3 py-2"
        >
            <CheckCircle class="size-4 text-success" />
            <span class="text-sm font-medium text-foreground">
                {{ highlights.matching_skills }}
                {{ highlights.matching_skills === 1 ? 'skill' : 'skills' }} match
            </span>
        </div>

        <div
            v-if="highlights.relevant_years !== undefined"
            class="flex items-center gap-2 rounded-lg border border-border/50 bg-background/60 px-3 py-2"
        >
            <Briefcase class="size-4 text-primary" />
            <span class="text-sm font-medium text-foreground">
                {{ highlights.relevant_years }}
                {{ highlights.relevant_years === 1 ? 'year' : 'years' }} experience
            </span>
        </div>

        <div
            v-if="highlights.key_gaps !== undefined"
            class="flex items-center gap-2 rounded-lg border border-border/50 bg-background/60 px-3 py-2"
        >
            <Target class="size-4 text-warning" />
            <span class="text-sm font-medium text-foreground">
                {{ highlights.key_gaps }}
                {{ highlights.key_gaps === 1 ? 'area' : 'areas' }} to develop
            </span>
        </div>
    </div>
</template>
```

#### `resources/js/components/KeyPhrasesList.vue`

Display key phrases as pills/badges:

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{
    phrases: string[];
}>();
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Badge
            v-for="(phrase, index) in phrases"
            :key="index"
            variant="secondary"
            class="text-xs"
        >
            {{ phrase }}
        </Badge>
    </div>
</template>
```

### 2. Update `resources/js/pages/Jobs/Show.vue`

**Replace the feedback display section** (around lines 1460-1483):

**Current code:**
```vue
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-foreground">
            Feedback
        </h3>
        <span class="text-xs text-muted-foreground">
            {{ activeEvaluation.model || '—' }}
        </span>
    </div>
    <div class="rounded-xl border border-border/60 bg-background/80 p-4">
        <MarkdownViewer
            :content="
                activeEvaluation.feedback_markdown ||
                '_Feedback is still processing or unavailable._'
            "
        />
    </div>
</div>
```

**New code:**
```vue
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-foreground">
            Feedback
        </h3>
        <span class="text-xs text-muted-foreground">
            {{ activeEvaluation.model || '—' }}
        </span>
    </div>
    <EvaluationFeedbackCard
        :feedback-data="activeEvaluation.feedback_data"
        :fallback-markdown="activeEvaluation.feedback_markdown"
    />
</div>
```

**Add import:**
```vue
<script setup lang="ts">
import EvaluationFeedbackCard from '@/components/EvaluationFeedbackCard.vue';
// ... existing imports
</script>
```

**Update interface** (around line 61):
```typescript
interface Evaluation {
    id: number;
    status: string;
    headline?: string | null;
    model?: string | null;
    notes?: string | null;
    feedback_markdown?: string | null;
    feedback_data?: {
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
    } | null;
    error_message?: string | null;
    resume: {
        id?: number | null;
        title?: string | null;
        slug?: string | null;
    };
    completed_at?: string | null;
    created_at?: string | null;
}
```

### 3. Update API Response

**Modify evaluation controller/resource** to include `feedback_data`:

In `app/Http/Controllers/ResumeEvaluationController.php` or the relevant resource class:

```php
// Ensure the API returns feedback_data alongside feedback_markdown
return [
    'id' => $evaluation->id,
    'status' => $evaluation->status,
    // ... other fields
    'feedback_markdown' => $evaluation->feedback_markdown,
    'feedback_data' => $evaluation->feedback_data, // Uses the accessor
];
```

---

## Migration Steps

### 1. Create Migration File

```bash
php artisan make:migration add_feedback_structured_to_resume_evaluations_table
```

### 2. Migration Content

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_evaluations', function (Blueprint $table) {
            $table->json('feedback_structured')->nullable()->after('feedback_markdown');
        });
    }

    public function down(): void
    {
        Schema::table('resume_evaluations', function (Blueprint $table) {
            $table->dropColumn('feedback_structured');
        });
    }
};
```

### 3. Run Migration

```bash
php artisan migrate
```

---

## Testing Strategy

### Backend Testing

1. **Test JSON parsing with valid response**
   - AI returns properly formatted JSON
   - Verify structured data is stored correctly
   - Verify markdown is generated correctly

2. **Test fallback with malformed JSON**
   - AI returns invalid JSON
   - Verify fallback to treating response as plain markdown
   - Ensure no crashes or errors

3. **Test backward compatibility**
   - Old evaluations without `feedback_structured`
   - Verify `feedback_data` accessor returns sensible fallback
   - Verify UI renders correctly

### Frontend Testing

1. **Test structured feedback display**
   - New evaluations with structured data
   - Verify split-panel layout renders
   - Verify sentiment badge displays correctly
   - Verify highlights and key phrases render

2. **Test legacy feedback display**
   - Old evaluations without structured data
   - Verify fallback to MarkdownViewer
   - Verify no visual regressions

3. **Test responsive layout**
   - Desktop: split-panel side-by-side
   - Mobile: stacked vertically
   - Verify readability at all breakpoints

---

## Rollout Plan

### Phase 1: Backend Implementation
1. Create database migration
2. Update config/system prompt
3. Update ResumeIntelligenceService parsing
4. Update ProcessResumeEvaluation job
5. Add accessor to ResumeEvaluation model
6. Test with new evaluations

### Phase 2: Frontend Implementation
1. Create new Vue components (SentimentBadge, HighlightStats, KeyPhrasesList)
2. Create EvaluationFeedbackCard component
3. Update Jobs/Show.vue to use new component
4. Test with both structured and legacy data

### Phase 3: Testing & Refinement
1. Manual testing with various evaluation scenarios
2. Visual QA across devices
3. Performance testing
4. Gather user feedback

### Phase 4: Optional Enhancements
1. Add fuzzy section detection for legacy evaluations
2. Add animation/transitions between sentiment states
3. Add ability to expand/collapse sections
4. Add export/print-friendly view

---

## Success Metrics

After implementation, we should see:
- **Reduced time to understand evaluation** - Users can quickly scan sentiment, highlights, and key phrases
- **Improved visual hierarchy** - Clear separation of strengths vs. gaps
- **Better actionability** - Recommendations are prominent and clear
- **No regressions** - Legacy evaluations still display correctly

---

## Future Considerations

### Potential Enhancements
1. **Interactive filtering** - Toggle to show/hide specific sections
2. **Comparison view** - Side-by-side comparison of multiple evaluations
3. **Score visualization** - Charts/graphs for skill matching over time
4. **AI improvements** - Fine-tune sentiment classification based on user feedback
5. **Export options** - PDF export with visual layout preserved

### Alternative Approaches Discussed
- **Option A**: Card-based layout with visual indicators per section
- **Option B**: Progressive disclosure with collapsible sections
- **Option C**: Split-panel comparison (SELECTED)

---

## Appendix: Example AI Response

```json
{
  "sentiment": "good_match",
  "highlights": {
    "matching_skills": 7,
    "relevant_years": 5,
    "key_gaps": 2
  },
  "key_phrases": [
    "Strong React and TypeScript experience",
    "Missing AWS certification",
    "Excellent problem-solving demonstrated in portfolio",
    "Limited experience with serverless architecture"
  ],
  "sections": {
    "summary": "## Summary of Fit\n\nThe candidate presents a **good overall match** for this Senior Frontend Developer role. They bring 5 years of relevant experience with React, TypeScript, and modern frontend tooling, which aligns well with the core technical requirements. Their portfolio demonstrates strong problem-solving abilities and clean code practices.\n\nHowever, there are two notable gaps: the role requires AWS certification (not currently held) and experience with serverless architecture (minimal exposure). These are addressable but would require some ramp-up time.",

    "relevant_experience": "## Relevant Experience\n\n- **5 years of React development** with demonstrated expertise in hooks, context, and performance optimization\n- **Strong TypeScript skills** evident in type-safe component architecture and utility libraries\n- **Modern tooling experience** including Vite, testing libraries (Jest, React Testing Library), and CI/CD pipelines\n- **Component library development** showing ability to build reusable, well-documented UI systems\n- **Agile team collaboration** with experience in code reviews, pair programming, and sprint planning\n- **Performance optimization** background with documented improvements in Core Web Vitals\n- **Responsive design expertise** with mobile-first approach and accessibility considerations",

    "gaps": "## Gaps\n\n- **AWS Certification**: The role requires AWS Solutions Architect Associate or equivalent. Candidate has general cloud familiarity but no formal AWS certification.\n- **Serverless Architecture**: Limited hands-on experience with Lambda, API Gateway, or serverless patterns. Role involves building and maintaining serverless APIs.\n- **GraphQL**: Job mentions GraphQL as a plus; candidate has primarily REST API experience.\n- **Team leadership**: While technically strong, limited evidence of mentoring junior developers or leading technical initiatives (role has some leadership expectations).",

    "recommendations": "## Recommendations\n\n1. **Pursue AWS Certification** - Enroll in an AWS Solutions Architect Associate course and schedule the exam within 2-3 months. Highlight any existing cloud experience (even if not AWS) in your application.\n\n2. **Build serverless portfolio project** - Create a small serverless application using AWS Lambda and API Gateway to demonstrate hands-on capability. Document the architecture and learnings in your portfolio.\n\n3. **Emphasize problem-solving and learning agility** - In your cover letter and interviews, highlight examples of quickly learning new technologies and adapting to different architectures.\n\n4. **Prepare GraphQL talking points** - Review GraphQL fundamentals and be ready to discuss how your REST API experience translates to GraphQL concepts.\n\n5. **Frame leadership potential** - Even without formal leadership experience, discuss times you've mentored peers, led code reviews, or driven technical decisions within your team.\n\n6. **Apply confidently** - Despite the gaps, your strong technical foundation and demonstrated growth trajectory make you a viable candidate. The missing pieces are learnable, and your existing strengths align well with the core role requirements."
  }
}
```

---

## Document History

- **2025-11-20**: Initial design completed through brainstorming session
