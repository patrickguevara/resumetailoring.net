<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyResearchRequest;
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
        $company = $companyInput !== '' ? $companyInput : (string) ($metadata['company'] ?? '');

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

        $metadata['company'] = $company;
        $metadata['company_research'] = [
            'summary' => $result['content'],
            'last_ran_at' => now()->toIso8601String(),
            'model' => $result['model'],
        ];

        $job->metadata = $metadata;
        $job->save();

        return to_route('jobs.show', $job)->with('flash', [
            'type' => 'success',
            'message' => 'Company research updated.',
        ]);
    }
}
