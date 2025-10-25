<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyResearch;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Models\TailoredResume;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $userCount = User::count();
        $subscriberCount = User::query()
            ->whereHas('subscriptions', function ($query) {
                $query->where('stripe_status', 'active');
            })
            ->count();

        $resumesCount = Resume::count();
        $jobDescriptionsCount = JobDescription::count();
        $resumeEvaluationsCount = ResumeEvaluation::count();
        $tailoredResumesCount = TailoredResume::count();
        $companyResearchCount = CompanyResearch::count();

        $actionDefinitions = collect([
            [
                'key' => 'resumes',
                'label' => 'Resumes uploaded',
                'helper' => 'All base resumes saved to workspaces',
                'count' => $resumesCount,
            ],
            [
                'key' => 'job_descriptions',
                'label' => 'Job descriptions tracked',
                'helper' => 'Jobs imported via link or manual entry',
                'count' => $jobDescriptionsCount,
            ],
            [
                'key' => 'resume_evaluations',
                'label' => 'Resume evaluations run',
                'helper' => 'AI evaluations generated for resumes',
                'count' => $resumeEvaluationsCount,
            ],
            [
                'key' => 'tailored_resumes',
                'label' => 'Tailored resumes generated',
                'helper' => 'Customized resume versions created',
                'count' => $tailoredResumesCount,
            ],
            [
                'key' => 'company_research',
                'label' => 'Company research briefs',
                'helper' => 'Research requests tied to job descriptions',
                'count' => $companyResearchCount,
            ],
        ]);

        $totalTrackedActions = (int) $actionDefinitions->sum('count');

        $commonActions = $actionDefinitions
            ->map(function (array $action) use ($userCount, $totalTrackedActions) {
                $count = (int) $action['count'];

                return [
                    'key' => $action['key'],
                    'label' => $action['label'],
                    'helper' => $action['helper'] ?? null,
                    'count' => $count,
                    'avg_per_user' => $userCount > 0 ? round($count / $userCount, 2) : 0,
                    'share' => $totalTrackedActions > 0
                        ? round(($count / $totalTrackedActions) * 100, 1)
                        : 0,
                ];
            })
            ->values()
            ->all();

        $overviewCards = [
            [
                'key' => 'users',
                'label' => 'Total users',
                'value' => $userCount,
                'helper' => 'Registered accounts',
            ],
            [
                'key' => 'subscribers',
                'label' => 'Active subscribers',
                'value' => $subscriberCount,
                'helper' => 'Stripe subscriptions with active status',
            ],
            [
                'key' => 'subscriber_rate',
                'label' => 'Subscriber rate',
                'value' => $userCount > 0 ? round(($subscriberCount / $userCount) * 100, 1) : 0,
                'helper' => 'Percent of users on paid plans',
                'format' => 'percentage',
            ],
        ];

        $totals = [
            [
                'key' => 'resumes',
                'label' => 'Resumes',
                'value' => $resumesCount,
                'helper' => 'Uploaded base resumes',
            ],
            [
                'key' => 'job_descriptions',
                'label' => 'Job descriptions',
                'value' => $jobDescriptionsCount,
                'helper' => 'Tracked job postings',
            ],
            [
                'key' => 'resume_evaluations',
                'label' => 'Resume evaluations',
                'value' => $resumeEvaluationsCount,
                'helper' => 'AI evaluation runs',
            ],
            [
                'key' => 'tailored_resumes',
                'label' => 'Tailored resumes',
                'value' => $tailoredResumesCount,
                'helper' => 'Generated tailored versions',
            ],
            [
                'key' => 'company_research',
                'label' => 'Company research',
                'value' => $companyResearchCount,
                'helper' => 'Research summaries delivered',
            ],
        ];

        return Inertia::render('Admin/Overview', [
            'overview' => [
                'cards' => $overviewCards,
                'totals' => $totals,
            ],
            'usage' => [
                'common_actions' => $commonActions,
                'totals' => [
                    'tracked_actions' => $totalTrackedActions,
                ],
            ],
        ]);
    }
}
