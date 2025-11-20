# Jobs/Show Page Layout Redesign - Design Document

**Date:** November 20, 2025
**Status:** Design Complete
**Goal:** Simplify the Jobs/Show page layout to focus on the primary user workflow: running evaluations and reviewing detailed feedback sequentially.

---

## Problem Statement

The current Jobs/Show page layout has several issues:

- **Sidebar creates visual clutter** - Company research is isolated in a right sidebar, creating a complex two-column layout
- **Long scroll in main column** - Evaluation form, evaluation details, and job description all in one column creates excessive scrolling
- **Unclear hierarchy** - No clear distinction between primary actions (run/review evaluations) and secondary features (research, job description)
- **Poor workflow alignment** - Layout doesn't match the actual user workflow: run evaluation → review feedback → generate tailored resume → move on
- **Company research buried** - Important feature is hidden in sidebar, users may miss it

---

## Solution Overview

**Single-Column Focus Layout** - Remove the sidebar entirely and create a linear, single-column layout that guides users through their primary workflow while keeping secondary features accessible but out of the way.

**Key Principles:**
1. **Primary first** - Evaluation results are immediately visible and prominent
2. **Sequential workflow** - Layout matches user behavior: run → review → generate → next job
3. **Collapsible secondary** - Company research and job description are accessible but collapsed by default
4. **Consistent patterns** - All collapsible sections use the same expand/collapse UI pattern
5. **Mobile-friendly** - Single column eliminates layout shifts on small screens

---

## Layout Structure

### Overall Flow (Top to Bottom)

```
┌─────────────────────────────────────────┐
│ 1. Job Overview Header (Sticky)         │
│    - Title, Company, Metadata           │
│    - 4 Quick Action Cards               │
└─────────────────────────────────────────┘
        ↓
┌─────────────────────────────────────────┐
│ 2. Run New Evaluation (Collapsible)     │
│    - Collapsed by default               │
│    - "New Evaluation" button toggles    │
└─────────────────────────────────────────┘
        ↓
┌─────────────────────────────────────────┐
│ 3. Latest Evaluation Results (Expanded) │
│    - Full feedback with visual cards    │
│    - Sentiment, highlights, split-panel │
│    - "Generate Tailored Resume" CTA     │
└─────────────────────────────────────────┘
        ↓
┌─────────────────────────────────────────┐
│ 4. Evaluation History (Collapsible)     │
│    - Compact list of past evaluations   │
│    - Click to load details above        │
└─────────────────────────────────────────┘
        ↓
┌─────────────────────────────────────────┐
│ 5. Company Research (Collapsible)       │
│    - Moved from sidebar                 │
│    - Collapsed by default               │
└─────────────────────────────────────────┘
        ↓
┌─────────────────────────────────────────┐
│ 6. Job Description (Collapsible)        │
│    - Collapsed by default               │
│    - Full markdown content when open    │
└─────────────────────────────────────────┘
```

**Container:**
- Single column: `max-w-4xl` (1024px) for optimal readability
- Centered: `mx-auto`
- Vertical spacing: `space-y-6` between major sections
- No sidebar - eliminates `lg:grid-cols-[main,sidebar]` layout

---

## Section Designs

### 1. Job Overview Header (No Changes)

**Keep existing design:**
- Sticky positioning at top
- Gradient background with job title and company
- Metadata row (added date, updated date, evaluation count)
- 4 quick action cards that scroll to sections

**Why no changes:** This section already works well and provides good context at a glance.

---

### 2. Run New Evaluation (Already Implemented)

**Current state:** Recently updated to be collapsible
- Collapsed by default
- "New Evaluation" / "Cancel" toggle button
- Form and billing notice hidden until expanded

**Why no changes:** Just implemented this improvement - it already solves the problem of the form taking up too much initial space.

---

### 3. Latest Evaluation Results (New Design)

**Purpose:** Display the most recent (or user-selected) evaluation's complete feedback immediately and prominently.

**Structure:**

