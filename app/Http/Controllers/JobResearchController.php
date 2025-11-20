<?php

namespace App\Http\Controllers;

use App\Enums\UsageFeature;
use App\Http\Requests\StoreCompanyResearchRequest;
use App\Jobs\RunCompanyResearch;
use App\Models\JobDescription;
use App\Services\UsageMeter;
use Illuminate\Http\RedirectResponse;

class JobResearchController extends Controller
{
    public function __construct(private readonly UsageMeter $usageMeter) {}

    public function store(
        StoreCompanyResearchRequest $request,
        JobDescription $job
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($job->user_id === $user->id, 404);

        $this->usageMeter->assertCanUse($user, UsageFeature::CompanyResearch);

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

        RunCompanyResearch::dispatch(
            jobId: $job->id,
            company: $company,
            focus: $focus !== '' ? $focus : null,
            modelOverride: $model,
        );

        $this->usageMeter->increment($user, UsageFeature::CompanyResearch);

        return to_route('jobs.show', $job)->with('flash', [
            'type' => 'info',
            'message' => 'Company research queued. We will notify you with the results soon.',
        ]);
    }
}
