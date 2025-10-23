<?php

namespace Tests\Feature;

use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Models\TailoredResume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TailoredResumeShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_fetch_tailored_resume_details(): void
    {
        $user = User::factory()->create();

        $resume = Resume::create([
            'user_id' => $user->id,
            'title' => 'Product Resume',
            'slug' => 'product-resume',
            'content_markdown' => '# Summary',
        ]);

        $job = JobDescription::create([
            'user_id' => $user->id,
            'title' => 'Product Manager',
            'source_url' => 'https://example.test/jobs/pm',
            'content_markdown' => 'Job description content.',
        ]);

        $evaluation = ResumeEvaluation::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'status' => ResumeEvaluation::STATUS_COMPLETED,
            'model' => 'gpt-5-mini',
        ]);

        $tailored = TailoredResume::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'resume_evaluation_id' => $evaluation->id,
            'model' => 'gpt-5-mini',
            'title' => 'Tailored Resume',
            'content_markdown' => str_repeat('Tailored content. ', 5),
        ]);

        $response = $this->actingAs($user)->getJson(route('tailored-resumes.show', $tailored));

        $response
            ->assertOk()
            ->assertJson([
                'id' => $tailored->id,
                'title' => 'Tailored Resume',
                'model' => 'gpt-5-mini',
                'content_markdown' => $tailored->content_markdown,
                'evaluation_id' => $evaluation->id,
            ])
            ->assertJsonPath('resume.id', $resume->id)
            ->assertJsonPath('job_description.id', $job->id);
    }

    public function test_non_owner_cannot_fetch_tailored_resume(): void
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
            'title' => 'Product Manager',
            'source_url' => 'https://example.test/jobs/pm',
            'content_markdown' => 'Job description content.',
        ]);

        $evaluation = ResumeEvaluation::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'status' => ResumeEvaluation::STATUS_COMPLETED,
            'model' => 'gpt-5-mini',
        ]);

        $tailored = TailoredResume::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'resume_evaluation_id' => $evaluation->id,
            'model' => 'gpt-5-mini',
            'title' => 'Tailored Resume',
            'content_markdown' => 'Tailored content',
        ]);

        $this->actingAs($other)
            ->getJson(route('tailored-resumes.show', $tailored))
            ->assertNotFound();
    }
}
