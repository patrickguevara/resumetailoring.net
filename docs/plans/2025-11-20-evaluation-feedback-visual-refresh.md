# Evaluation Feedback Visual Refresh Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform text-heavy job evaluation feedback into a visually structured split-panel layout with sentiment-driven styling and quick-scan highlights.

**Architecture:** Backend will update AI system prompt to return structured JSON (sentiment, highlights, key_phrases, sections), parse and store both JSON and markdown formats for backward compatibility. Frontend will create new Vue components to render the structured data in a responsive split-panel layout with fallback to legacy markdown display.

**Tech Stack:** Laravel 11, Vue 3, TypeScript, Inertia.js, Tailwind CSS, OpenAI API

---

## Task 1: Database Migration for Structured Feedback

**Files:**
- Create: `database/migrations/2025_11_20_000001_add_feedback_structured_to_resume_evaluations_table.php`

**Step 1: Create migration file**

```bash
php artisan make:migration add_feedback_structured_to_resume_evaluations_table
```

Expected: Migration file created in `database/migrations/`

**Step 2: Write migration content**

In `database/migrations/2025_11_20_000001_add_feedback_structured_to_resume_evaluations_table.php`:

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

**Step 3: Run migration**

```bash
php artisan migrate
```

Expected: `Migration table updated successfully` with new `feedback_structured` column

**Step 4: Verify migration in database**

```bash
php artisan tinker
```

Then run:
```php
Schema::hasColumn('resume_evaluations', 'feedback_structured')
```

Expected: `true`

**Step 5: Commit**

```bash
git add database/migrations/2025_11_20_000001_add_feedback_structured_to_resume_evaluations_table.php
git commit -m "feat: add feedback_structured column to resume_evaluations table"
```

---

## Task 2: Update ResumeEvaluation Model

**Files:**
- Modify: `app/Models/ResumeEvaluation.php:21-36`

**Step 1: Add feedback_structured to fillable array**

In `app/Models/ResumeEvaluation.php`, update the `$fillable` array (line 21):

```php
protected $fillable = [
    'user_id',
    'resume_id',
    'job_description_id',
    'status',
    'model',
    'headline',
    'feedback_markdown',
    'feedback_structured',
    'notes',
    'error_message',
    'completed_at',
];
```

**Step 2: Add feedback_structured to casts array**

In `app/Models/ResumeEvaluation.php`, update the `$casts` array (line 34):

```php
protected $casts = [
    'completed_at' => 'datetime',
    'feedback_structured' => 'array',
];
```

**Step 3: Add accessor for smart fallback**

Add this method after the `aiPrompts()` method (after line 66):

```php
/**
 * Get feedback data with smart fallback for legacy evaluations.
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
        ],
    ];
}
```

**Step 4: Test accessor in tinker**

```bash
php artisan tinker
```

Then run:
```php
$eval = App\Models\ResumeEvaluation::first();
$eval->feedback_data;
```

Expected: Returns array with sentiment, highlights, key_phrases, sections (using fallback if no structured data)

**Step 5: Commit**

```bash
git add app/Models/ResumeEvaluation.php
git commit -m "feat: add feedback_structured field and accessor to ResumeEvaluation model"
```

---

## Task 3: Update AI System Prompt Configuration

**Files:**
- Modify: `config/resume_intelligence.php:8-19`

**Step 1: Update system prompt to request JSON response**

In `config/resume_intelligence.php`, replace the `analysis.system_prompt` content (lines 8-19):

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

**Step 2: Verify configuration loads**

```bash
php artisan tinker
```

Then run:
```php
config('resume_intelligence.analysis.system_prompt');
```

Expected: Returns the new prompt with JSON structure instructions

**Step 3: Commit**

```bash
git add config/resume_intelligence.php
git commit -m "feat: update AI system prompt to request structured JSON response"
```

---

## Task 4: Add JSON Parsing Methods to ResumeIntelligenceService

**Files:**
- Modify: `app/Services/ResumeIntelligenceService.php:14-367`

**Step 1: Add parseStructuredFeedback method**

Add this method after the `buildTailorPrompt` method (after line 365):

```php
/**
 * Parse and validate structured JSON feedback from AI response.
 *
 * @return array{sentiment: string, highlights: array|null, key_phrases: array, sections: array}
 */
private function parseStructuredFeedback(string $payload): array
{
    // Try to parse JSON
    $decoded = json_decode($payload, true);

    if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
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

    // Validate and normalize structure
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
```

**Step 2: Add generateMarkdownFromStructured method**

