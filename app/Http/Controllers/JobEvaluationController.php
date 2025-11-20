<?php

namespace App\Http\Controllers;

use App\Enums\UsageFeature;
use App\Http\Requests\StoreJobEvaluationRequest;
use App\Jobs\ProcessResumeEvaluation;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Services\UsageMeter;
use Illuminate\Http\RedirectResponse;

class JobEvaluationController extends Controller
{
    public function __construct(private readonly UsageMeter $usageMeter) {}

    public function store(
        StoreJobEvaluationRequest $request,
        JobDescription $job
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($job->user_id === $user->id, 404);

        $this->usageMeter->assertCanUse($user, UsageFeature::Evaluation);

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

        ProcessResumeEvaluation::dispatch(
            evaluationId: $evaluation->id,
            jobUrlOverride: $jobUrl,
            modelOverride: $model,
        );

        $this->usageMeter->increment($user, UsageFeature::Evaluation);

        return to_route('jobs.show', $job)->with('flash', [
            'type' => 'info',
            'message' => 'Evaluation queued. We will notify you when it finishes.',
        ]);
    }
}
