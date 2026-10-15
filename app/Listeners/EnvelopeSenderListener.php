<?php

namespace App\Listeners;

use App\Events\ElderRegistered;
use App\Events\SendEnvelopeTriggered;
use App\Services\SenderEmailTrait;
use Illuminate\Support\Facades\Log;
use App\Models\ContributionEnvelope;
use Illuminate\Support\Facades\Mail;
use App\Mail\EnvelopeSentNotification;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\ElderRegisteredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnvelopeSenderListener
{
    use SenderEmailTrait;
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SendEnvelopeTriggered $event): void
    {
        Log::info("sending envelope by email");
        try {
            Mail::to($event->envelope->recipient, $event->envelope->recipient?->detail?->first_name)->cc([$event->envelope->responsible])->send(new EnvelopeSentNotification($event->envelope));
            $event->envelope->status = "delivered";
            $event->envelope->save();
            Log::info("envelope sent");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