Add this method after `parseStructuredFeedback`:

```php
/**
 * Generate flattened markdown from structured sections for backward compatibility.
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

**Step 3: Update evaluate method to use new parsing**

In `app/Services/ResumeIntelligenceService.php`, update the `evaluate` method (lines 25-64) to parse and return structured data:

Replace the return statement (lines 57-63) with:

```php
// Parse JSON response into structured format
$structured = $this->parseStructuredFeedback($payload);

// Generate markdown from structured sections for backward compatibility
$markdown = $this->generateMarkdownFromStructured($structured);

return [
    'model' => $model,
    'content' => $markdown,
    'structured' => $structured,
    'system_prompt' => $systemPrompt,
    'prompt' => $prompt,
    'prompt_log' => $promptLog,
];
```

**Step 4: Test parsing with mock data**

```bash
php artisan tinker
```

Then run:
```php
$service = app(App\Services\ResumeIntelligenceService::class);
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('parseStructuredFeedback');
$method->setAccessible(true);

$jsonPayload = '{"sentiment":"strong_match","highlights":{"matching_skills":5,"relevant_years":3,"key_gaps":1},"key_phrases":["Great fit"],"sections":{"summary":"Test","relevant_experience":"Experience","gaps":"Gaps","recommendations":"Recs"}}';
$result = $method->invoke($service, $jsonPayload);
print_r($result);
```

Expected: Returns properly structured array with sentiment, highlights, key_phrases, sections

**Step 5: Commit**

```bash
git add app/Services/ResumeIntelligenceService.php
git commit -m "feat: add JSON parsing methods to ResumeIntelligenceService"
```

---

## Task 5: Update ProcessResumeEvaluation Job

**Files:**
- Modify: `app/Jobs/ProcessResumeEvaluation.php:58-80`

**Step 1: Update job to store structured feedback**

In `app/Jobs/ProcessResumeEvaluation.php`, update the evaluation update block (lines 73-80):

```php
$evaluation->forceFill([
    'status' => ResumeEvaluation::STATUS_COMPLETED,
    'model' => $result['model'],
    'feedback_markdown' => $result['content'],
    'feedback_structured' => $result['structured'],
    'headline' => (string) $headline ?: null,
    'completed_at' => now(),
    'error_message' => null,
])->save();
```

**Step 2: Test job processes structured data correctly**

Create a test file `tests/Unit/ProcessResumeEvaluationTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Jobs\ProcessResumeEvaluation;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Models\User;
use App\Services\ResumeIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessResumeEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_structured_feedback(): void
    {
        $user = User::factory()->create();
        $resume = Resume::factory()->create(['user_id' => $user->id]);
        $job = JobDescription::factory()->create(['user_id' => $user->id]);
        $evaluation = ResumeEvaluation::factory()->create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
        ]);

        $mockService = Mockery::mock(ResumeIntelligenceService::class);
        $mockService->shouldReceive('evaluate')
            ->once()
            ->andReturn([
                'model' => 'gpt-5-nano',
                'content' => '# Summary\n\nTest content',
                'structured' => [
                    'sentiment' => 'good_match',
                    'highlights' => ['matching_skills' => 5],
                    'key_phrases' => ['Great fit'],
                    'sections' => [
                        'summary' => '# Summary\n\nTest',
                        'relevant_experience' => null,
                        'gaps' => null,
                        'recommendations' => null,
                    ],
                ],
                'system_prompt' => 'test',
                'prompt' => 'test',
                'prompt_log' => null,
            ]);

        $this->app->instance(ResumeIntelligenceService::class, $mockService);

        $job = new ProcessResumeEvaluation($evaluation->id);
        $job->handle($mockService);

        $evaluation->refresh();

        $this->assertEquals('completed', $evaluation->status);
        $this->assertNotNull($evaluation->feedback_structured);
        $this->assertEquals('good_match', $evaluation->feedback_structured['sentiment']);
    }
}
```

**Step 3: Run test**

```bash
php artisan test tests/Unit/ProcessResumeEvaluationTest.php
```

Expected: Test passes

**Step 4: Commit**

```bash
git add app/Jobs/ProcessResumeEvaluation.php tests/Unit/ProcessResumeEvaluationTest.php
git commit -m "feat: update ProcessResumeEvaluation to store structured feedback"
```

---

## Task 6: Create SentimentBadge Vue Component

**Files:**
- Create: `resources/js/components/SentimentBadge.vue`

**Step 1: Create SentimentBadge component**

In `resources/js/components/SentimentBadge.vue`:

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

    return (
        configs[props.sentiment as keyof typeof configs] ?? configs.good_match
    );
});
</script>

<template>
    <Badge :class="config.className">
        <component :is="config.icon" class="mr-1 size-3" />
        {{ config.label }}
    </Badge>
</template>
```

