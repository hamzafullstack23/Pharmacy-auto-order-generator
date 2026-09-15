<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyUploadReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $date;

    public function __construct($date)
    {
        $this->date = $date;
    }

    public function build()
    {
        return $this->subject('Daily Sales Upload Reminder')
                    ->view('emails.daily-upload-reminder')
                    ->with([
                        'date' => $this->date,
                        'uploadUrl' => route('sales.upload'),
                    ]);
    }
}