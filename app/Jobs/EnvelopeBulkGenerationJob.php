<?php

namespace App\Jobs;

use App\Models\ContributionEnvelope;
use App\Services\JubileeEnvelopeService;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnvelopeBulkGenerationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $ids)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(JubileeEnvelopeService $jubileeEnvelopeService): void
    {
        $jubileeEnvelopeService->generateAllEnvelopes(ContributionEnvelope::whereIn("id", $this->ids)->get(), "invitation_template_2.pdf");
    }
}