**Step 2: Verify component syntax**

```bash
npm run build
```

Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/SentimentBadge.vue
git commit -m "feat: create SentimentBadge component for evaluation feedback"
```

---

## Task 7: Create HighlightStats Vue Component

**Files:**
- Create: `resources/js/components/HighlightStats.vue`

**Step 1: Create HighlightStats component**

In `resources/js/components/HighlightStats.vue`:

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
                {{
                    highlights.matching_skills === 1 ? 'skill' : 'skills'
                }}
                match
            </span>
        </div>

        <div
            v-if="highlights.relevant_years !== undefined"
            class="flex items-center gap-2 rounded-lg border border-border/50 bg-background/60 px-3 py-2"
        >
            <Briefcase class="size-4 text-primary" />
            <span class="text-sm font-medium text-foreground">
                {{ highlights.relevant_years }}
                {{ highlights.relevant_years === 1 ? 'year' : 'years' }}
                experience
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

**Step 2: Verify component syntax**

```bash
npm run build
```

Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/HighlightStats.vue
git commit -m "feat: create HighlightStats component for evaluation highlights"
```

---

## Task 8: Create KeyPhrasesList Vue Component

**Files:**
- Create: `resources/js/components/KeyPhrasesList.vue`

**Step 1: Create KeyPhrasesList component**

In `resources/js/components/KeyPhrasesList.vue`:

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

**Step 2: Verify component syntax**

```bash
npm run build
```

Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/KeyPhrasesList.vue
git commit -m "feat: create KeyPhrasesList component for evaluation key phrases"
```

---

## Task 9: Create EvaluationFeedbackCard Vue Component

**Files:**
- Create: `resources/js/components/EvaluationFeedbackCard.vue`

**Step 1: Create EvaluationFeedbackCard component**

In `resources/js/components/EvaluationFeedbackCard.vue`:

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
```

**Step 2: Verify component syntax**

```bash
npm run build
```

Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/EvaluationFeedbackCard.vue
git commit -m "feat: create EvaluationFeedbackCard component with split-panel layout"
```

---

## Task 10: Update Jobs/Show.vue to Use New Components

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue:2,61-76,1460-1483`

**Step 1: Add import for EvaluationFeedbackCard**

In `resources/js/pages/Jobs/Show.vue`, add the import after line 2:

```typescript
import EvaluationFeedbackCard from '@/components/EvaluationFeedbackCard.vue';
```

**Step 2: Update Evaluation interface to include feedback_data**

In `resources/js/pages/Jobs/Show.vue`, update the `Evaluation` interface (lines 61-76):

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

**Step 3: Replace feedback display section**

In `resources/js/pages/Jobs/Show.vue`, replace the feedback display (lines 1460-1483):

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

**Step 4: Build and verify no errors**

```bash
npm run build
```

Expected: Build completes without errors

