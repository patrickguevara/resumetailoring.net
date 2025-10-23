<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResumeEvaluationRequest;
use App\Jobs\ProcessResumeEvaluation;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResumeEvaluationController extends Controller
{
    public function store(
        StoreResumeEvaluationRequest $request,
        Resume $resume
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($resume->user_id === $user->id, 404);

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

        return to_route('resumes.show', $resume)->with('flash', [
            'type' => 'info',
            'message' => 'Evaluation queued. We will notify you when it finishes.',
        ]);
    }
}