**Header Bar:**
```
┌────────────────────────────────────────────┐
│ [Resume Badge] • [Job Title Match]         │
│ "Software Engineer Resume"                 │
│                                            │
│ Model: gpt-5-nano • Completed 2 mins ago   │
│                                    [⋮ Menu]│
└────────────────────────────────────────────┘
```

- **Resume badge** - Shows which resume was evaluated
- **Metadata** - Model used, timestamp (relative)
- **Menu** - Three-dot menu for actions (delete, re-run, download PDF)

**Feedback Display:**
Uses the new EvaluationFeedbackCard component (already built):
- Summary of Fit (sentiment-driven gradient, highlights, key phrases)
- Split-panel: Relevant Experience | Gaps (50/50)
- Recommendations (full width)

**Action Footer:**
```
┌────────────────────────────────────────────┐
│ [✨ Generate Tailored Resume]              │
│                                            │
│ [Download PDF]  [Share Link]               │
└────────────────────────────────────────────┘
```

- **Primary CTA:** "Generate Tailored Resume" button
  - Prominent styling (primary button, full width on mobile)
  - Uses existing tailor functionality
  - Shows spinner when processing
- **Secondary actions:** Download PDF, Share link (smaller, outline buttons)

**Empty State:**
When no evaluations exist yet:
```
┌────────────────────────────────────────────┐
│                                            │
│         [📊 Icon]                          │
│                                            │
│    No evaluations yet                      │
│                                            │
│    Click "New Evaluation" above to analyze │
│    how your resume matches this job.       │
│                                            │
└────────────────────────────────────────────┘
```

- Centered content
- Friendly, encouraging tone
- Points users to the action (run evaluation)

**Visual Design:**
- Card with rounded corners, border, shadow (consistent with current design)
- Feedback display uses existing EvaluationFeedbackCard component
- Action footer has subtle top border separator
- Adequate padding (`p-6`)

**Behavior:**
- Shows most recent evaluation by default
- When user clicks an evaluation in History section (below), this section updates to show that evaluation
- Smooth transition when switching evaluations
- Scroll to top of this section when switching evaluations

---

### 4. Evaluation History (New Design)

**Purpose:** Compact, scannable list of all past evaluations allowing users to switch which evaluation is displayed above.

**Structure:**

**Header (Collapsed State):**
```
┌────────────────────────────────────────────┐
│ Past Evaluations (3)              [Expand ▼]│
└────────────────────────────────────────────┘
```

- Shows count of total evaluations
- Chevron icon indicates expandable
- Click anywhere on header to expand

**Expanded State - List View:**
```
┌────────────────────────────────────────────┐
│ Past Evaluations (3)            [Collapse ▲]│
├────────────────────────────────────────────┤
│                                            │
│ ┌──────────────────────────────────────┐  │
│ │ ● Software Engineer Resume           │  │
│ │   [Strong Match] 8 skills • 2 mins ago│ │
│ │   gpt-5-nano                     [→] │  │
│ └──────────────────────────────────────┘  │
│                                            │
│ ┌──────────────────────────────────────┐  │
│ │ ○ Product Manager Resume             │  │
│ │   [Good Match] 5 skills • 1 hour ago │  │
│ │   gpt-5-mini                     [→] │  │
│ └──────────────────────────────────────┘  │
│                                            │
│ ┌──────────────────────────────────────┐  │
│ │ ○ Software Engineer Resume           │  │
│ │   [Partial Match] 3 skills • 2 days  │  │
│ │   gpt-5-nano                     [→] │  │
│ └──────────────────────────────────────┘  │
│                                            │
│ [Show 2 more...]                           │
└────────────────────────────────────────────┘
```

**Each Row Contains:**
- **Active indicator** - Filled circle (●) for currently displayed evaluation, empty (○) for others
- **Resume name** - Bold, primary text
- **Sentiment badge** - Uses existing SentimentBadge component (small variant)
- **Highlight stat** - Quick peek at matching skills count
- **Timestamp** - Relative (e.g., "2 mins ago", "3 days ago")
- **Model name** - Small, muted text
- **Arrow icon** - Visual affordance that row is clickable