**Step 5: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "feat: integrate EvaluationFeedbackCard into Jobs/Show page"
```

---

## Task 11: Update API Response to Include feedback_data

**Files:**
- Modify: `app/Http/Controllers/ResumeEvaluationController.php:1-57` (or relevant controller)

**Step 1: Find the evaluation API controller/resource**

```bash
grep -r "feedback_markdown" app/Http/Controllers app/Http/Resources
```

Expected: Shows which controller or resource serializes evaluation data

**Step 2: Update API response to include feedback_data**

If using a controller directly, ensure the response includes `feedback_data`:

```php
return response()->json([
    'id' => $evaluation->id,
    'status' => $evaluation->status,
    'headline' => $evaluation->headline,
    'model' => $evaluation->model,
    'notes' => $evaluation->notes,
    'feedback_markdown' => $evaluation->feedback_markdown,
    'feedback_data' => $evaluation->feedback_data, // Uses the accessor
    'error_message' => $evaluation->error_message,
    'completed_at' => $evaluation->completed_at,
    'created_at' => $evaluation->created_at,
    'resume' => [
        'id' => $evaluation->resume->id,
        'title' => $evaluation->resume->title,
        'slug' => $evaluation->resume->slug,
    ],
]);
```

Or if using an API Resource (`app/Http/Resources/EvaluationResource.php`):

```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'status' => $this->status,
        'headline' => $this->headline,
        'model' => $this->model,
        'notes' => $this->notes,
        'feedback_markdown' => $this->feedback_markdown,
        'feedback_data' => $this->feedback_data,
        'error_message' => $this->error_message,
        'completed_at' => $this->completed_at,
        'created_at' => $this->created_at,
        'resume' => [
            'id' => $this->resume->id,
            'title' => $this->resume->title,
            'slug' => $this->resume->slug,
        ],
    ];
}
```

**Step 3: Test API response**

```bash
php artisan tinker
```

Then:
```php
$eval = App\Models\ResumeEvaluation::first();
$controller = new App\Http\Controllers\ResumeEvaluationController();
// Or test via route if available
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/ResumeEvaluationController.php
# Or app/Http/Resources/EvaluationResource.php
git commit -m "feat: include feedback_data in evaluation API response"
```

---

## Task 12: Update Inertia Response in Jobs Show Controller

**Files:**
- Modify: `app/Http/Controllers/JobDescriptionController.php` (or wherever jobs.show route is handled)

**Step 1: Find the jobs.show controller method**

```bash
grep -r "jobs.show" app/Http/Controllers routes/
```

Expected: Shows which controller method handles the jobs.show route

**Step 2: Ensure evaluations include feedback_data in Inertia response**

In the controller method that returns the job show page, ensure evaluations are loaded with `feedback_data`:

```php
public function show(JobDescription $job)
{
    $job->load(['user', 'companyResearch']);

    $evaluations = $job->evaluations()
        ->with('resume:id,title,slug')
        ->latest()
        ->get()
        ->map(function ($evaluation) {
            return [
                'id' => $evaluation->id,
                'status' => $evaluation->status,
                'headline' => $evaluation->headline,
                'model' => $evaluation->model,
                'notes' => $evaluation->notes,
                'feedback_markdown' => $evaluation->feedback_markdown,
                'feedback_data' => $evaluation->feedback_data,
                'error_message' => $evaluation->error_message,
                'completed_at' => $evaluation->completed_at,
                'created_at' => $evaluation->created_at,
                'resume' => [
                    'id' => $evaluation->resume->id,
                    'title' => $evaluation->resume->title,
                    'slug' => $evaluation->resume->slug,
                ],
            ];
        });

    return inertia('Jobs/Show', [
        'job' => $job,
        'evaluations' => $evaluations,
        // ... other props
    ]);
}
```

**Step 3: Test in browser**

```bash
npm run dev
```

Visit a job page with evaluations and check browser console:
```javascript
console.log(this.$page.props.evaluations[0].feedback_data)
```

Expected: Shows feedback_data object with sentiment, highlights, etc.

**Step 4: Commit**

```bash
git add app/Http/Controllers/JobDescriptionController.php
git commit -m "feat: include feedback_data in job show Inertia response"
```

---

## Task 13: Manual Testing and Refinement

**Step 1: Create a test evaluation with structured data**

```bash
php artisan tinker
```

Then run:
```php
$user = App\Models\User::first();
$resume = App\Models\Resume::where('user_id', $user->id)->first();
$job = App\Models\JobDescription::where('user_id', $user->id)->first();

