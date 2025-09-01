<?php
// app/Mail/FeedbackNotification.php
namespace App\Mail;

use App\Models\Feedbacks;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FeedbackNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $feedback;
    public $action;

    /**
     * Create a new message instance.
     */
    public function __construct(Feedbacks $feedback, string $action)
    {
        $this->feedback = $feedback;
        $this->action = $action;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = match ($this->action) {
            'deleted'   => 'Votre feedback a été supprimé',
            'created'   => 'Merci pour votre feedback !',
            'updated'   => 'Votre feedback a été mis à jour',
            default     => 'Notification sur votre feedback',
        };

        return $this->subject($subject)
                    ->view('emails.feedback')
                    ->with([
                        'feedback' => $this->feedback,
                        'action'   => $this->action,
                    ]);
    }
}
