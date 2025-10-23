<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResumeRequest;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResumeController extends Controller
{
    public function index(Request $request)
    {
        $resumes = $request->user()
            ->resumes()
            ->withCount(['evaluations', 'tailoredResumes'])
            ->withMax('evaluations as last_evaluation_at', 'completed_at')
            ->with([
                'tailoredResumes' => fn ($query) => $query
                    ->with('jobDescription')
                    ->latest(),
            ])
            ->latest()
            ->get()
            ->map(function (Resume $resume) {
                $tailoredFor = $resume->tailoredResumes
                    ->filter(fn ($tailored) => $tailored->jobDescription !== null)
                    ->map(fn ($tailored) => [
                        'job_id' => $tailored->jobDescription->id,
                        'job_title' => $tailored->jobDescription->title
                            ?? $tailored->jobDescription->sourceLabel(),
                        'company' => $tailored->jobDescription->company
                            ?? data_get($tailored->jobDescription->metadata, 'company'),
                    ])
                    ->unique(fn ($item) => $item['job_id'])
                    ->take(3)
                    ->values()
                    ->all();

                return [
                    'id' => $resume->id,
                    'slug' => $resume->slug,
                    'title' => $resume->title,
                    'description' => $resume->description,
                    'uploaded_at' => $resume->created_at?->toIso8601String(),
                    'updated_at' => $resume->updated_at?->toIso8601String(),
                    'last_evaluated_at' => $resume->last_evaluation_at
                        ? Carbon::parse($resume->last_evaluation_at)->toIso8601String()
                        : null,
                    'evaluations_count' => $resume->evaluations_count,
                    'tailored_count' => $resume->tailored_resumes_count,
                    'tailored_for' => $tailoredFor,
                ];
            })
            ->values()
            ->all();

        $recentEvaluations = $request->user()
            ->resumeEvaluations()
            ->with(['resume:id,title,slug', 'jobDescription:id,title,source_url'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ResumeEvaluation $evaluation) => [
                'id' => $evaluation->id,
                'status' => $evaluation->status,
                'headline' => $evaluation->headline,
                'resume' => [
                    'title' => $evaluation->resume?->title,
                    'slug' => $evaluation->resume?->slug,
                ],
                'job_description' => [
                    'title' => $evaluation->jobDescription?->title,
                    'url' => $evaluation->jobDescription && ! $evaluation->jobDescription->isManual()
                        ? $evaluation->jobDescription->source_url
                        : null,
                    'source_label' => $evaluation->jobDescription?->sourceLabel() ?? 'Job description',
                    'is_manual' => $evaluation->jobDescription?->isManual() ?? false,
                ],
                'completed_at' => $evaluation->completed_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Resumes/Index', [
            'resumes' => $resumes,
            'recent_evaluations' => $recentEvaluations,
        ]);
    }

    public function store(StoreResumeRequest $request)
    {
        $user = $request->user();

        $resume = $user->resumes()->create([
            'title' => $request->string('title')->trim(),
            'description' => $request->string('description')->trim() ?: null,
            'content_markdown' => $request->string('content_markdown'),
            'slug' => $this->makeSlug($request->string('title')),
        ]);

        return to_route('resumes.show', $resume)->with('flash', [
            'type' => 'success',
            'message' => 'Resume saved successfully.',
        ]);
    }

    public function show(Request $request, Resume $resume)
    {
        $this->authorizeResume($request, $resume);

        $resume->load([
            'evaluations' => fn ($query) => $query
                ->with('jobDescription')
                ->withCount('tailoredResumes')
                ->latest(),
            'tailoredResumes' => fn ($query) => $query
                ->with('jobDescription', 'evaluation')
                ->latest(),
        ]);

        return Inertia::render('Resumes/Show', [
            'resume' => [
                'id' => $resume->id,
                'slug' => $resume->slug,
                'title' => $resume->title,
                'description' => $resume->description,
                'content_markdown' => $resume->content_markdown,
                'created_at' => $resume->created_at?->toIso8601String(),
                'updated_at' => $resume->updated_at?->toIso8601String(),
            ],
            'evaluations' => $resume->evaluations
                ->map(fn ($evaluation) => [
                    'id' => $evaluation->id,
                    'status' => $evaluation->status,
                    'headline' => $evaluation->headline,
                    'model' => $evaluation->model,
                    'notes' => $evaluation->notes,
                    'feedback_markdown' => $evaluation->feedback_markdown,
                    'completed_at' => $evaluation->completed_at?->toIso8601String(),
                    'job_description' => [
                        'id' => $evaluation->jobDescription->id,
                        'title' => $evaluation->jobDescription->title,
                        'url' => $evaluation->jobDescription->isManual()
                            ? null
                            : $evaluation->jobDescription->source_url,
                        'source_label' => $evaluation->jobDescription->sourceLabel(),
                        'is_manual' => $evaluation->jobDescription->isManual(),
                        'company' => $evaluation->jobDescription->company
                            ?? data_get($evaluation->jobDescription->metadata, 'company'),
                    ],
                    'tailored_count' => $evaluation->tailored_resumes_count,
                    'created_at' => $evaluation->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'tailored_resumes' => $resume->tailoredResumes
                ->map(fn ($tailored) => [
                    'id' => $tailored->id,
                    'title' => $tailored->title,
                    'model' => $tailored->model,
                    'content_markdown' => $tailored->content_markdown,
                    'job_description' => $tailored->jobDescription
                        ? [
                            'id' => $tailored->jobDescription->id,
                            'title' => $tailored->jobDescription->title,
                            'url' => $tailored->jobDescription->isManual()
                                ? null
                                : $tailored->jobDescription->source_url,
                            'source_label' => $tailored->jobDescription->sourceLabel(),
                            'is_manual' => $tailored->jobDescription->isManual(),
                            'company' => $tailored->jobDescription->company
                                ?? data_get($tailored->jobDescription->metadata, 'company'),
                        ]
                        : null,
                    'evaluation_id' => $tailored->evaluation?->id,
                    'created_at' => $tailored->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    private function authorizeResume(Request $request, Resume $resume): void
    {
        abort_unless($resume->user_id === $request->user()->id, 404);
    }

    private function makeSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'resume';
        }

        $slug = $base;
        $i = 1;

        while (Resume::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
