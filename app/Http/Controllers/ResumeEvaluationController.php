<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResumeEvaluationRequest;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Services\ResumeIntelligenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ResumeEvaluationController extends Controller
{
    public function store(
        StoreResumeEvaluationRequest $request,
        Resume $resume,
        ResumeIntelligenceService $intelligenceService
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($resume->user_id === $user->id, 404);

        $jobInputType = $request->string('job_input_type')->trim()->lower()->value();
        $model = $request->string('model')->trim()->lower()->value();
        $errorField = $jobInputType === 'text' ? 'job_text' : 'job_url';

        $jobCompany = (string) $request->string('job_company')->trim();

        [$job, $jobUrl] = DB::transaction(function () use ($request, $user, $jobInputType, $jobCompany) {
            $jobTitle = (string) $request->string('job_title')->trim();

            if ($jobInputType === 'text') {
                $jobText = (string) $request->string('job_text')->trim();
                $manualKey = sprintf('manual://%s', (string) Str::uuid());
                $metadata = [];

                if ($jobCompany !== '') {
                    $metadata['company'] = $jobCompany;
                }

                $job = JobDescription::create([
                    'user_id' => $user->id,
                    'source_url' => $manualKey,
                    'title' => $jobTitle !== '' ? $jobTitle : null,
                    'content_markdown' => $jobText,
                    'metadata' => $metadata ?: null,
                ]);

                return [$job, null];
            }

            $jobUrl = (string) $request->string('job_url')->trim();

            $job = JobDescription::firstOrNew([
                'user_id' => $user->id,
                'source_url_hash' => hash('sha256', $jobUrl),
            ]);
            $metadata = $job->metadata ?? [];

            $job->source_url = $jobUrl;
            $job->title = $jobTitle !== '' ? $jobTitle : $job->title;
            if ($jobCompany !== '') {
                $metadata['company'] = $jobCompany;
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

        try {
            $result = $intelligenceService->evaluate($resume, $job, $jobUrl, $model);

            $headline = Str::of($result['content'])
                ->before("\n")
                ->stripTags()
                ->trim()
                ->limit(160);

            $evaluation->forceFill([
                'status' => ResumeEvaluation::STATUS_COMPLETED,
                'model' => $result['model'],
                'feedback_markdown' => $result['content'],
                'headline' => (string) $headline ?: null,
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (RuntimeException $exception) {
            $evaluation->forceFill([
                'status' => ResumeEvaluation::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();

            return back()->withErrors([
                $errorField => $exception->getMessage(),
            ]);
        }

        return to_route('resumes.show', $resume)->with('flash', [
            'type' => 'success',
            'message' => 'Job description evaluated successfully.',
        ]);
    }
}
