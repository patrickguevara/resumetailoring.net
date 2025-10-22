<?php

namespace Tests\Feature\Resumes;

use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResumeEvaluationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_index_shows_evaluation_counts(): void
    {
        $user = User::factory()->create();

        $resume = Resume::create([
            'user_id' => $user->id,
            'title' => 'QA Resume',
            'slug' => 'qa-resume',
            'description' => 'Manual testing resume',
            'content_markdown' => '# Summary',
        ]);

        $job = JobDescription::create([
            'user_id' => $user->id,
            'title' => 'QA Engineer',
            'source_url' => 'https://example.com/jobs/qa',
            'source_url_hash' => hash('sha256', 'https://example.com/jobs/qa'),
            'content_markdown' => 'Job details',
        ]);

        ResumeEvaluation::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'status' => ResumeEvaluation::STATUS_COMPLETED,
            'feedback_markdown' => 'Looks good',
            'headline' => 'Strong alignment',
            'completed_at' => now(),
        ]);

        $this->actingAs($user);

        $this->get(route('resumes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Resumes/Index')
                ->where('resumes.0.slug', $resume->slug)
                ->where('resumes.0.evaluations_count', 1)
            );
    }

    public function test_resume_show_displays_evaluations(): void
    {
        $user = User::factory()->create();

        $resume = Resume::create([
            'user_id' => $user->id,
            'title' => 'Backend Resume',
            'slug' => 'backend-resume',
            'description' => 'Backend engineer resume',
            'content_markdown' => '# Summary',
        ]);

        $job = JobDescription::create([
            'user_id' => $user->id,
            'title' => 'Backend Engineer',
            'source_url' => 'https://example.com/jobs/backend',
            'source_url_hash' => hash('sha256', 'https://example.com/jobs/backend'),
            'content_markdown' => 'Job details',
        ]);

        $evaluation = ResumeEvaluation::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'status' => ResumeEvaluation::STATUS_COMPLETED,
            'feedback_markdown' => 'Great match',
            'headline' => 'Excellent fit',
            'completed_at' => now(),
        ]);

        $this->actingAs($user);

        $this->get(route('resumes.show', $resume))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Resumes/Show')
                ->where('resume.slug', $resume->slug)
                ->where('evaluations.0.id', $evaluation->id)
                ->where('evaluations.0.feedback_markdown', 'Great match')
            );
    }
}
