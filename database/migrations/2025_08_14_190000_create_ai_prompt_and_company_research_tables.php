<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_descriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_descriptions', 'company')) {
                $table->string('company')->nullable()->after('title');
            }
        });

        Schema::create('company_research', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_description_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_evaluation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company')->nullable();
            $table->string('model');
            $table->text('focus')->nullable();
            $table->longText('summary');
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['job_description_id', 'ran_at']);
        });

        Schema::create('ai_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('promptable');
            $table->string('category', 32);
            $table->string('model', 64);
            $table->longText('system_prompt')->nullable();
            $table->longText('user_prompt');
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });

        DB::table('job_descriptions')
            ->select(['id', 'user_id', 'metadata', 'company'])
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(100, function ($jobs): void {
                foreach ($jobs as $job) {
                    $metadata = is_string($job->metadata)
                        ? json_decode($job->metadata, true)
                        : $job->metadata;

                    if (! is_array($metadata)) {
                        continue;
                    }

                    $updates = [];
                    $metadataChanged = false;

                    $company = null;
                    if (array_key_exists('company', $metadata)) {
                        $companyValue = $metadata['company'];
                        unset($metadata['company']);
                        $metadataChanged = true;

                        if (is_string($companyValue) && $companyValue !== '') {
                            $company = $companyValue;
                            $updates['company'] = $companyValue;
                        }
                    } elseif (is_string($job->company) && $job->company !== '') {
                        $company = $job->company;
                    }

                    if (isset($metadata['company_research']) && is_array($metadata['company_research'])) {
                        $research = $metadata['company_research'];
                        unset($metadata['company_research']);
                        $metadataChanged = true;

                        $summary = $research['summary'] ?? null;

                        if (is_string($summary) && $summary !== '') {
                            $ranAt = $research['last_ran_at'] ?? null;
                            $ranAtTimestamp = Carbon::now();

                            if (is_string($ranAt) && $ranAt !== '') {
                                try {
                                    $ranAtTimestamp = Carbon::parse($ranAt);
                                } catch (\Throwable) {
                                    $ranAtTimestamp = Carbon::now();
                                }
                            }

                            DB::table('company_research')->insert([
                                'user_id' => $job->user_id,
                                'job_description_id' => $job->id,
                                'resume_evaluation_id' => null,
                                'company' => $company,
                                'model' => $research['model'] ?? 'legacy',
                                'focus' => $research['focus'] ?? null,
                                'summary' => $summary,
                                'ran_at' => $ranAtTimestamp,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                    }

                    if ($metadataChanged || $updates !== []) {
                        $updates['metadata'] = $metadata !== [] ? json_encode($metadata) : null;

                        DB::table('job_descriptions')
                            ->where('id', $job->id)
                            ->update($updates);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('company_research');

        Schema::table('job_descriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('job_descriptions', 'company')) {
                $table->dropColumn('company');
            }
        });
    }
};