**Behavior:**
- **Collapsed by default** - Just shows header with count
- **Click header to expand** - Reveals full list with smooth height transition
- **Click any row** - Loads that evaluation in the "Latest Evaluation Results" section above
- **Smooth scroll** - After clicking, page scrolls to show the evaluation results section
- **Active state** - Currently displayed evaluation has filled circle (●), accent border, subtle background tint
- **Hover state** - Rows have border highlight on hover
- **Pagination** - Show first 5 evaluations, "Show X more..." button to reveal rest
- **State persistence** - Expanded/collapsed state persists during session (optional: use localStorage)

**Visual Design:**
- Card styling consistent with other sections
- Compact spacing between rows (`gap-2`)
- Each row is a rounded rectangle with border
- Active row: `border-primary bg-primary/5`
- Hover row: `border-primary/60 bg-primary/3`

**Edge Cases:**
- **Only 1 evaluation:** Section auto-collapsed or hidden (no need for history if only one)
- **No evaluations:** Section hidden entirely
- **Evaluations with same resume:** Show model and timestamp to differentiate

**Technical Notes:**
- Uses existing `activeEvaluationId` reactive state
- Clicking row updates `activeEvaluationId.value = clickedEvaluation.id`
- Watch for changes to `activeEvaluationId` and scroll to results section
- List sorted by `created_at` descending (newest first)

---

### 5. Company Research (Moved from Sidebar)

**Purpose:** Provide access to company research generation without cluttering the evaluation workflow.

**Changes from Current:**
- **Moved from right sidebar to main column** - Positioned below evaluation sections
- **Collapsed by default** - Just shows header
- **Same content when expanded** - No functionality changes

**Structure:**

**Header (Collapsed):**
```
┌────────────────────────────────────────────┐
│ Company Research                  [Expand ▼]│
└────────────────────────────────────────────┘
```

**Expanded State:**
```
┌────────────────────────────────────────────┐
│ Company Research                [Collapse ▲]│
├────────────────────────────────────────────┤
│                                            │
│ Generate a briefing to prepare for         │
│ outreach and interviews.                   │
│                                            │
│ [If briefing exists:]                      │
│ ┌──────────────────────────────────────┐  │
│ │ Latest Briefing                       │  │
│ │ Ran 2 hours ago                       │  │
│ │                                       │  │
│ │ [Markdown content:]                   │  │
│ │ ## Recent News                        │  │
│ │ - Company raised Series B             │  │
│ │ - Launched new product line           │  │
│ │                                       │  │
│ │ ## Talking Points                     │  │
│ │ - Ask about growth strategy...        │  │
│ └──────────────────────────────────────┘  │
│                                            │
│ Generate/Update Research:                  │
│ ┌──────────────────────────────────────┐  │
│ │ Company Name: [________________]      │  │
│ │ Role Title: [________________]        │  │
│ │ Focus Areas: [________________]       │  │
│ │             [________________]        │  │
│ │                                       │  │
│ │ [🔬 Generate Research]                │  │
│ └──────────────────────────────────────┘  │
│                                            │
│ [Billing notice if needed]                 │
│                                            │
└────────────────────────────────────────────┘
```

**Behavior:**
- Click header to expand/collapse
- Chevron rotates (▼ → ▲)
- Smooth height transition
- Form and results use existing functionality (no changes)
- Same billing restrictions as current implementation

**Visual Design:**
- Same card styling as other sections
- Slightly lighter border to indicate secondary priority
- Adequate padding when expanded

**Why This Position:**
- Below evaluations since it's a separate, preparatory activity
- Still easily accessible when needed
- Removes sidebar complexity

---

### 6. Job Description (Moved Up from Bottom)

**Purpose:** Make the full job description accessible for reference.

**Changes from Current:**
- **Collapsed by default** - Was always expanded, taking up space
- **Same position in flow** - Still logically follows company research
- **Same content** - No functionality changes

**Structure:**

**Header (Collapsed):**
```
┌────────────────────────────────────────────┐
│ Job Description                   [Expand ▼]│
└────────────────────────────────────────────┘
```

