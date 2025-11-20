<?php

namespace Tests\Unit;

use App\Jobs\ProcessResumeEvaluation;
use App\Models\JobDescription;
use App\Models\Resume;
use App\Models\ResumeEvaluation;
use App\Models\User;
use App\Services\ResumeIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class ProcessResumeEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_structured_feedback(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $resume = Resume::create([
            'user_id' => $user->id,
            'title' => 'Test Resume',
            'slug' => 'test-resume',
            'content_markdown' => 'Test content',
        ]);
        $job = JobDescription::create([
            'user_id' => $user->id,
            'title' => 'Test Job',
            'company' => 'Test Company',
            'source_url' => 'https://example.com/job',
            'source_url_hash' => hash('sha256', 'https://example.com/job'),
            'content_markdown' => 'Test job description',
        ]);
        $evaluation = ResumeEvaluation::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'job_description_id' => $job->id,
            'status' => 'pending',
        ]);

        $mockService = Mockery::mock(ResumeIntelligenceService::class);
        $mockService->shouldReceive('evaluate')
            ->once()
            ->andReturn([
                'model' => 'gpt-5-nano',
                'content' => '# Summary\n\nTest content',
                'structured' => [
                    'sentiment' => 'good_match',
                    'highlights' => ['matching_skills' => 5],
                    'key_phrases' => ['Great fit'],
                    'sections' => [
                        'summary' => '# Summary\n\nTest',
                        'relevant_experience' => null,
                        'gaps' => null,
                        'recommendations' => null,
                    ],
                ],
                'system_prompt' => 'test',
                'prompt' => 'test',
                'prompt_log' => null,
            ]);

        $this->app->instance(ResumeIntelligenceService::class, $mockService);

        $job = new ProcessResumeEvaluation($evaluation->id);
        $job->handle($mockService);

        $evaluation->refresh();

        $this->assertEquals('completed', $evaluation->status);
        $this->assertNotNull($evaluation->feedback_structured);
        $this->assertEquals('good_match', $evaluation->feedback_structured['sentiment']);
    }
}
