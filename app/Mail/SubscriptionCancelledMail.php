<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User         $user,
        public readonly Workspace    $workspace,
        public readonly Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Skoolyst subscription has been cancelled');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing.cancelled');
    }
}