**Expanded State:**
```
┌────────────────────────────────────────────┐
│ Job Description                 [Collapse ▲]│
├────────────────────────────────────────────┤
│                                            │
│ Source: https://company.com/jobs/12345     │
│ Last updated: November 18, 2025            │
│                                            │
│ ┌──────────────────────────────────────┐  │
│ │ [Full job description in markdown]    │  │
│ │                                       │  │
│ │ ## About the Role                     │  │
│ │ We're seeking a Senior Engineer...    │  │
│ │                                       │  │
│ │ ## Responsibilities                   │  │
│ │ - Design and build scalable...        │  │
│ │                                       │  │
│ │ ## Requirements                       │  │
│ │ - 5+ years experience with...         │  │
│ └──────────────────────────────────────┘  │
│                                            │
└────────────────────────────────────────────┘
```

**Behavior:**
- Click header to expand/collapse
- Uses existing MarkdownViewer component
- Shows source URL and last updated timestamp
- Smooth transition

**Visual Design:**
- Consistent card styling
- Markdown content has subtle background (`bg-background/80`)
- Good vertical padding for readability

**Why Collapsed:**
- Users already saw the job overview in header
- Full description is reference material, not needed constantly
- Reduces initial page length significantly

---

## Visual Design System

**Consistency Across All Sections:**

**Card Styling:**
```css
rounded-2xl
border border-border/60
bg-card/80
p-6
shadow-sm
```

**Section Headers:**
```css
text-lg font-semibold text-foreground
```

**Collapsible Headers (Clickable):**
```css
flex items-center justify-between
cursor-pointer
hover:bg-muted/20
transition
```

**Expand/Collapse Icons:**
- Chevron Down (▼) when collapsed
- Chevron Up (▲) when expanded
- Rotate transition: `transition-transform duration-200`
- Rotation class: `rotate-180`

**Spacing:**
- Between major sections: `space-y-6`
- Within cards: `p-6`
- Between card elements: `space-y-4`

**Colors:**
- Primary sections (evaluations): Normal border/background
- Secondary sections (research, description): Slightly lighter border (`border-border/50`)

---

## Responsive Behavior

**Desktop (≥1024px):**
- Single column, max-width 1024px (`max-w-4xl`)
- Centered: `mx-auto`
- All sections full width within container
- Sticky header remains at top

**Tablet (768px - 1023px):**
- Same as desktop, slightly narrower container
- Touch-friendly tap targets (min 44px height)

**Mobile (<768px):**
- Single column (already optimized)
- Sections stack naturally
- Sticky header may un-stick on very small screens (optional)
- Larger tap targets for expand/collapse
- Evaluation history rows stack info vertically

**No Layout Shifts:**
- Removing sidebar eliminates the grid breakpoint change
- Collapsible sections expand/collapse smoothly without horizontal shifts
- Better mobile experience overall

---

## Migration Path

**Phase 1: Restructure Layout**
- Remove `lg:grid-cols-[main,sidebar]` layout
- Change to single column: `max-w-4xl mx-auto`
- Keep all existing sections in new positions

**Phase 2: Build Evaluation History Component**
- Create collapsible list component
- Implement active evaluation indicator
- Wire up click handlers to update `activeEvaluationId`
- Add scroll-to-results behavior

**Phase 3: Update Evaluation Results Section**
- Add header bar with resume name, metadata, menu
- Integrate existing EvaluationFeedbackCard component
- Add action footer with "Generate Tailored Resume" CTA
- Add empty state design

**Phase 4: Make Secondary Sections Collapsible**
- Add collapse state to Company Research section
- Add collapse state to Job Description section
- Default both to collapsed
- Persist state (optional)

**Phase 5: Polish & Test**
- Smooth transitions for all expand/collapse
- Test scroll behavior
- Verify responsive breakpoints
- Visual QA across devices

---

## Technical Implementation Notes

**State Management:**

