<?php

namespace App\Http\Controllers;

use App\Enums\UsageFeature;
use App\Http\Requests\StoreResumeEvaluationRequest;
use App\Jobs\ProcessResumeEvaluation;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Services\UsageMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResumeEvaluationController extends Controller
{
    public function __construct(private readonly UsageMeter $usageMeter) {}

    public function store(
        StoreResumeEvaluationRequest $request,
        Resume $resume
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($resume->user_id === $user->id, 404);

        $this->usageMeter->assertCanUse($user, UsageFeature::Evaluation);

        if ($resume->ingestion_status !== 'completed') {
            throw ValidationException::withMessages([
                'resume' => 'Resume is still processing. Try again once ingestion completes.',
            ]);
        }

        $jobInputType = $request->string('job_input_type')->trim()->lower()->value();
        $model = $request->string('model')->trim()->lower()->value();

        $jobCompany = (string) $request->string('job_company')->trim();

        [$job, $jobUrl] = DB::transaction(function () use ($request, $user, $jobInputType, $jobCompany) {
            $jobTitle = (string) $request->string('job_title')->trim();

            if ($jobInputType === 'text') {
                $jobText = (string) $request->string('job_text')->trim();
                $manualKey = sprintf('manual://%s', (string) Str::uuid());

                $job = JobDescription::create([
                    'user_id' => $user->id,
                    'source_url' => $manualKey,
                    'title' => $jobTitle !== '' ? $jobTitle : null,
                    'company' => $jobCompany !== '' ? $jobCompany : null,
                    'content_markdown' => $jobText,
                ]);

                return [$job, null];
            }

            $jobUrl = (string) $request->string('job_url')->trim();

            $job = JobDescription::firstOrNew([
                'user_id' => $user->id,
                'source_url_hash' => hash('sha256', $jobUrl),
            ]);
            $metadata = $job->metadata ?? [];
            unset($metadata['company'], $metadata['company_research']);

            $job->source_url = $jobUrl;
            $job->title = $jobTitle !== '' ? $jobTitle : $job->title;
            if ($jobCompany !== '') {
                $job->company = $jobCompany;
            }
            $job->metadata = $metadata !== [] ? $metadata : null;
            $job->save();

            return [$job, $jobUrl];
        });

        $evaluation = $resume->evaluations()->create([
            'user_id' => $user->id,
            'job_description_id' => $job->id,
            'status' => ResumeEvaluation::STATUS_PENDING,
            'model' => $model,
            'notes' => $request->string('notes')->trim() ?: null,
        ]);

        ProcessResumeEvaluation::dispatch(
            evaluationId: $evaluation->id,
            jobUrlOverride: $jobUrl,
            modelOverride: $model,
        );

        $this->usageMeter->increment($user, UsageFeature::Evaluation);

        return to_route('resumes.show', $resume)->with('flash', [
            'type' => 'info',
            'message' => 'Evaluation queued. We will notify you when it finishes.',
        ]);
    }

    public function show(Request $request, ResumeEvaluation $evaluation): JsonResponse
    {
        abort_unless($evaluation->user_id === $request->user()->id, 404);

        $evaluation->loadMissing(['resume:id,title,slug', 'jobDescription']);
        $evaluation->loadCount('tailoredResumes');

        $job = $evaluation->jobDescription;

        return response()->json([
            'id' => $evaluation->id,
            'status' => $evaluation->status,
            'headline' => $evaluation->headline,
            'model' => $evaluation->model,
            'notes' => $evaluation->notes,
            'feedback_markdown' => $evaluation->feedback_markdown,
            'feedback_data' => $evaluation->feedback_data,
            'error_message' => $evaluation->error_message,
            'tailored_count' => $evaluation->tailored_resumes_count ?? 0,
            'completed_at' => $evaluation->completed_at?->toIso8601String(),
            'created_at' => $evaluation->created_at?->toIso8601String(),
            'resume' => $evaluation->resume
                ? [
                    'id' => $evaluation->resume->id,
                    'title' => $evaluation->resume->title,
                    'slug' => $evaluation->resume->slug,
                ]
                : null,
            'job_description' => $job
                ? [
                    'id' => $job->id,
                    'title' => $job->title,
                    'url' => $job->isManual() ? null : $job->source_url,
                    'source_label' => $job->sourceLabel(),
                    'is_manual' => $job->isManual(),
                    'company' => $job->company ?? data_get($job->metadata, 'company'),
                    'description_preview' => $job->content_markdown
                        ? Str::of($job->content_markdown)->stripTags()->limit(600)->value()
                        : null,
                ]
                : null,
        ]);
    }
}
