<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Feedbacks;

class FeedbackDeletedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $feedback;

    public function __construct(Feedbacks $feedback)
    {
        $this->feedback = $feedback;
    }

    public function build()
    {
        return $this->subject('Votre feedback a été supprimé')
                    ->view('emails.feedback_deleted');
    }
}
