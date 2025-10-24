<?php

namespace App\Http\Controllers;

use App\Events\ResumeUpdated;
use App\Http\Requests\StoreResumeRequest;
use App\Jobs\ProcessUploadedResume;
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
                    'ingestion_status' => $resume->ingestion_status,
                    'ingestion_error' => $resume->ingestion_error,
                    'ingested_at' => $resume->ingested_at?->toIso8601String(),
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

        $inputType = $request->string('input_type')->trim()->lower()->value() ?: 'markdown';
        $title = $request->string('title')->trim();
        $slug = $this->makeSlug($title);
        $description = $request->string('description')->trim() ?: null;

        if ($inputType === 'pdf') {
            $placeholder = "*Processing uploaded resume. We'll replace this content shortly.*";
            $resume = $user->resumes()->create([
                'title' => $title,
                'description' => $description,
                'content_markdown' => $placeholder,
                'slug' => $slug,
                'ingestion_status' => 'processing',
                'ingestion_error' => null,
                'ingested_at' => null,
            ]);

            $storedPath = $request->file('resume_file')?->store(
                sprintf('resume-uploads/%d', $user->id)
            );

            if ($storedPath === null) {
                $resume->forceFill([
                    'ingestion_status' => 'failed',
                    'ingestion_error' => 'Uploaded resume file could not be stored.',
                ])->save();

                broadcast(new ResumeUpdated($resume->fresh()));

                $flash = [
                    'type' => 'error',
                    'message' => 'We could not store the uploaded resume file.',
                ];
            } else {
                ProcessUploadedResume::dispatch(
                    resumeId: $resume->id,
                    storedPath: $storedPath
                );

                $flash = [
                    'type' => 'info',
                    'message' => 'Resume upload queued. We will notify you once processing finishes.',
                ];
            }
        } else {
            $resume = $user->resumes()->create([
                'title' => $title,
                'description' => $description,
                'content_markdown' => $request->string('content_markdown'),
                'slug' => $slug,
                'ingestion_status' => 'completed',
                'ingestion_error' => null,
                'ingested_at' => now(),
            ]);

            $flash = [
                'type' => 'success',
                'message' => 'Resume saved successfully.',
            ];
        }

        return to_route('resumes.show', $resume)->with('flash', [
            'type' => $flash['type'],
            'message' => $flash['message'],
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
                'ingestion_status' => $resume->ingestion_status,
                'ingestion_error' => $resume->ingestion_error,
                'ingested_at' => $resume->ingested_at?->toIso8601String(),
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
                    'error_message' => $evaluation->error_message,
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
