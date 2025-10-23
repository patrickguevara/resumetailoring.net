<?php

namespace App\Http\Controllers;

use App\Models\JobDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $jobs = $request->user()
            ->jobDescriptions()
            ->withCount(['evaluations', 'tailoredResumes'])
            ->withMax('evaluations as last_evaluation_at', 'completed_at')
            ->withMax('tailoredResumes as last_tailored_at', 'created_at')
            ->latest()
            ->get()
            ->map(function (JobDescription $job) {
                $metadata = $job->metadata ?? [];
                $companyResearch = (array) ($metadata['company_research'] ?? []);

                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'company' => $metadata['company'] ?? null,
                    'source_url' => $job->isManual() ? null : $job->source_url,
                    'source_label' => $job->sourceLabel(),
                    'is_manual' => $job->isManual(),
                    'evaluations_count' => $job->evaluations_count,
                    'tailored_resumes_count' => $job->tailored_resumes_count,
                    'has_tailored_resume' => $job->tailored_resumes_count > 0,
                    'has_company_research' => filled($companyResearch['summary'] ?? null),
                    'last_evaluated_at' => $job->last_evaluation_at
                        ? Carbon::parse($job->last_evaluation_at)->toIso8601String()
                        : null,
                    'last_tailored_at' => $job->last_tailored_at
                        ? Carbon::parse($job->last_tailored_at)->toIso8601String()
                        : null,
                    'created_at' => $job->created_at?->toIso8601String(),
                    'updated_at' => $job->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
        ]);
    }

    public function show(Request $request, JobDescription $job): Response
    {
        abort_unless($job->user_id === $request->user()->id, 404);

        $job->load([
            'evaluations' => fn ($query) => $query
                ->with(['resume:id,title,slug'])
                ->latest(),
            'tailoredResumes' => fn ($query) => $query
                ->with(['resume:id,title,slug'])
                ->latest(),
        ]);

        $metadata = $job->metadata ?? [];
        $companyResearch = (array) ($metadata['company_research'] ?? []);

        $resumes = $request->user()
            ->resumes()
            ->select('id', 'title', 'slug')
            ->orderBy('title')
            ->get()
            ->map(fn ($resume) => [
                'id' => $resume->id,
                'title' => $resume->title,
                'slug' => $resume->slug,
            ])
            ->values()
            ->all();

        return Inertia::render('Jobs/Show', [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'company' => $metadata['company'] ?? null,
                'source_url' => $job->isManual() ? null : $job->source_url,
                'source_label' => $job->sourceLabel(),
                'is_manual' => $job->isManual(),
                'description_markdown' => $job->content_markdown,
                'created_at' => $job->created_at?->toIso8601String(),
                'updated_at' => $job->updated_at?->toIso8601String(),
                'company_research' => [
                    'summary' => $companyResearch['summary'] ?? null,
                    'last_ran_at' => $companyResearch['last_ran_at'] ?? null,
                    'model' => $companyResearch['model'] ?? null,
                ],
            ],
            'evaluations' => $job->evaluations
                ->map(fn ($evaluation) => [
                    'id' => $evaluation->id,
                    'status' => $evaluation->status,
                    'headline' => $evaluation->headline,
                    'model' => $evaluation->model,
                    'resume' => [
                        'id' => $evaluation->resume?->id,
                        'title' => $evaluation->resume?->title,
                        'slug' => $evaluation->resume?->slug,
                    ],
                    'notes' => $evaluation->notes,
                    'feedback_markdown' => $evaluation->feedback_markdown,
                    'completed_at' => $evaluation->completed_at?->toIso8601String(),
                    'created_at' => $evaluation->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'tailored_resumes' => $job->tailoredResumes
                ->map(fn ($tailored) => [
                    'id' => $tailored->id,
                    'title' => $tailored->title,
                    'model' => $tailored->model,
                    'resume' => [
                        'id' => $tailored->resume?->id,
                        'title' => $tailored->resume?->title,
                        'slug' => $tailored->resume?->slug,
                    ],
                    'created_at' => $tailored->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'resumes' => $resumes,
        ]);
    }
}
