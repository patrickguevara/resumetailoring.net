<?php

namespace Tests\Feature;

use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Models\TailoredResume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResumeEvaluationShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_fetch_evaluation_details(): void
    {
        $user = User::factory()->create();

        $resume = Resume::create([
            'user_id' => $user->id,
            'title' => 'Platform Resume',
            'slug' => 'platform-resume',
            'description' => 'Resume for platform engineering roles.',
            'content_markdown' => '# Summary',
        ]);

        $job = JobDescription::create([
            'user_id' => $user->id,
            'title' => 'Senior Platform Engineer',
            'company' => 'Acme Corp',
            'source_url' => 'https://example.test/jobs/123',
            'content_markdown' => 'Job description content.',
        ]);

        $evaluation = ResumeEvaluation::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'status' => ResumeEvaluation::STATUS_COMPLETED,
            'model' => 'gpt-5-mini',
            'headline' => 'Great match',
            'feedback_markdown' => str_repeat('Detailed feedback. ', 10),
            'notes' => 'Focus on platform experience.',
            'completed_at' => Carbon::now(),
        ]);

        TailoredResume::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'resume_evaluation_id' => $evaluation->id,
            'model' => 'gpt-5-mini',
            'title' => 'Tailored Resume',
            'content_markdown' => 'Tailored content',
        ]);

        $response = $this->actingAs($user)->getJson(route('evaluations.show', $evaluation));

        $response
            ->assertOk()
            ->assertJson([
                'id' => $evaluation->id,
                'status' => ResumeEvaluation::STATUS_COMPLETED,
                'headline' => 'Great match',
                'model' => 'gpt-5-mini',
                'notes' => 'Focus on platform experience.',
                'feedback_markdown' => $evaluation->feedback_markdown,
                'tailored_count' => 1,
                'resume' => [
                    'id' => $resume->id,
                    'title' => 'Platform Resume',
                    'slug' => 'platform-resume',
                ],
            ])
            ->assertJsonPath('job_description.id', $job->id)
            ->assertJsonPath('job_description.source_label', $job->sourceLabel());
    }

    public function test_non_owner_cannot_fetch_evaluation_details(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $resume = Resume::create([
            'user_id' => $user->id,
            'title' => 'Resume',
            'slug' => 'resume',
            'content_markdown' => '# Summary',
        ]);

        $job = JobDescription::create([
            'user_id' => $user->id,
            'title' => 'Senior Platform Engineer',
            'source_url' => 'https://example.test/jobs/123',
            'content_markdown' => 'Job description content.',
        ]);

        $evaluation = ResumeEvaluation::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'status' => ResumeEvaluation::STATUS_COMPLETED,
            'model' => 'gpt-5-mini',
            'feedback_markdown' => 'Feedback',
        ]);

        $this->actingAs($other)
            ->getJson(route('evaluations.show', $evaluation))
            ->assertNotFound();
    }
}