$evaluation = App\Models\ResumeEvaluation::create([
    'user_id' => $user->id,
    'resume_id' => $resume->id,
    'job_description_id' => $job->id,
    'status' => 'completed',
    'model' => 'gpt-5-nano',
    'feedback_markdown' => "# Summary\n\nGood match overall.",
    'feedback_structured' => [
        'sentiment' => 'good_match',
        'highlights' => [
            'matching_skills' => 7,
            'relevant_years' => 5,
            'key_gaps' => 2,
        ],
        'key_phrases' => [
            'Strong React experience',
            'Missing AWS certification',
        ],
        'sections' => [
            'summary' => "## Summary of Fit\n\nThe candidate is a good match.",
            'relevant_experience' => "## Relevant Experience\n\n- 5 years React\n- TypeScript expert",
            'gaps' => "## Gaps\n\n- No AWS certification\n- Limited cloud experience",
            'recommendations' => "## Recommendations\n\n1. Get AWS certified\n2. Build cloud projects",
        ],
    ],
    'completed_at' => now(),
]);
```

**Step 2: Visit job page in browser**

Navigate to `/jobs/{job_id}` and verify:
- Summary section displays with sentiment badge
- Highlights show (7 skills, 5 years, 2 gaps)
- Key phrases display as badges
- Split panel shows Experience and Gaps side-by-side
- Recommendations span full width
- Mobile view stacks vertically

**Step 3: Test legacy evaluation fallback**

Create or view an old evaluation without `feedback_structured`:
- Verify it displays with legacy MarkdownViewer
- Verify no errors in console

**Step 4: Test sentiment variations**

Update the test evaluation sentiment:
```php
$evaluation->update([
    'feedback_structured' => array_merge($evaluation->feedback_structured, [
        'sentiment' => 'strong_match',
    ]),
]);
```

Refresh page and verify:
- Summary gradient changes to success green
- Badge shows "Strong Match" with check icon

**Step 5: Document any visual refinements needed**

Create notes in `docs/plans/2025-11-20-refinements.md` for any tweaks to:
- Colors
- Spacing
- Typography
- Responsive breakpoints

---

## Task 14: Run Full Test Suite

**Step 1: Run PHP unit tests**

```bash
php artisan test
```

Expected: All tests pass

**Step 2: Run frontend build**

```bash
npm run build
```

Expected: Build completes successfully

**Step 3: Run linting**

```bash
npm run lint
```

Expected: No linting errors (or only warnings that are acceptable)

**Step 4: Verify no TypeScript errors**

```bash
npx vue-tsc --noEmit
```

Expected: No type errors

**Step 5: Commit any test fixes**

```bash
git add .
git commit -m "test: ensure all tests pass after evaluation feedback visual refresh"
```

---

## Task 15: Final Integration and Documentation

**Step 1: Update CHANGELOG or release notes**

If the project has a CHANGELOG.md, add entry:

```markdown
## [Unreleased]

### Added
- Structured JSON evaluation feedback with sentiment indicators
- Split-panel layout for evaluation feedback (Experience vs Gaps)
- Visual sentiment badges (Strong/Good/Partial/Weak Match)
- Quick-scan highlight statistics (matching skills, years experience, key gaps)
- Key phrase extraction for at-a-glance insights

### Changed
- AI system prompt now returns structured JSON instead of plain markdown
- Evaluation feedback display redesigned with gradient backgrounds
- Mobile view now stacks evaluation sections vertically

### Fixed
- Legacy evaluations continue to display correctly with fallback markdown viewer
```

**Step 2: Create pull request or merge to main**

```bash
git push origin feature/evaluation-feedback-visual-refresh
```

Then create PR with description:
- Link to design document
- Screenshots of before/after
- Testing checklist

**Step 3: Deploy to staging environment**

Follow project deployment process to staging.

**Step 4: Verify on staging**

- Test new evaluation creation
- Verify structured JSON is stored
- Test visual display on desktop and mobile
- Test legacy evaluations still work

**Step 5: Gather user feedback**

After deployed to production, monitor:
- User engagement with evaluations page
- Time spent on evaluation feedback
- User feedback/support tickets

---

## Rollback Plan

If issues arise in production:

**Step 1: Revert frontend changes**

```bash
git revert <commit-hash-of-jobs-show-update>
git push
```

This will restore legacy MarkdownViewer display.

**Step 2: System prompt can stay**

The updated system prompt is backward compatible - if frontend reverts, it will just display the markdown sections concatenated.

**Step 3: Database column is safe**

The `feedback_structured` column being nullable means old code will continue to work.

---

## Success Criteria

- ✅ New evaluations store both JSON and markdown
- ✅ Sentiment badge displays correctly (4 variants)
- ✅ Highlights display correct numbers
- ✅ Key phrases render as badges
- ✅ Split-panel layout on desktop (50/50)
- ✅ Stacked layout on mobile
- ✅ Legacy evaluations display with fallback
- ✅ No JavaScript console errors
- ✅ No PHP errors in logs
- ✅ All tests passing

---

## Post-Implementation Enhancements

Future improvements to consider:

1. **Fuzzy section detection** - Parse legacy markdown to extract sections intelligently
2. **Animation transitions** - Smooth fade-in for sentiment changes
3. **Export to PDF** - Print-friendly evaluation report with visual layout
4. **Comparison view** - Side-by-side comparison of multiple evaluations
5. **Score trending** - Chart showing match quality over time for a candidate
6. **AI refinement** - Fine-tune sentiment classification based on user feedback

---

## References

- Design Document: `docs/plans/2025-11-20-evaluation-feedback-visual-refresh-design.md`
- OpenAI Responses API: https://platform.openai.com/docs/api-reference/responses
- Vue 3 Composition API: https://vuejs.org/guide/extras/composition-api-faq.html
- Tailwind Gradients: https://tailwindcss.com/docs/gradient-color-stops
