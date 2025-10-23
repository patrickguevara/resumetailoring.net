<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyResearchRequest;
use App\Models\AiPrompt;
use App\Models\CompanyResearch;
use App\Models\JobDescription;
use App\Services\ResumeIntelligenceService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class JobResearchController extends Controller
{
    public function store(
        StoreCompanyResearchRequest $request,
        JobDescription $job,
        ResumeIntelligenceService $intelligenceService
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($job->user_id === $user->id, 404);

        $metadata = $job->metadata ?? [];

        $companyInput = (string) $request->string('company')->trim();
        $fallbackCompany = (string) ($job->company ?? ($metadata['company'] ?? ''));
        $company = $companyInput !== '' ? $companyInput : $fallbackCompany;

        if ($company === '') {
            return back()->withErrors([
                'company' => 'Provide the company name before running research.',
            ]);
        }

        $model = $request->string('model')->trim()->lower()->value();
        $focus = (string) $request->string('focus')->trim();

        try {
            $result = $intelligenceService->researchCompany(
                $job,
                $company,
                $job->title ?? 'the role',
                $focus !== '' ? $focus : null,
                $model
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'focus' => $exception->getMessage(),
            ]);
        }

        $ranAt = now();

        $companyResearch = CompanyResearch::create([
            'user_id' => $user->id,
            'job_description_id' => $job->id,
            'company' => $company,
            'model' => $result['model'],
            'focus' => $focus !== '' ? $focus : null,
            'summary' => $result['content'],
            'ran_at' => $ranAt,
        ]);

        $promptLog = $result['prompt_log'] ?? null;
        if ($promptLog instanceof AiPrompt) {
            $promptLog->promptable()->associate($companyResearch);
            $promptLog->save();
        }

        unset($metadata['company'], $metadata['company_research']);

        $job->forceFill([
            'company' => $company,
            'metadata' => $metadata !== [] ? $metadata : null,
        ])->save();

        return to_route('jobs.show', $job)->with('flash', [
            'type' => 'success',
            'message' => 'Company research updated.',
        ]);
    }
}
