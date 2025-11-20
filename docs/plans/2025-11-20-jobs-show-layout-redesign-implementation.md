# Jobs/Show Page Layout Redesign - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Restructure Jobs/Show page from two-column sidebar layout to single-column focus layout that prioritizes evaluation workflow and collapses secondary features.

**Architecture:** Remove sidebar grid layout, create single-column container (max-w-4xl), reorder sections to match user workflow, add collapsible evaluation history component, and make secondary sections (research, description) collapsible by default.

**Tech Stack:** Vue 3 Composition API, Inertia.js, TailwindCSS, TypeScript, Laravel backend (no changes needed)

---

## Context: Current Structure

**File:** `resources/js/pages/Jobs/Show.vue` (2038 lines)

**Current Layout (line 1179):**
```vue
<div class="grid gap-8 lg:grid-cols-[minmax(0,1fr),minmax(320px,360px)]">
```

This creates a two-column grid: main content (left) and sidebar (right) containing company research and evaluation history.

**Current Section Order:**
1. Job Overview Header (sticky)
2. Main Column:
   - Run New Evaluation (collapsible - recently added)
   - Evaluation Details (active evaluation)
   - Job Description (always expanded)
3. Sidebar (right):
   - Company Research (collapsible with showCompanyResearch ref)
   - Evaluation History (clickable list)

**Key State Variables (lines 511-521):**
- `showCompanyResearch = ref(true)` - Controls company research visibility
- `showEvaluationForm = ref(false)` - Controls evaluation form visibility
- `activeEvaluationId = ref<number | null>()` - Tracks selected evaluation
- `evaluations = ref<Evaluation[]>()` - All evaluations for this job

---

## Task 1: Restructure Layout to Single Column

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue:1179`

**Goal:** Remove two-column grid layout and create single-column container

**Step 1: Locate the layout container**

Find the line that creates the two-column grid (should be around line 1179):

```vue
<div class="grid gap-8 lg:grid-cols-[minmax(0,1fr),minmax(320px,360px)]">
```

**Step 2: Replace with single-column container**

Replace the grid container with:

```vue
<div class="mx-auto w-full max-w-4xl space-y-6">
```

**Why:** `max-w-4xl` = 1024px optimized for reading, `space-y-6` = consistent vertical spacing, `mx-auto` = centered

**Step 3: Remove section/aside wrappers**

The current structure has:
```vue
<div class="grid...">
    <section class="space-y-6">
        <!-- main content -->
    </section>
    <aside class="space-y-6">
        <!-- sidebar content -->
    </aside>
</div>
```

Change to flat structure:
```vue
<div class="mx-auto w-full max-w-4xl space-y-6">
    <!-- all sections here at same level -->
</div>
```

**Step 4: Verify build**

Run: `npm run build`
Expected: Build succeeds with no errors

**Step 5: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "refactor: convert Jobs/Show layout from two-column to single-column

- Remove lg:grid-cols sidebar layout
- Change container to max-w-4xl centered single column
- Remove section/aside wrappers for flat structure"
```

---

## Task 2: Add New State for Collapsible Sections

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue:511-521`

**Goal:** Add reactive state for new collapsible sections

**Step 1: Locate existing state declarations**

Find the state section around line 511-521:

```typescript
const showCompanyResearch = ref(true);
const companyResearchProcessing = ref(false);
const showEvaluationForm = ref(false);
```

**Step 2: Add new state variables**

Add these new state declarations after the existing ones:

```typescript
const showEvaluationHistory = ref(false); // collapsed by default
const showJobDescription = ref(false); // collapsed by default
```

**Why:**
- `showEvaluationHistory`: Controls visibility of evaluation history list
- `showJobDescription`: Job description now collapsed by default per design
- `showCompanyResearch` already exists but defaults to `true`, we'll change default later

**Step 3: Update showCompanyResearch default**

Change line 511 from:
```typescript
const showCompanyResearch = ref(true);
```

To:
```typescript
const showCompanyResearch = ref(false); // collapsed by default per redesign
```

**Step 4: Verify TypeScript compilation**

Run: `npm run build`
Expected: Build succeeds, TypeScript validates new refs

**Step 5: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "feat: add state for collapsible sections in layout redesign

- Add showEvaluationHistory ref (collapsed by default)
- Add showJobDescription ref (collapsed by default)
- Change showCompanyResearch default to false"
```

