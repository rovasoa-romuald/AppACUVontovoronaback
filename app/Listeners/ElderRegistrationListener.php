<?php

namespace App\Listeners;

use App\Events\ElderRegistered;
use App\Mail\ElderRegisteredNotification;
use App\Services\SenderEmailTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ElderRegistrationListener
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
    public function handle(ElderRegistered $event): void
    {
        Log::info("running");
        try {
            if (!str_contains($event->user->email, "fictif")) {
                Mail::to($event->user)->send(new ElderRegisteredNotification($event->user));
                //$this->sendEmail(ElderRegisteredNotification::class, [$event->user], [$event->user], []);
            } else {
                Log::info("Cannot send email to a fictive email");
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
