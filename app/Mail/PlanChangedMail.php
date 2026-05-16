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

class PlanChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User         $user,
        public readonly Workspace    $workspace,
        public readonly Subscription $subscription,
        public readonly array        $planConfig,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Skoolyst plan has been updated — ' . ucfirst($this->subscription->plan),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing.plan-changed');
    }
}
