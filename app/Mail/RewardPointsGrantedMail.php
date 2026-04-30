<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RewardPointsGrantedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public int $points,
        public ?string $notes,
        public int $newRewardBalance,
        public string $electricianName,
        public ?string $grantedByName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('You received %d reward points — Order #%s', $this->points, $this->order->getKey()),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reward-points-granted',
        );
    }
}
