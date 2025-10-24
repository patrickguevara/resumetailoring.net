<?php

namespace Tests\Feature\Resumes;

use App\Events\ResumeEvaluationUpdated;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Models\User;
use App\Services\ResumeIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResumeEvaluationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_user_can_submit_manual_job_description(): void
    {
        $user = User::factory()->create();

        $resume = Resume::create([
            'user_id' => $user->id,
            'title' => 'Platform Resume',
            'slug' => 'platform-resume',
            'description' => 'Resume for platform engineering roles.',
            'content_markdown' => '# Summary',
        ]);

        $manualText = 'This role requires expertise in distributed systems, platform tooling, and automation.';

        $service = Mockery::mock(ResumeIntelligenceService::class);
        $service->shouldReceive('evaluate')
            ->once()
            ->withArgs(function (
                Resume $passedResume,
                JobDescription $job,
                ResumeEvaluation $evaluation,
                $jobUrlOverride,
                $modelOverride
            ) use ($resume, $manualText) {
                $this->assertTrue($passedResume->is($resume));
                $this->assertTrue($job->isManual());
                $this->assertSame($manualText, $job->content_markdown);
                $this->assertNull($jobUrlOverride);
                $this->assertSame('gpt-5-nano', $modelOverride);

                return true;
            })
            ->andReturn([
                'model' => 'gpt-test',
                'content' => "Generated feedback headline\nFull feedback body",
            ]);

        $this->app->instance(ResumeIntelligenceService::class, $service);

        Event::fake([ResumeEvaluationUpdated::class]);

        $response = $this->actingAs($user)->post(
            route('resumes.evaluations.store', $resume),
            [
                'job_input_type' => 'text',
                'job_text' => $manualText,
                'job_title' => 'Platform Engineer',
                'model' => 'gpt-5-nano',
                'notes' => 'Focus on backend experience.',
            ]
        );

        $response->assertRedirect(route('resumes.show', $resume));

        Event::assertDispatched(ResumeEvaluationUpdated::class);

        $job = JobDescription::first();
        $this->assertNotNull($job);
        $this->assertTrue($job->isManual());
        $this->assertSame($manualText, $job->content_markdown);
        $this->assertSame('Platform Engineer', $job->title);
        $this->assertTrue(Str::startsWith($job->source_url, 'manual://'));
        $this->assertTrue(Str::isUuid(Str::after($job->source_url, 'manual://')));

        $this->assertDatabaseHas('resume_evaluations', [
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'status' => ResumeEvaluation::STATUS_COMPLETED,
            'model' => 'gpt-test',
        ]);

        $evaluation = ResumeEvaluation::first();
        $this->assertSame("Generated feedback headline\nFull feedback body", $evaluation->feedback_markdown);
        $this->assertSame('Generated feedback headline', $evaluation->headline);
        $this->assertSame('Focus on backend experience.', $evaluation->notes);
    }
}