---

## Task 3: Create Active Evaluation Computed Property

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue:595-600`

**Goal:** Ensure activeEvaluation computed exists (it already does, we just verify)

**Step 1: Locate the computed property**

The computed already exists at line 595-600:

```typescript
const activeEvaluation = computed(
    () =>
        evaluations.value.find(
            (evaluation) => evaluation.id === activeEvaluationId.value,
        ) ?? null,
);
```

**Step 2: Verify it matches design spec**

Design document specifies (line 524-530):
```typescript
const activeEvaluation = computed(() => {
    return evaluations.value.find(e => e.id === activeEvaluationId.value)
        ?? evaluations.value[0]
        ?? null;
});
```

Current implementation doesn't fall back to `evaluations.value[0]` - this is handled elsewhere.

**Step 3: Decide if fallback needed**

Check line 519-576 for activeEvaluationId initialization and watch logic.

Current logic (lines 554-578) already ensures activeEvaluationId gets set to first evaluation if none selected:
```typescript
watch(
    () => evaluations.value.map((evaluation) => evaluation.id),
    (evaluationIds) => {
        // ...
        if (
            activeEvaluationId.value === null ||
            !evaluationIds.includes(activeEvaluationId.value)
        ) {
            activeEvaluationId.value = evaluationIds[0] ?? null;
        }
    },
    { immediate: true },
);
```

**Conclusion:** No changes needed, existing computed is sufficient.

**Step 4: Skip commit**

No changes made, move to next task.

---

## Task 4: Add switchToEvaluation Handler

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue` (add after activeEvaluation computed ~line 600)

**Goal:** Create handler that switches evaluation and scrolls to results section

**Step 1: Locate where to add handler**

After the `activeEvaluation` computed (line 600), before `activeEvaluationTailored` computed.

**Step 2: Add switchToEvaluation function**

Add this function:

```typescript
const switchToEvaluation = (evaluationId: number) => {
    activeEvaluationId.value = evaluationId;

    // Scroll to results section with smooth animation
    nextTick(() => {
        const resultsElement = document.getElementById('evaluation-results');
        if (resultsElement) {
            resultsElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};
```

**Step 3: Add nextTick import**

At top of script (around line 29-36), find the imports from 'vue':

```typescript
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
```

Add `nextTick` to the imports:

```typescript
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
```

**Why:** `nextTick` ensures DOM has updated with new evaluation before scrolling

**Step 4: Verify build**

Run: `npm run build`
Expected: Build succeeds, no TypeScript errors

**Step 5: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "feat: add switchToEvaluation handler with scroll behavior

- Add switchToEvaluation function to change active evaluation and scroll to results
- Import nextTick from vue for DOM update timing
- Implements smooth scroll to evaluation-results section"
```

---

## Task 5: Reorder Sections - Move Latest Evaluation Results

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue:1182-1685`

**Goal:** Create "Latest Evaluation Results" section right after "Run New Evaluation"

