<?php

namespace App\Mail;

use App\Models\ContributionEnvelope;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use \Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Address;

class EnvelopeSentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $elderName = "";
    public $title = "";
    public $subject;
    public $envelope_path = [];
    public $responsibleName = "";
    public $responsiblePromotion = "";
    public $responsiblePhone = "";
    public $replyTo = [];

    /**
     * Create a new message instance.
     */
    public function __construct(ContributionEnvelope $cEnvelope)
    {
        $this->elderName = $cEnvelope->recipient?->detail?->civility . " " . $cEnvelope->recipient?->detail?->last_name;
        $this->responsibleName = $cEnvelope->responsible->detail?->civility . " " . $cEnvelope->responsible?->detail?->first_name . " " . $cEnvelope->responsible?->detail?->last_name;
        $this->responsiblePromotion = $cEnvelope->responsible?->detail?->espa_field . " / Promotion " . $cEnvelope->responsible?->detail?->promotion_year;
        $this->responsiblePhone = $cEnvelope->responsible?->detail?->phone;
        $this->subject = 'Merci pour votre participation à la stèle commémorative de la 40e anniversaire';
        $this->title = 'Célébration de la 40e anniversaire de l\'ACU Vontovorona';
        $envelope_file = $cEnvelope->envelope_path;
        if ($envelope_file) {
            $this->envelope_path[] = Attachment::fromPath(Storage::disk('public')->path($cEnvelope->envelope_path));
        }
        $this->replyTo = [new Address($cEnvelope->responsible->email, $cEnvelope->responsible->detail?->civility . " " . $cEnvelope->responsible?->detail?->first_name . " " . $cEnvelope->responsible?->detail?->last_name)];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
            replyTo: $this->replyTo,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.envelope.new-envelope-notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return $this->envelope_path;
    }
}
