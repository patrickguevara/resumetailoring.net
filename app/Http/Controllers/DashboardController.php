<?php

namespace App\Http\Controllers;

use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $resumesCount = $user->resumes()->count();
        $jobsCount = $user->jobDescriptions()->count();
        $evaluationsCount = $user->resumeEvaluations()->count();
        $tailoredCount = $user->tailoredResumes()->count();

        $lastActivity = collect([
            $user->resumes()->latest('updated_at')->value('updated_at'),
            $user->jobDescriptions()->latest('updated_at')->value('updated_at'),
            $user->resumeEvaluations()->latest('created_at')->value('created_at'),
            $user->tailoredResumes()->latest('created_at')->value('created_at'),
        ])
            ->filter()
            ->map(fn ($timestamp) => Carbon::parse($timestamp))
            ->max();

        $recentResumes = $user->resumes()
            ->withCount(['evaluations', 'tailoredResumes'])
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (Resume $resume) => [
                'id' => $resume->id,
                'title' => $resume->title,
                'slug' => $resume->slug,
                'evaluations_count' => $resume->evaluations_count,
                'tailored_count' => $resume->tailored_resumes_count,
                'updated_at' => $resume->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentJobs = $user->jobDescriptions()
            ->withCount(['evaluations', 'tailoredResumes'])
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (JobDescription $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'company' => $job->company ?? data_get($job->metadata, 'company'),
                'source_label' => $job->sourceLabel(),
                'is_manual' => $job->isManual(),
                'evaluations_count' => $job->evaluations_count,
                'tailored_count' => $job->tailored_resumes_count,
                'updated_at' => $job->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentEvaluations = $user->resumeEvaluations()
            ->with([
                'resume:id,title,slug',
                'jobDescription:id,title,company,metadata,source_url',
            ])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (ResumeEvaluation $evaluation) => [
                'id' => $evaluation->id,
                'status' => $evaluation->status,
                'headline' => $evaluation->headline,
                'model' => $evaluation->model,
                'completed_at' => $evaluation->completed_at?->toIso8601String(),
                'created_at' => $evaluation->created_at?->toIso8601String(),
                'resume' => [
                    'title' => $evaluation->resume?->title,
                    'slug' => $evaluation->resume?->slug,
                ],
                'job_description' => [
                    'id' => $evaluation->jobDescription?->id,
                    'title' => $evaluation->jobDescription?->title,
                    'company' => $evaluation->jobDescription?->company
                        ?? data_get($evaluation->jobDescription?->metadata ?? [], 'company'),
                    'url' => $evaluation->jobDescription && ! $evaluation->jobDescription->isManual()
                        ? $evaluation->jobDescription->source_url
                        : null,
                    'source_label' => $evaluation->jobDescription?->sourceLabel(),
                    'is_manual' => $evaluation->jobDescription?->isManual() ?? false,
                ],
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'summary' => [
                'resumes_count' => $resumesCount,
                'jobs_count' => $jobsCount,
                'evaluations_count' => $evaluationsCount,
                'tailored_resumes_count' => $tailoredCount,
                'last_activity_at' => $lastActivity?->toIso8601String(),
            ],
            'recent' => [
                'resumes' => $recentResumes,
                'jobs' => $recentJobs,
                'evaluations' => $recentEvaluations,
            ],
        ]);
    }
}
