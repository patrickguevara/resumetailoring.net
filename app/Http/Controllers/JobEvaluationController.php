<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobEvaluationRequest;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Services\ResumeIntelligenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use RuntimeException;

class JobEvaluationController extends Controller
{
    public function store(
        StoreJobEvaluationRequest $request,
        JobDescription $job,
        ResumeIntelligenceService $intelligenceService
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($job->user_id === $user->id, 404);

        $resume = Resume::where('user_id', $user->id)
            ->where('id', $request->integer('resume_id'))
            ->firstOrFail();

        $model = $request->string('model')->trim()->lower()->value();
        $jobUrlOverride = (string) $request->string('job_url_override')->trim();
        $jobUrl = $jobUrlOverride !== '' ? $jobUrlOverride : null;

        $evaluation = $job->evaluations()->create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'status' => ResumeEvaluation::STATUS_PENDING,
            'model' => $model,
            'notes' => $request->string('notes')->trim() ?: null,
        ]);

        try {
            $result = $intelligenceService->evaluate(
                $resume,
                $job,
                $evaluation,
                $jobUrl,
                $model
            );

            $headline = Str::of($result['content'])
                ->before("\n")
                ->stripTags()
                ->trim()
                ->limit(160);

            $evaluation->forceFill([
                'status' => ResumeEvaluation::STATUS_COMPLETED,
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
                'job_url_override' => $exception->getMessage(),
            ]);
        }

        return to_route('jobs.show', $job)->with('flash', [
            'type' => 'success',
            'message' => 'Evaluation completed for this job.',
        ]);
    }
}