New reactive state needed:
```typescript
const showEvaluationHistory = ref(false); // collapsed by default
const showCompanyResearch = ref(false); // collapsed by default
const showJobDescription = ref(false); // collapsed by default
```

Existing state to use:
```typescript
const activeEvaluationId = ref<number | null>(null); // already exists
const evaluations = ref<Evaluation[]>([]); // already exists
```

**Active Evaluation Computed:**
```typescript
const activeEvaluation = computed(() => {
    return evaluations.value.find(e => e.id === activeEvaluationId.value)
        ?? evaluations.value[0]
        ?? null;
});
```

**Switch Evaluation Handler:**
```typescript
const switchToEvaluation = (evaluationId: number) => {
    activeEvaluationId.value = evaluationId;

    // Scroll to results section
    const resultsElement = document.getElementById('evaluation-results');
    if (resultsElement) {
        resultsElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};
```

**Section IDs:**
```html
<section id="evaluation-results">...</section>
<section id="evaluation-history">...</section>
<section id="company-research">...</section>
<section id="job-description">...</section>
```

---

## Benefits of This Design

**For Users:**
1. **Clearer focus** - Primary workflow (run/review evaluations) is prominent
2. **Less overwhelming** - Collapsed secondary sections reduce visual clutter
3. **Faster scanning** - Evaluation history provides at-a-glance sentiment badges
4. **Mobile-friendly** - Single column eliminates layout complexity
5. **Logical flow** - Matches actual workflow: run → review → generate

**For Development:**
1. **Simpler layout** - No sidebar grid to maintain
2. **Reusable patterns** - All collapsible sections use same component pattern
3. **Less CSS complexity** - No multi-column responsive breakpoints
4. **Better accessibility** - Linear document structure for screen readers
5. **Easier testing** - Single column, predictable flow

**For Product:**
1. **Feature discoverability** - Company research more visible than in sidebar
2. **Reduced cognitive load** - Users see what they need when they need it
3. **Scalability** - Easy to add new sections in the future
4. **Engagement** - Primary CTA ("Generate Tailored Resume") is prominent
5. **Data insights** - Can track which sections users expand/use most

---

## Success Metrics

After implementation, measure:
- **Time to complete evaluation workflow** - Should decrease (less scrolling, clearer path)
- **Tailored resume generation rate** - Should increase (more prominent CTA)
- **Company research usage** - May increase (more visible than in sidebar)
- **Mobile engagement** - Should improve (better mobile layout)
- **User feedback** - Survey users on clarity and ease of use

---

## Future Enhancements

**Not in scope for initial implementation, but consider later:**

1. **Evaluation comparison mode** - Toggle to show 2 evaluations side-by-side
2. **Filters on history** - Filter by resume, model, sentiment, date range
3. **Sorting options** - Sort history by date, sentiment, resume name
4. **Bulk actions** - Select multiple evaluations to delete, export, compare
5. **Pinned evaluation** - Pin a favorite evaluation to top of history
6. **Evaluation notes** - Add private notes to evaluations
7. **Share evaluations** - Generate shareable link to specific evaluation results
8. **Print view** - Optimized print stylesheet for evaluation results

---

## Alternatives Considered

**Option B: Tabbed Interface**
- Main content area with tabs (Evaluate, Research, Description)
- Right sidebar with evaluation history
- **Why rejected:** More clicks to access features, tabs hide content unnecessarily

**Option C: Card-Based Dashboard**
- Everything as collapsible cards in one column
- No sectional hierarchy
- **Why rejected:** Too many cards, unclear what's primary vs secondary

**Option A (Selected): Single-Column Focus**
- Linear flow matching user workflow
- Clear hierarchy (primary evaluation content → secondary features)
- Simplest implementation

---

## Document History

- **2025-11-20:** Initial design completed through brainstorming session
- **2025-11-20:** User workflow identified as: run evaluation (B) → review feedback (A) → generate tailored resume (D)
- **2025-11-20:** Secondary features (research, job description) identified as separate activities (C)
- **2025-11-20:** Single-column focus approach selected (Option A) over tabbed interface (B) and card dashboard (C)