**Current Section Structure:**
1. Run New Evaluation (#run-evaluation) - lines 1182-1363
2. Evaluation Detail (#evaluation-details) - lines 1365-1685
3. Job Description (#job-description) - lines 1687-1710

**New Section Order:**
1. Run New Evaluation (keep as-is)
2. Latest Evaluation Results (rename & enhance #evaluation-details)
3. Evaluation History (will add in next task)
4. Company Research (will move from sidebar)
5. Job Description (will add collapse)

**Step 1: Locate Evaluation Detail section**

Find the section starting at line 1365:

```vue
<div
    id="evaluation-details"
    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
>
```

**Step 2: Change section ID to evaluation-results**

Change the id:

```vue
<div
    id="evaluation-results"
    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
>
```

**Why:** Design spec uses "evaluation-results" as the section ID for scroll targeting

**Step 3: Update header text**

Find the header (line 1369-1381):

```vue
<header
    class="flex flex-wrap items-center justify-between gap-3"
>
    <div>
        <h2
            class="text-lg font-semibold text-foreground"
        >
            Evaluation detail
        </h2>
        <p class="text-sm text-muted-foreground">
            Inspect the selected evaluation's feedback
            and tailored outputs.
        </p>
    </div>
```

Change to:

```vue
<header
    class="flex flex-wrap items-center justify-between gap-3"
>
    <div>
        <h2
            class="text-lg font-semibold text-foreground"
        >
            Latest evaluation results
        </h2>
        <p class="text-sm text-muted-foreground">
            Detailed feedback for the selected evaluation.
        </p>
    </div>
```

**Step 4: Update quick action scroll target**

Find the quick action card that scrolls to evaluations (line 1065):

```vue
@click="scrollToSection('evaluation-details')"
```

Change to:

```vue
@click="scrollToSection('evaluation-results')"
```

**Step 5: Verify build**

Run: `npm run build`
Expected: Build succeeds

**Step 6: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "refactor: rename Evaluation Detail to Latest Evaluation Results

- Change section ID from evaluation-details to evaluation-results
- Update header title and description
- Update quick action scroll target"
```

---

## Task 6: Build Evaluation History Component Section

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue` (add after Latest Evaluation Results section, ~line 1685)

**Goal:** Create new collapsible Evaluation History section below Latest Results

**Current State:** Evaluation History is in sidebar (lines 1931-2033). We need to move it to main column and make it collapsible.

**Step 1: Locate where to insert new section**

After the closing `</div>` of "Latest Evaluation Results" section (around line 1685), before Job Description section (line 1687).

**Step 2: Copy evaluation history content from sidebar**

Find the evaluation history sidebar section (lines 1931-2033):

```vue
<div
    id="evaluation-history"
    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
>
    <header class="flex items-center justify-between gap-3">
        <!-- ... header content ... -->
    </header>

    <div class="mt-4 space-y-3">
        <!-- ... evaluation list ... -->
    </div>
</div>
```

**Step 3: Add collapsible header with chevron**

Replace the header with collapsible pattern:

```vue
<div
    id="evaluation-history"
    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
>
    <header
        class="flex items-center justify-between gap-3 cursor-pointer hover:bg-muted/20 transition rounded-lg -m-2 p-2"
        @click="showEvaluationHistory = !showEvaluationHistory"
    >
        <div>
            <h2 class="text-lg font-semibold text-foreground">
                Past evaluations ({{ evaluations.length }})
            </h2>
            <p v-if="!showEvaluationHistory" class="text-sm text-muted-foreground">
                Click to view evaluation history
            </p>
        </div>
        <ChevronDown
            class="size-5 text-muted-foreground transition-transform duration-200"
            :class="showEvaluationHistory ? 'rotate-180' : ''"
        />
    </header>

    <div v-if="showEvaluationHistory" class="mt-4 space-y-3">
        <!-- existing evaluation list content -->
    </div>
</div>
```

**Step 4: Update evaluation click handlers**

In the evaluation list buttons (around line 1966), change:

```vue
@click="activeEvaluationId = evaluation.id"
```

To:

```vue
@click="switchToEvaluation(evaluation.id)"
```

**Why:** Uses the new handler that scrolls to results section

**Step 5: Add active indicator to evaluation rows**

Update each evaluation button to show active state with circle indicator:

```vue
<button
    v-for="evaluation in evaluations"
    :key="evaluation.id"
    type="button"
    :class="[
        'w-full rounded-xl border p-4 text-left transition',
        activeEvaluationId === evaluation.id
            ? 'border-primary bg-primary/10 shadow-sm'
            : 'border-border/60 bg-background/70 hover:border-primary/60 hover:bg-primary/5',
    ]"
    @click="switchToEvaluation(evaluation.id)"
>
    <div class="flex items-start gap-3">
        <!-- Active indicator circle -->
        <div class="flex-shrink-0 mt-1">
            <div
                :class="[
                    'size-2 rounded-full',
                    activeEvaluationId === evaluation.id
                        ? 'bg-primary'
                        : 'bg-border'
                ]"
            />
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1 min-w-0">
                    <p class="text-sm font-semibold text-foreground">
                        {{ evaluation.resume.title || 'Resume' }}
                    </p>
                    <p
                        v-if="evaluation.headline"
                        class="text-xs text-muted-foreground"
                    >
                        {{ evaluation.headline }}
                    </p>
                </div>
                <Badge
                    :class="evaluationStatusClass(evaluation.status)"
                >
                    <Loader2
                        v-if="evaluation.status === 'pending'"
                        class="mr-1 size-3 animate-spin"
                    />
                    {{ evaluationStatusLabel(evaluation.status) }}
                </Badge>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span>{{ evaluation.model || '—' }}</span>
                <span>•</span>
                <span>
                    {{ formatDateTime(evaluation.completed_at) || 'Pending' }}
                </span>
            </div>
        </div>
    </div>
</button>
```

**Step 6: Remove old sidebar evaluation history**

Delete the entire evaluation history section from the sidebar (lines 1931-2033).

**Step 7: Verify build**

Run: `npm run build`
Expected: Build succeeds

**Step 8: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "feat: create collapsible Evaluation History section below results

- Move evaluation history from sidebar to main column
- Add collapsible header with chevron icon
- Add active indicator circle to evaluation rows
- Update click handlers to use switchToEvaluation
- Show evaluation count in header"
```

---

## Task 7: Move Company Research Section and Make Collapsible by Default

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue:1713-1929`

**Goal:** Move Company Research from sidebar to main column, ensure collapsed by default

**Current Location:** Sidebar (lines 1713-1929)

**New Location:** After Evaluation History section, before Job Description

**Step 1: Verify showCompanyResearch defaults to false**

Check line 511 - we already changed this in Task 2:

```typescript
const showCompanyResearch = ref(false); // collapsed by default per redesign
```

**Step 2: Locate Company Research section**

Find the section starting at line 1713:

```vue
<div
    id="company-research"
    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
>
```

**Step 3: Update header styling to match collapsible pattern**

The header already has collapse functionality (lines 1718-1750), but update styling to match design:

```vue
<header
    class="cursor-pointer hover:bg-muted/20 transition rounded-lg -m-2 p-2"
    @click="showCompanyResearch = !showCompanyResearch"
>
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-foreground">
                Company research
            </h2>
            <p v-if="!showCompanyResearch" class="text-sm text-muted-foreground">
                Generate a briefing to prepare for interviews
            </p>
        </div>
        <ChevronDown
            class="size-5 text-muted-foreground transition-transform duration-200"
            :class="showCompanyResearch ? 'rotate-180' : ''"
        />
    </div>
</header>
```

**Step 4: Remove the old toggle button approach**

Current implementation has a separate Button component (lines 1731-1750). Replace entire header with the new pattern above.

**Step 5: Update border for secondary priority**

Change border class to indicate secondary:

```vue
<div
    id="company-research"
    class="rounded-2xl border border-border/50 bg-card/80 p-6 shadow-sm"
>
```

**Note:** `border-border/50` instead of `border-border/60` per design spec

**Step 6: Cut and paste section to new location**

1. Cut the entire company-research div (lines 1713-1929)
2. Paste it after the evaluation-history section closing tag
3. Before the job-description section

**Step 7: Verify build**

Run: `npm run build`
Expected: Build succeeds

**Step 8: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "refactor: move Company Research to main column, collapsed by default

- Move company-research section from sidebar to after evaluation-history
- Update header to collapsible pattern with hover effect
- Change border to border-border/50 for secondary priority
- Defaults to collapsed (showCompanyResearch = false)"
```

---

## Task 8: Make Job Description Collapsible

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue:1687-1710`

**Goal:** Add collapse/expand functionality to Job Description section

**Current State:** Job Description is always expanded with static header

**Step 1: Locate Job Description section**

Find the section starting around line 1687:

```vue
<div
    id="job-description"
    class="rounded-2xl border border-border/60 bg-card/80 p-6 shadow-sm"
>
    <header class="mb-4">
        <h2 class="text-lg font-semibold text-foreground">
            Job description
        </h2>
        <p class="text-sm text-muted-foreground">
            Reference the source description that current
            evaluations use.
        </p>
    </header>
    <div
        class="rounded-xl border border-border/60 bg-background/80 p-4"
    >
        <MarkdownViewer
            :content="
                job.description_markdown ??
                '*No description stored yet.*'
            "
        />
    </div>
</div>
```

**Step 2: Update header to collapsible pattern**

Replace header and content with:

```vue
<div
    id="job-description"
    class="rounded-2xl border border-border/50 bg-card/80 p-6 shadow-sm"
>
    <header
        class="cursor-pointer hover:bg-muted/20 transition rounded-lg -m-2 p-2"
        @click="showJobDescription = !showJobDescription"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-foreground">
                    Job description
                </h2>
                <p v-if="!showJobDescription" class="text-sm text-muted-foreground">
                    Click to view full job description
                </p>
            </div>
            <ChevronDown
                class="size-5 text-muted-foreground transition-transform duration-200"
                :class="showJobDescription ? 'rotate-180' : ''"
            />
        </div>
    </header>

    <div v-if="showJobDescription" class="mt-4">
        <div class="rounded-xl border border-border/60 bg-background/80 p-4">
            <MarkdownViewer
                :content="
                    job.description_markdown ??
                    '*No description stored yet.*'
                "
            />
        </div>
    </div>
</div>
```

**Note:** Changed border to `border-border/50` for secondary priority

**Step 3: Verify build**

Run: `npm run build`
Expected: Build succeeds

**Step 4: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "feat: make Job Description section collapsible

- Add collapsible header with chevron icon
- Wrap content in v-if=\"showJobDescription\"
- Change border to border-border/50 for secondary priority
- Defaults to collapsed per redesign"
```

---

## Task 9: Remove Empty Sidebar and Cleanup

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue`

**Goal:** Remove now-empty sidebar `<aside>` element and clean up structure

**Step 1: Verify sidebar is empty**

Check if the `<aside class="space-y-6">` section (originally around line 1713) is now empty after moving company-research and evaluation-history.

**Step 2: Remove sidebar element**

Delete the empty `<aside>` tags:

```vue
<aside class="space-y-6">
    <!-- This should now be empty -->
</aside>
```

**Step 3: Verify all sections are in main container**

Confirm structure is now:

```vue
<div class="mx-auto w-full max-w-4xl space-y-6">
    <!-- Run New Evaluation -->
    <!-- Latest Evaluation Results -->
    <!-- Evaluation History -->
    <!-- Company Research -->
    <!-- Job Description -->
</div>
```

**Step 4: Verify build**

Run: `npm run build`
Expected: Build succeeds

**Step 5: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "refactor: remove empty sidebar element

- Delete empty aside tag after moving all content to main column
- Confirm all sections in single-column container"
```

---

## Task 10: Add Empty State to Latest Evaluation Results

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue` (Latest Evaluation Results section)

**Goal:** Show friendly empty state when no evaluations exist

**Current State:** Shows "Select an evaluation from the history panel" when activeEvaluation is null (line 1678-1684)

**Design Requirement:** Show centered empty state with icon and call-to-action

**Step 1: Locate empty state block**

Find the empty state around line 1678:

```vue
<div
    v-else
    class="rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
>
    Select an evaluation from the history panel to view
    details.
</div>
```

**Step 2: Check if evaluations exist**

This empty state should only show when `evaluations.length === 0`, not when an evaluation just isn't selected.

Add computed property to check (after hasEvaluations computed, around line 593):

```typescript
const hasNoEvaluations = computed(() => evaluations.value.length === 0);
```

**Step 3: Update conditional logic**

Change the `v-else` to be more specific:

```vue
<div v-if="activeEvaluation" class="mt-4 space-y-6">
    <!-- existing evaluation details -->
</div>
<div
    v-else-if="hasNoEvaluations"
    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/60 bg-background/80 p-12 text-center"
>
    <Sparkles class="size-12 text-muted-foreground/40 mb-4" />
    <h3 class="text-lg font-semibold text-foreground mb-2">
        No evaluations yet
    </h3>
    <p class="text-sm text-muted-foreground max-w-sm">
        Click "New Evaluation" above to analyze how your resume matches this job.
    </p>
</div>
<div
    v-else
    class="rounded-xl border border-dashed border-border/60 bg-background/80 p-6 text-sm text-muted-foreground"
>
    Select an evaluation from the history below to view details.
</div>
```

**Step 4: Verify build**

Run: `npm run build`
Expected: Build succeeds

**Step 5: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "feat: add empty state to Latest Evaluation Results

- Add hasNoEvaluations computed property
- Show centered empty state with icon when no evaluations exist
- Update message when evaluation exists but not selected"
```

---

## Task 11: Update Section Spacing and Polish

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue`

**Goal:** Ensure consistent spacing and transitions across all sections

**Step 1: Verify space-y-6 on main container**

Check that main container has `space-y-6` (from Task 1):

```vue
<div class="mx-auto w-full max-w-4xl space-y-6">
```

**Step 2: Add transition classes to collapsible content**

For each collapsible section's content div, add transition:

Evaluation History:
```vue
<div
    v-if="showEvaluationHistory"
    class="mt-4 space-y-3 transition-all duration-200"
>
```

Company Research:
```vue
<div
    v-if="showCompanyResearch"
    class="mt-4 space-y-6 transition-all duration-200"
>
```

Job Description:
```vue
<div
    v-if="showJobDescription"
    class="mt-4 transition-all duration-200"
>
```

**Step 3: Verify all headers use consistent hover pattern**

All collapsible headers should have:

```vue
class="cursor-pointer hover:bg-muted/20 transition rounded-lg -m-2 p-2"
```

**Step 4: Verify build**

Run: `npm run build`
Expected: Build succeeds with smooth transitions

**Step 5: Test in browser**

Manual test checklist:
- [ ] All collapsible sections expand/collapse smoothly
- [ ] Chevron icons rotate on toggle
- [ ] Hover states work on headers
- [ ] Active evaluation has visual indicator
- [ ] Clicking evaluation in history scrolls to results
- [ ] No horizontal scrollbar (single column responsive)

**Step 6: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "polish: add transitions and consistent spacing to all sections

- Add transition-all duration-200 to collapsible content
- Verify consistent hover states on all headers
- Confirm space-y-6 between major sections"
```

---

## Task 12: Update Quick Action Cards Scroll Targets

**Files:**
- Modify: `resources/js/pages/Jobs/Show.vue:1032-1175`

**Goal:** Ensure quick action cards scroll to correct sections in new layout

**Step 1: Locate quick action cards**

Find the 4 quick action cards around lines 1032-1175:

1. Evaluations - scrolls to 'run-evaluation'
2. Tailored resumes - scrolls to 'evaluation-details' (now 'evaluation-results')
3. Company research - scrolls to 'company-research'
4. Job description - scrolls to 'job-description'

**Step 2: Verify scroll targets match new IDs**

Card 1 (Evaluations) - line 1036:
```vue
@click="scrollToSection('run-evaluation')"
```
✓ Correct - section still has id="run-evaluation"

Card 2 (Tailored resumes) - line 1065:
```vue
@click="scrollToSection('evaluation-details')"
```
❌ Update to 'evaluation-results' (we changed this in Task 5)

Card 3 (Company research) - line 1099:
```vue
@click="scrollToSection('company-research')"
```
✓ Correct - section still has id="company-research"

Card 4 (Job description) - line 1149:
```vue
@click="scrollToSection('job-description')"
```
✓ Correct - section still has id="job-description"

**Step 3: Fix tailored resumes scroll target**

We already fixed this in Task 5, verify it's correct:

```vue
@click="scrollToSection('evaluation-results')"
```

**Step 4: Add expand behavior to action cards**

When clicking company research or job description cards, also expand those sections:

Company research card (line 1099):
```vue
@click="scrollToSection('company-research'); showCompanyResearch = true"
```

Job description card (line 1149):
```vue
@click="scrollToSection('job-description'); showJobDescription = true"
```

**Step 5: Verify build**

Run: `npm run build`
Expected: Build succeeds

**Step 6: Commit**

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "fix: update quick action cards to expand sections on click

- Add expand behavior to company-research card
- Add expand behavior to job-description card
- Verify all scroll targets match new section IDs"
```

---

## Task 13: Visual QA and Responsive Testing

**Goal:** Verify layout works across all screen sizes and edge cases

**Step 1: Test desktop layout (≥1024px)**

Browser test at 1440px width:
- [ ] Single column centered with max-w-4xl
- [ ] Sections have consistent spacing (space-y-6)
- [ ] Sticky header remains at top
- [ ] All collapsible sections expand/collapse smoothly
- [ ] Active evaluation shows indicator circle
- [ ] Clicking evaluation scrolls to results section

**Step 2: Test tablet layout (768px - 1023px)**

Browser test at 834px width (iPad):
- [ ] Single column still centered
- [ ] Touch targets are at least 44px (headers, buttons)
- [ ] No horizontal overflow
- [ ] Chevron icons are easily tappable

**Step 3: Test mobile layout (<768px)**

Browser test at 375px width (iPhone):
- [ ] Sections stack naturally
- [ ] Quick action cards are responsive (grid may stack)
- [ ] Evaluation history rows wrap content
- [ ] Forms are usable without zoom
- [ ] No pinch-to-zoom required

**Step 4: Test edge cases**

- [ ] Empty state shows when no evaluations
- [ ] Loading state shows when evaluation is pending
- [ ] Error state shows when evaluation fails
- [ ] Long resume names wrap correctly in history
- [ ] Long company names don't break layout

**Step 5: Document any issues**

If issues found, create follow-up tasks. Otherwise proceed.

**Step 6: No commit needed**

This is a testing task.

---

## Task 14: Final Build and Verification

**Goal:** Ensure production build succeeds and all functionality works

**Step 1: Clean build**

Run: `npm run build`
Expected: Build succeeds with no errors or warnings

**Step 2: Check bundle size**

Compare bundle size before/after:

Run: `ls -lh public/build/assets/*.js`

Expected: Similar or smaller size (removed unused sidebar grid logic)

**Step 3: Test in production mode**

If using Laravel Mix/Vite:

Run: `npm run prod`
Expected: Minified production build succeeds

**Step 4: Verify TypeScript types**

Run: `npx vue-tsc --noEmit`
Expected: No type errors

**Step 5: Test key workflows**

Final user workflow test:
1. Load Jobs/Show page
2. Click "New Evaluation" - form expands
3. Submit evaluation - form collapses, evaluation appears
4. Click evaluation in history - scrolls to results, shows indicator
5. Click "Company Research" card - section expands and scrolls
6. Click "Job Description" card - section expands and scrolls
7. Toggle all sections - smooth transitions

**Step 6: Commit if fixes needed**

If any issues found and fixed, commit:

```bash
git add resources/js/pages/Jobs/Show.vue
git commit -m "fix: resolve issues found in final verification

- List specific fixes here"
```

---

## Success Criteria

**Layout Structure:**
- ✓ Two-column grid removed
- ✓ Single column (max-w-4xl) centered layout
- ✓ No sidebar element remains
- ✓ All sections in main container

**Section Order:**
1. ✓ Job Overview Header (sticky)
2. ✓ Run New Evaluation (collapsible, collapsed by default)
3. ✓ Latest Evaluation Results (expanded, shows active evaluation)
4. ✓ Evaluation History (collapsible, collapsed by default)
5. ✓ Company Research (collapsible, collapsed by default)
6. ✓ Job Description (collapsible, collapsed by default)

**Functionality:**
- ✓ Clicking evaluation in history switches active evaluation and scrolls to results
- ✓ Active evaluation shows visual indicator (circle)
- ✓ All collapsible sections expand/collapse with chevron rotation
- ✓ Quick action cards expand sections and scroll to them
- ✓ Empty state shows when no evaluations exist
- ✓ Smooth transitions on all expand/collapse actions

**State Management:**
- ✓ `showEvaluationHistory = ref(false)` added
- ✓ `showJobDescription = ref(false)` added
- ✓ `showCompanyResearch` default changed to `false`
- ✓ `switchToEvaluation` handler added
- ✓ All state properly reactive

**Responsive:**
- ✓ Works on desktop (≥1024px)
- ✓ Works on tablet (768px - 1023px)
- ✓ Works on mobile (<768px)
- ✓ No horizontal scrollbars
- ✓ Touch-friendly tap targets

---

## Rollback Plan

If critical issues arise post-deployment:

**Step 1: Revert commit**

Find the merge commit or last commit from this plan:

```bash
git log --oneline -20
```

Revert to commit before layout changes:

```bash
git revert <commit-hash> --no-commit
```

**Step 2: Quick fix alternative**

If only minor issue, quick fix:

Restore two-column layout temporarily:

```vue
<div class="grid gap-8 lg:grid-cols-[minmax(0,1fr),minmax(320px,360px)]">
    <section class="space-y-6">
        <!-- main content -->
    </section>
    <aside class="space-y-6">
        <!-- restore sidebar -->
    </aside>
</div>
```

**Step 3: Deploy hotfix**

```bash
npm run build
git add .
git commit -m "hotfix: revert to two-column layout temporarily"
git push
```

---

## Future Enhancements (Not in Scope)

These were identified in design doc but NOT implemented in this plan:

1. **Evaluation comparison mode** - Side-by-side view of 2 evaluations
2. **History filters** - Filter by resume, model, sentiment, date
3. **History sorting** - Sort by date, sentiment, name
4. **Bulk actions** - Select multiple evaluations for batch operations
5. **Pinned evaluations** - Pin favorite evaluation to top
6. **Evaluation notes** - Add private notes to evaluations
7. **Share evaluations** - Generate shareable links
8. **Print view** - Optimized print stylesheet

---

## Notes for Engineer

**Tech Context:**
- This is a Vue 3 SFC using Composition API with TypeScript
- Inertia.js handles routing (no Vue Router)
- TailwindCSS for all styling (no custom CSS files to create)
- No backend changes needed (all state is client-side UI state)

**Key Files:**
- `resources/js/pages/Jobs/Show.vue` - Only file modified (2038 lines)

**State Management:**
- All state is local refs (no Pinia/Vuex)
- Reactive props from Laravel via Inertia
- Real-time updates via Laravel Echo (already configured)

**Testing:**
- Manual browser testing required (no automated UI tests exist)
- Use browser DevTools responsive mode for mobile testing
- Test with real data if possible (create test jobs/evaluations)

**Common Pitfalls:**
- Don't forget to import `nextTick` when adding `switchToEvaluation`
- Ensure chevron icons rotate with `:class="show ? 'rotate-180' : ''"`
- Verify section IDs match scroll targets in quick action cards
- Test that collapsible sections don't cause layout shift (use smooth transitions)

**DRY Violations to Avoid:**
- Don't duplicate collapsible header pattern - copy it exactly each time
- Use consistent border classes (`border-border/60` for primary, `border-border/50` for secondary)
- Reuse existing computed properties rather than creating new ones

**YAGNI Reminder:**
- Don't add features from "Future Enhancements" section
- Don't add custom components (use inline Vue)
- Don't add localStorage persistence for collapse state (not in design)
- Don't add keyboard shortcuts (not in requirements)

**Commit Strategy:**
- One commit per task (14 total commits)
- Use conventional commit format: `feat:`, `refactor:`, `fix:`, `polish:`
- Keep commit messages concise but descriptive
- Include "why" in multi-line commit messages when context helps

**Questions to Ask:**
- None - plan is complete and self-contained
- If stuck, refer back to design doc: `docs/plans/2025-11-20-jobs-show-layout-redesign.md`
